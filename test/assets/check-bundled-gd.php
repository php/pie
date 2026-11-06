<?php

declare(strict_types=1);

$configured = ($argv[1] ?? '') === 'configured';
$info       = gd_info();

foreach (['PNG Support' => true, 'JPEG Support' => $configured, 'FreeType Support' => $configured] as $feature => $expected) {
    if ($info[$feature] !== $expected) {
        throw new RuntimeException('Unexpected GD capability: ' . $feature);
    }
}

$image = imagecreatetruecolor(80, 40);
if ($image === false) {
    throw new RuntimeException('Could not create an image');
}

$background = imagecolorallocate($image, 20, 40, 60);
imagefill($image, 0, 0, $background);

foreach ($configured ? ['png', 'jpeg'] : ['png'] as $format) {
    ob_start();
    $written = $format === 'png' ? imagepng($image) : imagejpeg($image);
    $encoded = ob_get_clean();
    if (! $written || $encoded === false) {
        throw new RuntimeException('Could not encode ' . $format);
    }

    $decoded = imagecreatefromstring($encoded);
    if ($decoded === false || imagesx($decoded) !== 80 || imagesy($decoded) !== 40) {
        throw new RuntimeException('Could not round-trip ' . $format);
    }

    if ($format === 'png' && imagecolorat($decoded, 0, 0) !== $background) {
        throw new RuntimeException('PNG round-trip changed the pixel colour');
    }
}

if ($configured) {
    $font = $argv[2] ?? '';
    if (! is_file($font)) {
        throw new RuntimeException('Missing TrueType test font: ' . $font);
    }

    $foreground = imagecolorallocate($image, 255, 255, 255);
    if (imagettftext($image, 16, 0, 5, 25, $foreground, $font, 'GD') === false) {
        throw new RuntimeException('FreeType could not render text');
    }

    $rendered = false;
    for ($x = 0; $x < 80; $x++) {
        for ($y = 0; $y < 40; $y++) {
            $rendered = $rendered || imagecolorat($image, $x, $y) !== $background;
        }
    }

    if (! $rendered) {
        throw new RuntimeException('FreeType did not draw any pixels');
    }
}

echo 'GD ' . ($configured ? 'JPEG/FreeType' : 'default') . ' checks passed.' . PHP_EOL;
