<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     */
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
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

                User::create([
                    'name' => $rowData['name'],
                    'email' => $rowData['email'],
                    'password' => bcrypt(\Illuminate\Support\Str::random(16)),
                    'role' => 'student',
                    'status' => 'pending',
                    'target_band' => $rowData['target_band'] ?? null,
                ]);
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

    private function validateIds(Request $request): array
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);
        return $request->input('ids');
    }
}