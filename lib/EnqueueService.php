<?php

namespace Bx\ImageWebp;

use Bitrix\Main\Type\DateTime;
use CFile;

/**
 * Lightweight enqueue of convertible images for an iblock element.
 */
final class EnqueueService
{
    /** @var bool */
    private static $internalUpdate = false;

    public static function beginInternalUpdate(): void
    {
        self::$internalUpdate = true;
    }

    public static function endInternalUpdate(): void
    {
        self::$internalUpdate = false;
    }

    public static function isInternalUpdate(): bool
    {
        return self::$internalUpdate;
    }

    /**
     * Scan configured fields/properties and enqueue convertible files.
     *
     * @return int number of newly queued jobs
     */
    public static function enqueueElement(int $iblockId, int $elementId): int
    {
        if (self::$internalUpdate) {
            return 0;
        }
        if (!Config::isEnabled() || !Config::isIblockAllowed($iblockId) || $elementId <= 0) {
            return 0;
        }
        if (!\CModule::IncludeModule('iblock')) {
            return 0;
        }

        $added = 0;

        foreach (Config::getElementFields() as $fieldCode) {
            $fileId = self::resolveElementFieldFileId($iblockId, $elementId, $fieldCode);
            if ($fileId > 0 && self::isConvertibleFile($fileId)) {
                if (self::addJob($iblockId, $elementId, Config::TARGET_FIELD, $fieldCode, null, $fileId)) {
                    $added++;
                }
            }
        }

        foreach (Config::getPropertyCodes() as $propCode) {
            $rows = self::resolvePropertyFileRows($iblockId, $elementId, $propCode);
            foreach ($rows as $row) {
                $fileId = (int)$row['FILE_ID'];
                $propertyValueId = (int)$row['PROPERTY_VALUE_ID'];
                if ($fileId > 0 && $propertyValueId > 0 && self::isConvertibleFile($fileId)) {
                    if (self::addJob(
                        $iblockId,
                        $elementId,
                        Config::TARGET_PROPERTY,
                        $propCode,
                        $propertyValueId,
                        $fileId
                    )) {
                        $added++;
                    }
                }
            }
        }

        return $added;
    }

    public static function isConvertibleFile(int $fileId): bool
    {
        if ($fileId <= 0) {
            return false;
        }

        $file = CFile::GetFileArray($fileId);
        if (!is_array($file) || empty($file['SRC'])) {
            return false;
        }

        $contentType = strtolower((string)($file['CONTENT_TYPE'] ?? ''));
        if ($contentType === 'image/webp') {
            return false;
        }

        $allowedMime = ['image/jpeg', 'image/jpg', 'image/png'];
        if (in_array($contentType, $allowedMime, true)) {
            return true;
        }

        $ext = strtolower((string)GetFileExtension((string)($file['ORIGINAL_NAME'] ?? $file['FILE_NAME'] ?? '')));
        if ($ext === 'webp') {
            return false;
        }

        return in_array($ext, ['jpg', 'jpeg', 'png'], true);
    }

    /**
     * @return bool true if a new pending row was inserted
     */
    public static function addJob(
        int $iblockId,
        int $elementId,
        string $targetType,
        string $targetCode,
        ?int $propertyValueId,
        int $fileId
    ): bool {
        if ($fileId <= 0) {
            return false;
        }

        // Deduplicate per target slot (same FILE_ID may be linked from DETAIL + PREVIEW).
        $filter = [
            '=ELEMENT_ID' => $elementId,
            '=TARGET_TYPE' => $targetType,
            '=TARGET_CODE' => $targetCode,
            '=FILE_ID' => $fileId,
            '@STATUS' => [QueueTable::STATUS_PENDING, QueueTable::STATUS_WORKING],
        ];
        if ($propertyValueId !== null && $propertyValueId > 0) {
            $filter['=PROPERTY_VALUE_ID'] = $propertyValueId;
        } else {
            $filter[] = [
                'LOGIC' => 'OR',
                ['=PROPERTY_VALUE_ID' => null],
                ['=PROPERTY_VALUE_ID' => 0],
            ];
        }

        $existing = QueueTable::getList([
            'select' => ['ID'],
            'filter' => $filter,
            'limit' => 1,
        ])->fetch();

        if ($existing) {
            return false;
        }

        $result = QueueTable::add([
            'ELEMENT_ID' => $elementId,
            'IBLOCK_ID' => $iblockId,
            'TARGET_TYPE' => $targetType,
            'TARGET_CODE' => $targetCode,
            'PROPERTY_VALUE_ID' => $propertyValueId,
            'FILE_ID' => $fileId,
            'STATUS' => QueueTable::STATUS_PENDING,
            'ATTEMPTS' => 0,
            'LAST_ERROR' => null,
            'DATE_INSERT' => new DateTime(),
            'DATE_UPDATE' => null,
        ]);

        return $result->isSuccess();
    }

    private static function resolveElementFieldFileId(int $iblockId, int $elementId, string $fieldCode): int
    {
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'ID' => $elementId],
            false,
            false,
            ['ID', 'IBLOCK_ID', $fieldCode]
        );
        $row = $res ? $res->Fetch() : false;
        if (!is_array($row)) {
            return 0;
        }

        return (int)($row[$fieldCode] ?? 0);
    }

    /**
     * @return list<array{FILE_ID:int,PROPERTY_VALUE_ID:int}>
     */
    private static function resolvePropertyFileRows(int $iblockId, int $elementId, string $propCode): array
    {
        $prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $propCode])->Fetch();
        if (!is_array($prop) || (string)($prop['PROPERTY_TYPE'] ?? '') !== 'F') {
            return [];
        }

        $out = [];
        $res = \CIBlockElement::GetProperty($iblockId, $elementId, ['sort' => 'asc'], ['CODE' => $propCode]);
        while ($row = $res->Fetch()) {
            $fileId = (int)($row['VALUE'] ?? 0);
            $valueId = (int)($row['PROPERTY_VALUE_ID'] ?? 0);
            if ($fileId > 0 && $valueId > 0) {
                $out[] = [
                    'FILE_ID' => $fileId,
                    'PROPERTY_VALUE_ID' => $valueId,
                ];
            }
        }

        return $out;
    }
}
