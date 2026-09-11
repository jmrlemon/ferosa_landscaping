<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Shared rules and storage for chat attachments, so the customer chat and the
 * admin inbox accept exactly the same things.
 */
class MessageAttachment
{
    // Phone cameras routinely produce 4-8 MB photos. The browser downscales
    // images before upload, but this has to clear the originals that slip
    // through (a failed decode, or a large PDF).
    public const MAX_KB = 12288; // 12 MB

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx'];

    /**
     * `mimes` checks the file's actual content, not the name it was uploaded
     * with, so a renamed .php cannot pass as a .jpg.
     */
    public static function rules(): array
    {
        return ['nullable', 'file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::EXTENSIONS)];
    }

    public static function accept(): string
    {
        return '.'.implode(',.', self::EXTENSIONS);
    }

    /**
     * The disk chat attachments live on.
     *
     * Customers send receipts, IDs and photos of their property through this
     * channel, so it uses the private disk and is served through an
     * ownership-checked route - the same treatment payment proofs get. On the
     * public disk these were readable by anyone who knew or guessed the URL,
     * with no login required.
     */
    public const DISK = 'local';

    /**
     * Where attachments used to be written. Rows created before the move still
     * point here, so reads fall back to it.
     */
    public const LEGACY_DISK = 'public';

    /**
     * @return array{attachment_path:string,attachment_name:string,attachment_mime:string,attachment_size:int}
     */
    public static function store(UploadedFile $file): array
    {
        // store() generates a random name, so the original filename never
        // reaches the filesystem; it is kept in the database for display only.
        $path = $file->store('messages', self::DISK);

        return [
            'attachment_path' => $path,
            'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'attachment_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'attachment_size' => $file->getSize(),
        ];
    }

    public static function delete(?string $path): void
    {
        if (filled($path)) {
            Storage::disk(self::DISK)->delete($path);
            Storage::disk(self::LEGACY_DISK)->delete($path);
        }
    }

    /**
     * The disk a given attachment actually lives on, or null if the file is
     * gone. Attachments written before the move to the private disk are still
     * on the public one.
     */
    public static function diskFor(string $path): ?string
    {
        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }
}
