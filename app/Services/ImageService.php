<?php

declare(strict_types=1);

namespace App\Services;

class ImageService
{
    public function getWebp(string $img, int $width, int $height, int $quality): string
    {
        $imgFromString = imagecreatefromstring(file_get_contents($img));
        $imgScaled = imagescale($imgFromString, $width, $height, IMG_BICUBIC);
        ob_start();
        imagewebp($imgScaled, quality: $quality);
        return ob_get_clean();
    }
}
