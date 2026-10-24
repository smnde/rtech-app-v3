<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ItemImage
{
    public const DISK = 'public';

    public const DIRECTORY = 'items';

    public static function store(?UploadedFile $file, ?string $oldPath = null): ?string
    {
        if (! $file) {
            return $oldPath;
        }

        self::delete($oldPath);

        return $file->store(self::DIRECTORY, self::DISK);
    }

    public static function url(?string $path): ?string
    {
        // URL relatif mengikuti host yang dipakai user (local/prod), bukan APP_URL.
        return filled($path) ? '/storage/'.ltrim($path, '/') : null;
    }

    public static function delete(?string $path): void
    {
        if (filled($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
