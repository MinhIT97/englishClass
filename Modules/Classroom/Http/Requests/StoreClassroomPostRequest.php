<?php

namespace Modules\Classroom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Classroom\Enums\ClassroomPostType;
use Modules\Classroom\Models\Classroom;

class StoreClassroomPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Classroom $classroom */
        $classroom = $this->route('classroom');

        return $this->user() !== null && $classroom !== null && $this->user()->can('createPost', $classroom);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'type' => ['required', Rule::enum(ClassroomPostType::class)],

            // SECURITY: Restrict uploaded files to safe MIME types.
            // Without this, a teacher could upload a .php or .phtml
            // file that — depending on the storage disk — could be
            // executed by the web server if the storage path is
            // ever served as static assets. 50 MB hard cap to avoid
            // disk-fill DoS.
            'attachment' => [
                'nullable',
                'file',
                'max:51200',
                // SECURITY (SEC-009): Use mimetypes: (content-based) instead of
                // mimes: (extension-only). Extension check is bypassable by renaming
                // malware.php → document.pdf. mimetypes: inspects file content via
                // PHP Fileinfo, rejecting files whose content doesn't match the type.
                // Removed 'txt' and 'csv' — allowlisting only binary-safe types.
                // If plain-text uploads are required, add 'text/plain' and handle with
                // content sanitisation on read.
                //
                // SECURITY (SEC-021): ZIP / archive upload risks.
                // application/zip is allowlisted above (used for lesson bundles,
                // assignment packages). However, allowing ZIP uploads carries
                // residual risks:
                //
                //  1. **Zip slip**: if the file is ever extracted server-side
                //     (e.g. by a teacher preview feature), a malicious archive
                //     with entries like `../../etc/passwd` could escape the
                //     extraction directory. Defence: if extraction is added,
                //     use Symfony\Component\Finder\SplFileInfo + reject any
                //     entry whose name, after ZipArchive::getFromIndex(), starts
                //     with `/` or contains `..` segments. Alternatively, use
                //     ZipArchive::extractTo() with a per-upload scratch dir and
                //     the Filesystem::assertPathIsBelow() helper (Laravel 11+).
                //
                //  2. **Storage disk execution**: the storage/app/public disk
                //     serves uploads as static assets only if `php artisan
                //     storage:link` is run AND the web server is configured to
                //     execute PHP in that path. The Nginx/Apache config MUST
                //     deny script execution under /storage. Do NOT move
                //     `storage/app/public` under the web root with PHP enabled.
                //
                //  3. **Zip-bomb DoS**: a highly-compressed 42 KB ZIP can
                //     decompress to 4 GB. The 50 MB cap above applies to the
                //     compressed upload only. If/when server-side extraction is
                //     added, enforce a separate decompressed-size cap and
                //     reject archives whose compression ratio exceeds a sane
                //     threshold (e.g. 100×).
                //
                //  4. **Nested archives** (zip-inside-zip) bypass mimetypes:
                //     because mimetypes: inspects only the outer container.
                //     Treat any ZIP as untrusted until a future extractor job
                //     runs in a sandboxed worker.
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip,audio/mpeg,video/mp4',
            ],
        ];
    }
}
