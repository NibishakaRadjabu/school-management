<?php
declare(strict_types=1);

function decode_behavior_signature(string $dataUrl): string
{
    if (strlen($dataUrl) > 700000
        || preg_match('/\Adata:image\/png;base64,([A-Za-z0-9+\/]+={0,2})\z/', $dataUrl, $matches) !== 1) {
        throw new RuntimeException('Both signatures must be provided as valid PNG images.');
    }

    $image = base64_decode($matches[1], true);
    if ($image === false || strlen($image) > 512000) {
        throw new RuntimeException('A signature image is invalid or too large.');
    }

    $details = getimagesizefromstring($image);
    if ($details === false || $details['mime'] !== 'image/png' || $details[0] !== 600 || $details[1] !== 180) {
        throw new RuntimeException('A signature image is invalid. Please sign again.');
    }

    return $image;
}
