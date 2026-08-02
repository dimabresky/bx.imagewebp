<?php

namespace Bx\ImageWebp;

use Bitrix\Main\Config\Option;

/**
 * Typed access to bx.imagewebp module options.
 */
final class Config
{
    public const MODULE_ID = 'bx.imagewebp';

    /** @var list<string> */
    public const ALLOWED_ELEMENT_FIELDS = ['DETAIL_PICTURE', 'PREVIEW_PICTURE'];

    public const TARGET_FIELD = 'F';
    public const TARGET_PROPERTY = 'P';

    public static function isEnabled(): bool
    {
        return Option::get(self::MODULE_ID, 'enabled', 'Y') === 'Y';
    }

    /**
     * @return list<int>
     */
    public static function getIblockIds(): array
    {
        $raw = (string)Option::get(self::MODULE_ID, 'iblock_ids', '');
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int)trim($part);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public static function isIblockAllowed(int $iblockId): bool
    {
        if ($iblockId <= 0) {
            return false;
        }

        return in_array($iblockId, self::getIblockIds(), true);
    }

    /**
     * @return list<string>
     */
    public static function getElementFields(): array
    {
        $raw = (string)Option::get(self::MODULE_ID, 'element_fields', 'DETAIL_PICTURE,PREVIEW_PICTURE');
        $fields = [];
        foreach (explode(',', $raw) as $part) {
            $code = strtoupper(trim($part));
            if ($code !== '' && in_array($code, self::ALLOWED_ELEMENT_FIELDS, true)) {
                $fields[$code] = $code;
            }
        }

        return array_values($fields);
    }

    /**
     * @return list<string>
     */
    public static function getPropertyCodes(): array
    {
        $raw = (string)Option::get(self::MODULE_ID, 'property_codes', 'MORE_PHOTO');
        $codes = [];
        foreach (explode(',', $raw) as $part) {
            $code = trim($part);
            if ($code !== '') {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }

    public static function getQuality(): int
    {
        $q = (int)Option::get(self::MODULE_ID, 'quality', '82');
        if ($q < 1) {
            $q = 1;
        }
        if ($q > 100) {
            $q = 100;
        }

        return $q;
    }

    public static function getMaxSide(): int
    {
        $side = (int)Option::get(self::MODULE_ID, 'max_side', '0');

        return max(0, $side);
    }

    public static function getBatchSize(): int
    {
        $batch = (int)Option::get(self::MODULE_ID, 'batch_size', '5');

        return $batch < 1 ? 5 : $batch;
    }

    public static function getMaxAttempts(): int
    {
        $max = (int)Option::get(self::MODULE_ID, 'max_attempts', '5');

        return $max < 1 ? 5 : $max;
    }

    public static function getAgentInterval(): int
    {
        $interval = (int)Option::get(self::MODULE_ID, 'agent_interval', '60');

        return $interval < 10 ? 60 : $interval;
    }

    public static function isLogEnabled(): bool
    {
        return Option::get(self::MODULE_ID, 'log_enabled', 'Y') === 'Y';
    }

    /**
     * Absolute path to module work dir under /upload/bx_imagewebp/.
     */
    public static function getWorkDir(): string
    {
        $docRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\');
        $dir = $docRoot . '/upload/bx_imagewebp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function getLockPath(): string
    {
        return self::getWorkDir() . '/worker.lock';
    }

    public static function getLogPath(): string
    {
        return self::getWorkDir() . '/convert.log';
    }
}
