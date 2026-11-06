<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeGenerator
{
    /**
     * An SVG QR code for an http(s) URL, or null when the value is not one.
     */
    public function svg(?string $url, int $size = 240): ?string
    {
        $url = trim((string) $url);

        if (! preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd()));

        return $writer->writeString($url);
    }
}
