<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Enregistre une image envoyée en la rendant légère et sans métadonnées :
 * - redressée selon l'orientation de l'appareil photo, puis redimensionnée (côté max.) ;
 * - réencodée en WebP (JPEG si WebP indisponible) : les données EXIF, dont la position GPS, disparaissent ;
 * - si GD n'est pas installé ou l'image illisible, le fichier d'origine est conservé tel quel.
 */
class ImageOptimizer
{
    public const QUALITY = 80;

    /** $disk : « public » (photos visibles) ou « local » (fichiers privés, ex. preuves de paiement). */
    public static function store(UploadedFile $file, string $directory, int $maxSide = 1600, string $disk = 'public'): string
    {
        $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($file->getRealPath())) : false;
        if (! $image instanceof GdImage) {
            return $file->store($directory, $disk);
        }

        $image = self::orient($image, $file);
        $image = self::resize($image, $maxSide);

        $webp = function_exists('imagewebp');
        if (! $webp) {
            $image = self::flatten($image); // JPEG : pas de transparence
        }

        ob_start();
        $webp ? imagewebp($image, null, self::QUALITY) : imagejpeg($image, null, self::QUALITY);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        $path = trim($directory, '/').'/'.Str::random(40).($webp ? '.webp' : '.jpg');
        Storage::disk($disk)->put($path, $binary);

        return $path;
    }

    /** Applique l'orientation EXIF (photos de téléphone prises en portrait). */
    private static function orient(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }
        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }

    private static function resize(GdImage $image, int $maxSide): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) <= $maxSide) {
            return $image;
        }
        $ratio = $maxSide / max($w, $h);
        $resized = imagecreatetruecolor(max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio)));
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $w, $h);
        imagedestroy($image);

        return $resized;
    }

    private static function flatten(GdImage $image): GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);

        return $flat;
    }
}
