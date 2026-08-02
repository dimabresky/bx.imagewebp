<?php

/**
 * Autoload map for bx.imagewebp.
 */

use Bitrix\Main\Loader;
use Bx\ImageWebp\Agent;
use Bx\ImageWebp\Capability;
use Bx\ImageWebp\Config;
use Bx\ImageWebp\Converter;
use Bx\ImageWebp\ElementImageReplacer;
use Bx\ImageWebp\EnqueueService;
use Bx\ImageWebp\Handlers;
use Bx\ImageWebp\Logger;
use Bx\ImageWebp\QueueTable;
use Bx\ImageWebp\StaleTargetException;
use Bx\ImageWebp\Worker;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loader::registerAutoLoadClasses(
    'bx.imagewebp',
    [
        Config::class => 'lib/Config.php',
        Capability::class => 'lib/Capability.php',
        Logger::class => 'lib/Logger.php',
        QueueTable::class => 'lib/QueueTable.php',
        StaleTargetException::class => 'lib/StaleTargetException.php',
        EnqueueService::class => 'lib/EnqueueService.php',
        Handlers::class => 'lib/Handlers.php',
        Converter::class => 'lib/Converter.php',
        ElementImageReplacer::class => 'lib/ElementImageReplacer.php',
        Worker::class => 'lib/Worker.php',
        Agent::class => 'lib/Agent.php',
    ]
);
