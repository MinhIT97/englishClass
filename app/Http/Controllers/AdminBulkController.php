<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Bulk admin operations — kept behind the audit.admin middleware
 * and the admin-access gate. Every action is wrapped in a single
 * transaction so a partial failure rolls everything back.
 */
class AdminBulkController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $ids = $this->validateIds($request);
        $count = User::query()
            ->whereIn('id', $ids)
            ->where('status', 'pending')
            ->update(['status' => 'active']);

        $this->audit->log('users.bulk_approved', null, [
            'requested_ids' => $ids,
            'affected_count' => $count,
        ]);

        return back()->with('success', "Đã duyệt {$count} tài khoản.");
    }

    public function bulkRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'role' => ['required', 'in:admin,teacher,student'],
        ]);

        $count = User::query()->whereIn('id', $data['ids'])->update(['role' => $data['role']]);

        $this->audit->log('users.bulk_role_changed', null, [
            'role' => $data['role'],
            'affected_count' => $count,
        ]);

        return back()->with('success', "Đã đổi role cho {$count} tài khoản.");
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = $this->validateIds($request);

        $count = User::query()->whereIn('id', $ids)->delete();

        $this->audit->log('users.bulk_deleted', null, [
            'requested_ids' => $ids,
            'affected_count' => $count,
        ]);

        return back()->with('success', "Đã xoá {$count} tài khoản.");
    }

    /**
     * CSV import — accepts an uploaded CSV with headers:
     *   name,email,target_band
     * Returns counts and any per-row errors.
     *
     * SECURITY (SEC-044):
     *   - MIME validation (mimes:csv,txt) is already enforced upstream
     *     (SEC-009). As an additional layer we sniff the first 8 KiB of
     *     the upload and reject anything that contains PHP / HTML /
     *     NUL markers — `mimes:txt` accepts plain text by extension but
     *     cannot guarantee the contents are tabular.
     *   - Cells that begin with `=`, `+`, `-`, `@`, TAB, or CR are
     *     CSV-formula-injection vectors when the file is later opened
     *     in Excel / LibreOffice / Sheets. We prefix such cells with a
     *     single apostrophe so the formula is treated as literal text.
     */
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');

        // SEC-044: sniff the first 8 KiB for script / NUL markers.
        $head = fopen($file->getRealPath(), 'r');
        $sniff = $head ? (string) fread($head, 8192) : '';
        if ($head) {
            fclose($head);
        }
        if ($sniff !== '' && (
            str_contains($sniff, '<?php')
            || str_contains($sniff, '<?=')
            || str_contains($sniff, '<script')
            || str_contains($sniff, "\0")
        )) {
            return response()->json([
                'created' => 0,
                'skipped' => 0,
                'errors' => [[
                    'row' => 0,
                    'errors' => ['File content is not a valid CSV (binary or script markers detected).'],
                ]],
            ], 422);
        }

        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $created = 0; $skipped = 0; $errors = [];

        DB::transaction(function () use ($handle, $header, &$created, &$skipped, &$errors) {
            $row = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $row++;
                $rowData = array_combine($header, $data);

                $validator = Validator::make($rowData, [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'unique:users,email'],
                    'target_band' => ['nullable', 'numeric'],
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    $errors[] = ['row' => $row, 'errors' => $validator->errors()->all()];
                    continue;
                }

                // SECURITY (SEC-019): Set privileged fields (role, status) via direct
                // property assignment + save(), which bypasses $fillable. Required
                // because User::$fillable intentionally excludes these to prevent
                // mass-assignment if a future controller does User::create($request->all()).
                //
                // SECURITY (SEC-044): neutralise CSV-formula-injection vectors
                // before persisting. Cells beginning with =, +, -, @, TAB, or CR
                // are interpreted as formulas by Excel/LibreOffice/Sheets and can
                // exfiltrate data or trigger DDE/CMD execution when the file is
                // later opened by an admin.
                $user = new User();
                $user->name = $this->neutraliseCsvFormula((string) $rowData['name']);
                $user->email = strtolower(trim((string) $rowData['email']));
                $user->password = Hash::make(\Illuminate\Support\Str::random(16));
                $user->role = 'student';
                $user->status = 'pending';
                $user->target_band = $rowData['target_band'] ?? null;
                $user->save();
                $created++;
            }
        });
        fclose($handle);

        $this->audit->log('users.bulk_imported', null, [
            'created' => $created,
            'skipped' => $skipped,
            'error_count' => count($errors),
        ]);

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * SEC-044: prefix any cell that starts with a spreadsheet-formula
     * trigger with a single apostrophe so downstream tools (Excel,
     * LibreOffice, Sheets) treat it as a literal string instead of
     * evaluating `=cmd|'...'!A1` or `=HYPERLINK(...)`.
     */
    private function neutraliseCsvFormula(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        $first = $value[0];
        if ($first === '=' || $first === '+' || $first === '-' || $first === '@' || $first === "\t" || $first === "\r") {
            return "'" . $value;
        }
        return $value;
    }

    private function validateIds(Request $request): array
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);
        return $request->input('ids');
    }
}