<?php

/**
 * CLI worker for bx.imagewebp queue.
 *
 * Usage:
 *   php local/modules/bx.imagewebp/tools/worker.php
 *   php local/modules/bx.imagewebp/tools/worker.php --batches=3
 *
 * Exit codes: 0 ok, 1 bootstrap/module error, 2 lock busy (still ok for timer).
 */

use Bitrix\Main\Loader;
use Bx\ImageWebp\Config;
use Bx\ImageWebp\Logger;
use Bx\ImageWebp\Worker;

$batches = 1;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--batches=(\d+)$/', (string)$arg, $m)) {
        $batches = max(1, (int)$m[1]);
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

if (!Loader::includeModule('bx.imagewebp')) {
    fwrite(STDERR, "Module bx.imagewebp is not installed\n");
    exit(1);
}

if (!Config::isEnabled()) {
    fwrite(STDOUT, "bx.imagewebp disabled\n");
    exit(0);
}

try {
    $stats = Worker::process($batches);
} catch (Throwable $e) {
    Logger::error('CLI worker fatal: ' . $e->getMessage());
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "processed=%d success=%d failed=%d lock_skip=%s\n",
        $stats['processed'],
        $stats['success'],
        $stats['failed'],
        $stats['skipped_lock'] ? 'yes' : 'no'
    )
);

exit($stats['skipped_lock'] && $stats['processed'] === 0 ? 2 : 0);
