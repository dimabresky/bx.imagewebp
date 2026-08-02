<?php

namespace Bx\ImageWebp;

/**
 * Iblock element event handlers: enqueue only, no conversion.
 */
final class Handlers
{
    public static function onAfterIBlockElementAdd(array &$arFields): void
    {
        if (isset($arFields['RESULT']) && $arFields['RESULT'] === false) {
            return;
        }

        $iblockId = (int)($arFields['IBLOCK_ID'] ?? 0);
        $elementId = (int)($arFields['ID'] ?? 0);
        if ($iblockId <= 0 || $elementId <= 0) {
            return;
        }

        EnqueueService::enqueueElement($iblockId, $elementId);
    }

    public static function onAfterIBlockElementUpdate(array &$arFields): void
    {
        if (empty($arFields['RESULT'])) {
            return;
        }

        $elementId = (int)($arFields['ID'] ?? 0);
        if ($elementId <= 0) {
            return;
        }

        $iblockId = (int)($arFields['IBLOCK_ID'] ?? 0);
        if ($iblockId <= 0) {
            $iblockId = self::resolveIblockId($elementId);
        }
        if ($iblockId <= 0) {
            return;
        }

        EnqueueService::enqueueElement($iblockId, $elementId);
    }

    private static function resolveIblockId(int $elementId): int
    {
        if (!\CModule::IncludeModule('iblock')) {
            return 0;
        }

        $row = \CIBlockElement::GetList(
            [],
            ['ID' => $elementId],
            false,
            false,
            ['ID', 'IBLOCK_ID']
        )->Fetch();

        return is_array($row) ? (int)($row['IBLOCK_ID'] ?? 0) : 0;
    }
}
