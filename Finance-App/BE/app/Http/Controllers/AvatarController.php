<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AvatarController extends Controller
{
    public function show(string $filename): BinaryFileResponse
    {
        abort_unless(preg_match('/\A[A-Za-z0-9_-]+\.(?:jpe?g|png)\z/iD', $filename) === 1, 404);

        $disk = Storage::disk('public');
        $diskRoot = realpath($disk->path(''));
        $avatarDirectory = realpath($disk->path('avatars'));
        $avatarPath = realpath($disk->path('avatars/'.$filename));

        abort_unless(
            $diskRoot !== false
                && $avatarDirectory !== false
                && $avatarPath !== false
                && dirname($avatarDirectory) === $diskRoot
                && dirname($avatarPath) === $avatarDirectory
                && is_file($avatarPath)
                && is_readable($avatarPath),
            404
        );

        $expectedMime = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => null,
        };
        $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->file($avatarPath);

        abort_unless($expectedMime !== null && $actualMime === $expectedMime, 404);

        return response()->file($avatarPath, [
            'Content-Type' => $expectedMime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }
}
