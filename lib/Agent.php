<?php

namespace Bx\ImageWebp;

/**
 * Bitrix agent entry point for WebP queue processing.
 *
 * Register: \Bx\ImageWebp\Agent::run();
 */
final class Agent
{
    public static function run(): string
    {
        $return = '\\Bx\\ImageWebp\\Agent::run();';

        if (!Config::isEnabled()) {
            return $return;
        }

        try {
            Worker::process(1);
        } catch (\Throwable $e) {
            Logger::error('Agent fatal: ' . $e->getMessage());
        }

        return $return;
    }
}
