<?php

namespace Bx\ImageWebp;

use CFile;
use CIBlockElement;

/**
 * Replaces an element field or file property value with a new WebP file.
 */
final class ElementImageReplacer
{
    /**
     * @param array{path:string,name:string} $webp
     *
     * @throws \RuntimeException
     */
    public static function replace(
        int $iblockId,
        int $elementId,
        string $targetType,
        string $targetCode,
        ?int $propertyValueId,
        int $oldFileId,
        array $webp
    ): void {
        if (!\CModule::IncludeModule('iblock')) {
            throw new \RuntimeException('iblock module is not available');
        }

        $currentFileId = self::resolveCurrentFileId(
            $iblockId,
            $elementId,
            $targetType,
            $targetCode,
            $propertyValueId
        );
        if ($currentFileId !== $oldFileId) {
            throw new StaleTargetException(sprintf(
                'Target changed: expected file %d, current is %d',
                $oldFileId,
                $currentFileId
            ));
        }

        $fileArray = CFile::MakeFileArray($webp['path']);
        if (!is_array($fileArray) || empty($fileArray['tmp_name'])) {
            throw new \RuntimeException('MakeFileArray failed for ' . $webp['path']);
        }

        $fileArray['name'] = $webp['name'];
        $fileArray['MODULE_ID'] = 'iblock';
        if (empty($fileArray['type'])) {
            $fileArray['type'] = 'image/webp';
        }

        EnqueueService::beginInternalUpdate();
        try {
            if ($targetType === Config::TARGET_FIELD) {
                self::replaceField($elementId, $targetCode, $fileArray);
            } elseif ($targetType === Config::TARGET_PROPERTY) {
                if ($propertyValueId === null || $propertyValueId <= 0) {
                    throw new \RuntimeException('PROPERTY_VALUE_ID is required for property target');
                }
                self::replaceProperty($iblockId, $elementId, $targetCode, $propertyValueId, $fileArray);
            } else {
                throw new \RuntimeException('Unknown TARGET_TYPE: ' . $targetType);
            }
            // Do not call CFile::Delete: FILE_ID may be shared across fields/elements.
            // Field Update typically detaches the previous picture; orphaned files are left to Bitrix cleanup.
        } finally {
            EnqueueService::endInternalUpdate();
            @unlink($webp['path']);
        }
    }

    /**
     * Drop job without error when the element target no longer points at queued FILE_ID.
     */
    public static function resolveCurrentFileId(
        int $iblockId,
        int $elementId,
        string $targetType,
        string $targetCode,
        ?int $propertyValueId
    ): int {
        if ($targetType === Config::TARGET_FIELD) {
            $res = CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => $iblockId, 'ID' => $elementId],
                false,
                false,
                ['ID', $targetCode]
            );
            $row = $res ? $res->Fetch() : false;

            return is_array($row) ? (int)($row[$targetCode] ?? 0) : 0;
        }

        if ($targetType === Config::TARGET_PROPERTY) {
            if ($propertyValueId === null || $propertyValueId <= 0) {
                return 0;
            }
            $res = CIBlockElement::GetProperty(
                $iblockId,
                $elementId,
                ['sort' => 'asc'],
                ['CODE' => $targetCode]
            );
            while ($row = $res->Fetch()) {
                if ((int)($row['PROPERTY_VALUE_ID'] ?? 0) === $propertyValueId) {
                    return (int)($row['VALUE'] ?? 0);
                }
            }
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $fileArray
     */
    private static function replaceField(int $elementId, string $fieldCode, array $fileArray): void
    {
        if (!in_array($fieldCode, Config::ALLOWED_ELEMENT_FIELDS, true)) {
            throw new \RuntimeException('Field not allowed: ' . $fieldCode);
        }

        $el = new CIBlockElement();
        $ok = $el->Update(
            $elementId,
            [$fieldCode => $fileArray],
            false,
            false,
            false
        );
        if (!$ok) {
            throw new \RuntimeException('CIBlockElement::Update failed: ' . (string)$el->LAST_ERROR);
        }
    }

    /**
     * @param array<string,mixed> $fileArray
     */
    private static function replaceProperty(
        int $iblockId,
        int $elementId,
        string $propCode,
        int $propertyValueId,
        array $fileArray
    ): void {
        $values = [];
        $found = false;
        $res = CIBlockElement::GetProperty($iblockId, $elementId, ['sort' => 'asc'], ['CODE' => $propCode]);
        while ($row = $res->Fetch()) {
            $valueId = (int)($row['PROPERTY_VALUE_ID'] ?? 0);
            if ($valueId <= 0) {
                continue;
            }
            if ($valueId === $propertyValueId) {
                $values[$valueId] = [
                    'VALUE' => $fileArray,
                    'DESCRIPTION' => (string)($row['DESCRIPTION'] ?? ''),
                ];
                $found = true;
            } else {
                $values[$valueId] = [
                    'VALUE' => (int)($row['VALUE'] ?? 0),
                    'DESCRIPTION' => (string)($row['DESCRIPTION'] ?? ''),
                ];
            }
        }

        if (!$found) {
            throw new \RuntimeException(
                "Property value {$propertyValueId} not found for {$propCode} on element {$elementId}"
            );
        }

        CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [$propCode => $values]);
    }
}
