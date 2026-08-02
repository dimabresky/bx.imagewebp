<?php

namespace Bx\ImageWebp;

/**
 * Runtime checks for WebP conversion support.
 */
final class Capability
{
    public static function canConvertToWebp(): bool
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            try {
                $formats = array_change_key_case(\Imagick::queryFormats('WEBP') ?: [], CASE_UPPER);
                if (isset($formats['WEBP'])) {
                    return true;
                }
            } catch (\Throwable $e) {
                // fall through to GD
            }
        }

        return function_exists('imagewebp') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng');
    }

    public static function describe(): string
    {
        $parts = [];
        if (extension_loaded('imagick')) {
            $parts[] = 'imagick';
            try {
                $formats = array_change_key_case(\Imagick::queryFormats('WEBP') ?: [], CASE_UPPER);
                $parts[] = isset($formats['WEBP']) ? 'imagick-webp=yes' : 'imagick-webp=no';
            } catch (\Throwable $e) {
                $parts[] = 'imagick-webp=error';
            }
        } else {
            $parts[] = 'imagick=no';
        }
        $parts[] = function_exists('imagewebp') ? 'gd-webp=yes' : 'gd-webp=no';

        return implode('; ', $parts);
    }
}
