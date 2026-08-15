<?php

namespace Bx\ImageWebp;

/**
 * Runtime checks for WebP conversion support.
 */
final class Capability
{
    public static function canConvertToWebp(): bool
    {
        if (self::imagickSupportsWebp()) {
            return true;
        }

        return function_exists('imagewebp') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng');
    }

    public static function describe(): string
    {
        $parts = [];
        if (extension_loaded('imagick')) {
            $parts[] = 'imagick';
            $probe = self::probeImagickWebp();
            if ($probe['error'] !== null) {
                $parts[] = 'imagick-webp-key=error';
                $parts[] = 'imagick-webp-value=error';
                $parts[] = 'imagick-webp=error';
            } else {
                $parts[] = $probe['by_key'] ? 'imagick-webp-key=yes' : 'imagick-webp-key=no';
                $parts[] = $probe['by_value'] ? 'imagick-webp-value=yes' : 'imagick-webp-value=no';
                $parts[] = ($probe['by_key'] || $probe['by_value']) ? 'imagick-webp=yes' : 'imagick-webp=no';
            }
        } else {
            $parts[] = 'imagick=no';
        }
        $parts[] = function_exists('imagewebp') ? 'gd-webp=yes' : 'gd-webp=no';

        return implode('; ', $parts);
    }

    /**
     * True when Imagick lists WEBP as a format key and/or value in queryFormats().
     */
    private static function imagickSupportsWebp(): bool
    {
        $probe = self::probeImagickWebp();

        return $probe['error'] === null && ($probe['by_key'] || $probe['by_value']);
    }

    /**
     * Imagick::queryFormats() may return either associative keys (WEBP => ...)
     * or a numeric list of format names ([0 => 'WEBP']). Accept both.
     *
     * @return array{by_key:bool,by_value:bool,error:?string}
     */
    private static function probeImagickWebp(): array
    {
        $result = [
            'by_key' => false,
            'by_value' => false,
            'error' => null,
        ];

        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            return $result;
        }

        try {
            $formats = \Imagick::queryFormats('WEBP') ?: [];
            $keysUpper = array_change_key_case($formats, CASE_UPPER);
            $result['by_key'] = isset($keysUpper['WEBP']);
            $valuesUpper = array_map(
                static fn ($v) => strtoupper((string)$v),
                array_values($formats)
            );
            $result['by_value'] = in_array('WEBP', $valuesUpper, true);
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}
