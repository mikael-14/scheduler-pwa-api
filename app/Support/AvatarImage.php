<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * A profile photo made small: phones send photos of several MB, but an avatar is never shown
 * bigger than a few hundred pixels. Turned upright (phone photos are often stored sideways
 * with an EXIF note), shrunk to fit MAX_SIDE and saved as WebP (JPEG where GD has no WebP),
 * which is usually a few dozen KB.
 */
class AvatarImage
{
    /** Longest side of the stored photo, in pixels (enough for high-density screens). */
    private const MAX_SIDE = 512;

    private const QUALITY = 82;

    /** Bigger images would need more memory to open than a shared host gives (~5 bytes a pixel). */
    private const MAX_PIXELS = 50_000_000;

    private const MEMORY_LIMIT = '512M';

    /**
     * @return array{contents: string, extension: string}
     *
     * @throws ValidationException when the file cannot be read as an image
     */
    public static function fromFile(string $path, string $field = 'avatar'): array
    {
        $size = @getimagesize($path);
        if (! $size || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages([$field => __('The photo could not be processed. Try a smaller one.')]);
        }

        self::raiseMemoryLimit();

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (! $image) {
            throw ValidationException::withMessages([$field => __('The photo could not be processed. Try a smaller one.')]);
        }

        $image = self::upright($image, $path, $size[2]);
        $image = self::shrink($image);

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($image, null, self::QUALITY);
            $extension = 'webp';
        } else {
            $image = self::onWhite($image); // JPEG has no transparency
            imagejpeg($image, null, self::QUALITY);
            $extension = 'jpg';
        }
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return ['contents' => $contents, 'extension' => $extension];
    }

    /** Opening a big photo needs more than the usual 128M; never lowers a higher limit. */
    private static function raiseMemoryLimit(): void
    {
        $current = ini_get('memory_limit');
        if ($current !== '-1' && ini_parse_quantity($current) < ini_parse_quantity(self::MEMORY_LIMIT)) {
            @ini_set('memory_limit', self::MEMORY_LIMIT);
        }
    }

    /** Applies the camera's EXIF orientation, which is lost when the photo is re-saved. */
    private static function upright(\GdImage $image, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270, // imagerotate turns counter-clockwise
            7, 8 => 90,
            default => 0,
        };

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }

    private static function shrink(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = self::MAX_SIDE / max($width, $height);

        // a photo that is already small keeps its size (only re-saved)
        $newWidth = $scale < 1 ? max(1, (int) round($width * $scale)) : $width;
        $newHeight = $scale < 1 ? max(1, (int) round($height * $scale)) : $height;

        $small = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagecopyresampled($small, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $small;
    }

    private static function onWhite(\GdImage $image): \GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);

        return $flat;
    }
}
