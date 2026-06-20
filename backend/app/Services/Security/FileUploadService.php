<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileUploadService
{
    protected $allowedMimeTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-zip-compressed'
    ];

    protected $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'
    ];

    /**
     * Upload and secure the file.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string $disk
     * @return array
     * @throws ValidationException
     */
    public function upload(UploadedFile $file, string $folder = 'uploads', string $disk = 'public'): array
    {
        // 1. Validate size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => ['File size exceeds the 10MB limit.']]);
        }

        // 2. Validate extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (empty($extension)) {
            $extension = strtolower($file->guessExtension());
        }

        if (!in_array($extension, $this->allowedExtensions)) {
            throw ValidationException::withMessages(['file' => ['File extension .' . $extension . ' is not allowed.']]);
        }

        // 3. Validate mime type
        $mimeType = $file->getClientMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw ValidationException::withMessages(['file' => ['MIME type ' . $mimeType . ' is not allowed.']]);
        }

        // 4. Double check double-extensions (e.g. file.php.jpg) and special characters
        $originalName = $file->getClientOriginalName();
        if (preg_match('/\.(php|phtml|php3|php4|php5|php7|php8|sh|bash|pl|py|cgi|exe)\./i', $originalName)) {
            throw ValidationException::withMessages(['file' => ['Suspicious file name detected. Double extensions are blocked.']]);
        }

        // 5. Malware scanning hook
        $this->scanForMalware($file);

        // 6. Secure Rename: use UUID to completely eliminate original user input from disk name
        $secureName = (string) Str::uuid() . '.' . $extension;

        // 7. Store File (isolated inside subdirectories)
        $path = $file->storeAs($folder, $secureName, $disk);
        $url = Storage::disk($disk)->url($path);

        return [
            'filename' => $originalName,
            'secure_filename' => $secureName,
            'filepath' => $path,
            'url' => $url,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
        ];
    }

    /**
     * Scan file for malware (Stub/Hook for ClamAV daemon or API scanner).
     *
     * @param UploadedFile $file
     * @return void
     * @throws ValidationException
     */
    protected function scanForMalware(UploadedFile $file): void
    {
        // Check for EICAR standard antivirus test string (for testing validation logic)
        $content = file_get_contents($file->getRealPath());
        if (str_contains($content, 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*')) {
            throw ValidationException::withMessages(['file' => ['Malware signature detected in uploaded file. Upload blocked.']]);
        }

        // Production implementation would use a client to send to ClamAV:
        // $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        // ...
    }
}
