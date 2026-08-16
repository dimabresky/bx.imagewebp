<?php

namespace Bx\ImageWebp;

use Bitrix\Main\File\Image;
use Bitrix\Main\File\Image\Gd;
use Bitrix\Main\File\Image\Rectangle;
use CFile;

/**
 * Converts a Bitrix file (png/jpg/jpeg) to a temporary WebP file.
 */
final class Converter
{
    /**
     * @return array{path:string,name:string} absolute path and basename of webp
     *
     * @throws \RuntimeException
     */
    public static function convertFileId(int $fileId): array
    {
        if (!Capability::canConvertToWebp()) {
            throw new \RuntimeException('WebP conversion is not available: ' . Capability::describe());
        }

        $file = CFile::GetFileArray($fileId);
        if (!is_array($file)) {
            throw new \RuntimeException('File not found: ' . $fileId);
        }

        $docRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\');
        $srcRel = (string)($file['SRC'] ?? '');
        if ($srcRel === '') {
            throw new \RuntimeException('Empty SRC for file ' . $fileId);
        }

        $srcPath = $docRoot . $srcRel;
        if (!is_file($srcPath)) {
            throw new \RuntimeException('Physical file missing: ' . $srcPath);
        }

        $workDir = Config::getWorkDir() . '/tmp';
        if (!is_dir($workDir)) {
            @mkdir($workDir, 0775, true);
        }

        $baseName = pathinfo((string)($file['ORIGINAL_NAME'] ?? $file['FILE_NAME'] ?? 'image'), PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-zA-Z0-9_\-]+/u', '_', (string)$baseName) ?: 'image';
        $destName = $baseName . '_' . $fileId . '_' . str_replace('.', '', uniqid('', true)) . '.webp';
        $destPath = $workDir . '/' . $destName;

        $srcMeta = sprintf(
            'mime=%s wxh=%sx%s src_size=%d',
            (string)($file['CONTENT_TYPE'] ?? ''),
            (string)($file['WIDTH'] ?? '0'),
            (string)($file['HEIGHT'] ?? '0'),
            (int)filesize($srcPath)
        );

        $image = new Image($srcPath);
        if (!$image->load()) {
            throw new \RuntimeException('Failed to load image: ' . $srcPath . ' (' . $srcMeta . ')');
        }

        try {
            $maxSide = Config::getMaxSide();
            if ($maxSide > 0) {
                $info = $image->getInfo();
                if ($info !== null) {
                    $width = (int)$info->getWidth();
                    $height = (int)$info->getHeight();
                    if ($width > 0 && $height > 0 && max($width, $height) > $maxSide) {
                        $source = new Rectangle($width, $height);
                        $destination = new Rectangle($maxSide, $maxSide);
                        if ($source->resize($destination, Image::RESIZE_PROPORTIONAL)) {
                            $image->resize($source, $destination);
                        }
                    }
                }
            }

            self::ensureGdTruecolor($image);

            if (!$image->saveAs($destPath, Config::getQuality(), Image::FORMAT_WEBP)) {
                throw new \RuntimeException('Failed to save WebP: ' . $destPath . ' (' . $srcMeta . ')');
            }
        } finally {
            $image->clear();
        }

        if (!is_file($destPath) || filesize($destPath) <= 0) {
            throw new \RuntimeException('WebP output is empty: ' . $destPath . ' (' . $srcMeta . ')');
        }

        return [
            'path' => $destPath,
            'name' => $destName,
        ];
    }

    /**
     * GD imagewebp() can return true and write a 0-byte file for palette images.
     * Convert to truecolor before FORMAT_WEBP save when the engine is Gd.
     */
    private static function ensureGdTruecolor(Image $image): void
    {
        if (!function_exists('imagepalettetotruecolor') || !function_exists('imageistruecolor')) {
            return;
        }

        $engineProp = new \ReflectionProperty(Image::class, 'engine');
        $engineProp->setAccessible(true);
        $engine = $engineProp->getValue($image);
        if (!$engine instanceof Gd) {
            return;
        }

        $resource = $engine->getResource();
        if ($resource === null) {
            return;
        }

        if (!imageistruecolor($resource)) {
            imagepalettetotruecolor($resource);
        }
    }
}
