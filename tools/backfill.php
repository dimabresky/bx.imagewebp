<?php

/**
 * Enqueue existing convertible images for configured iblocks.
 *
 * Usage:
 *   php local/modules/bx.imagewebp/tools/backfill.php -- --iblock=12
 *   php local/modules/bx.imagewebp/tools/backfill.php -- --iblock=12 --limit=500 --from-id=0
 *
 * When several iblocks are configured, pass --iblock for paginated runs.
 * Without --iblock, each configured iblock is scanned from ID 0 for --limit elements (one-shot).
 */

use Bitrix\Main\Loader;
use Bx\ImageWebp\Config;
use Bx\ImageWebp\EnqueueService;
use Bx\ImageWebp\Logger;

$iblockFilter = 0;
$limit = 200;
$fromId = 0;

foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--iblock=(\d+)$/', (string)$arg, $m)) {
        $iblockFilter = (int)$m[1];
    }
    if (preg_match('/^--limit=(\d+)$/', (string)$arg, $m)) {
        $limit = max(1, (int)$m[1]);
    }
    if (preg_match('/^--from-id=(\d+)$/', (string)$arg, $m)) {
        $fromId = max(0, (int)$m[1]);
    }
}

$documentRoot = realpath(__DIR__ . '/../../../..');
if ($documentRoot === false || !is_file($documentRoot . '/bitrix/modules/main/include/prolog_before.php')) {
    fwrite(STDERR, "Cannot resolve DOCUMENT_ROOT from " . __DIR__ . PHP_EOL);
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('bx.imagewebp') || !Loader::includeModule('iblock')) {
    fwrite(STDERR, "Required modules are not available\n");
    exit(1);
}

$iblockIds = Config::getIblockIds();
if ($iblockFilter > 0) {
    if (!in_array($iblockFilter, $iblockIds, true)) {
        fwrite(STDERR, "Iblock {$iblockFilter} is not in module options iblock_ids\n");
        exit(1);
    }
    $iblockIds = [$iblockFilter];
} elseif ($fromId > 0 && count($iblockIds) > 1) {
    fwrite(STDERR, "Use --iblock=ID together with --from-id when multiple iblocks are configured\n");
    exit(1);
}

if ($iblockIds === []) {
    fwrite(STDERR, "iblock_ids option is empty\n");
    exit(1);
}

$totalElements = 0;
$totalJobs = 0;

foreach ($iblockIds as $iblockId) {
    $cursor = ($iblockFilter > 0 || count($iblockIds) === 1) ? $fromId : 0;
    $iblockElements = 0;
    $iblockJobs = 0;
    $lastId = $cursor;

    $res = CIBlockElement::GetList(
        ['ID' => 'ASC'],
        [
            'IBLOCK_ID' => $iblockId,
            '>ID' => $cursor,
        ],
        false,
        ['nTopCount' => $limit],
        ['ID', 'IBLOCK_ID']
    );

    while ($row = $res->Fetch()) {
        $elementId = (int)$row['ID'];
        $lastId = $elementId;
        $iblockElements++;
        $iblockJobs += EnqueueService::enqueueElement($iblockId, $elementId);
    }

    $totalElements += $iblockElements;
    $totalJobs += $iblockJobs;

    $line = sprintf(
        'backfill iblock=%d elements=%d jobs_added=%d limit=%d from_id=%d last_id=%d',
        $iblockId,
        $iblockElements,
        $iblockJobs,
        $limit,
        $cursor,
        $lastId
    );
    Logger::info($line);
    fwrite(STDOUT, $line . PHP_EOL);
}

fwrite(STDOUT, sprintf("total elements=%d jobs_added=%d\n", $totalElements, $totalJobs));

exit(0);
