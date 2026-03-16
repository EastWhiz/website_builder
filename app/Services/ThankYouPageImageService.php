<?php

namespace App\Services;

use App\Models\ThankYouPage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class ThankYouPageImageService
{
    /**
     * Base directory relative to public (no leading slash): e.g. "images/1"
     */
    public const IMAGE_DIR_RELATIVE = 'images';

    /**
     * Ensure public/images/{user_id}/ exists; create if not.
     * Returns the full path to the directory.
     */
    public function ensureUserImageDirectory(int $userId): string
    {
        $path = public_path(self::IMAGE_DIR_RELATIVE . '/' . $userId);
        File::ensureDirectoryExists($path);

        return $path;
    }

    /**
     * Get relative dir for a user (e.g. "images/1") for use in DB paths.
     */
    public function getUserImageDirRelative(int $userId): string
    {
        return self::IMAGE_DIR_RELATIVE . '/' . $userId;
    }

    /**
     * Save logo for a thank-you page. Overwrites existing file.
     * Returns path to store in DB (e.g. "images/1/logo-5.jpg").
     */
    public function saveLogo(ThankYouPage $page, UploadedFile $file): string
    {
        $dir = $this->ensureUserImageDirectory($page->user_id);
        $ext = $this->sanitizeExtension($file->getClientOriginalExtension());
        $filename = 'logo-' . $page->id . '.' . $ext;
        $file->move($dir, $filename);

        return $this->getUserImageDirRelative($page->user_id) . '/' . $filename;
    }

    /**
     * Save profile image for a thank-you page. Overwrites existing file.
     * Returns path to store in DB (e.g. "images/1/profile-5.jpg").
     */
    public function saveProfileImage(ThankYouPage $page, UploadedFile $file): string
    {
        $dir = $this->ensureUserImageDirectory($page->user_id);
        $ext = $this->sanitizeExtension($file->getClientOriginalExtension());
        $filename = 'profile-' . $page->id . '.' . $ext;
        $file->move($dir, $filename);

        return $this->getUserImageDirRelative($page->user_id) . '/' . $filename;
    }

    /**
     * Delete only the logo file for a thank-you page.
     */
    public function deleteLogo(ThankYouPage $page): void
    {
        $dir = public_path($this->getUserImageDirRelative($page->user_id));
        if (! File::isDirectory($dir)) {
            return;
        }
        $matches = File::glob($dir . '/logo-' . $page->id . '.*');
        foreach ($matches as $path) {
            File::delete($path);
        }
    }

    /**
     * Delete only the profile/hero image file for a thank-you page.
     */
    public function deleteProfileImage(ThankYouPage $page): void
    {
        $dir = public_path($this->getUserImageDirRelative($page->user_id));
        if (! File::isDirectory($dir)) {
            return;
        }
        $matches = File::glob($dir . '/profile-' . $page->id . '.*');
        foreach ($matches as $path) {
            File::delete($path);
        }
    }

    /**
     * Delete logo and profile image files for a thank-you page (logo-{id}.* and profile-{id}.*).
     */
    public function deleteImagesForPage(ThankYouPage $page): void
    {
        $dir = public_path($this->getUserImageDirRelative($page->user_id));
        if (! File::isDirectory($dir)) {
            return;
        }

        $id = $page->id;
        $patterns = ["logo-{$id}.*", "profile-{$id}.*"];

        foreach ($patterns as $pattern) {
            $matches = File::glob($dir . '/' . $pattern);
            foreach ($matches as $path) {
                File::delete($path);
            }
        }
    }

    private function sanitizeExtension(?string $ext): string
    {
        $ext = strtolower(trim((string) $ext));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if ($ext !== '' && in_array($ext, $allowed, true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        return 'jpg';
    }
}
