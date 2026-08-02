<?php

namespace Bx\ImageWebp;

/**
 * File + AddMessage2Log logger for conversion pipeline.
 */
final class Logger
{
    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
        if (Config::isLogEnabled()) {
            AddMessage2Log('[bx.imagewebp] ' . $message, Config::MODULE_ID);
        }
    }

    private static function write(string $level, string $message): void
    {
        if (!Config::isLogEnabled()) {
            return;
        }

        $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, $message);
        @file_put_contents(Config::getLogPath(), $line, FILE_APPEND | LOCK_EX);
    }
}
