<?php

namespace Bx\ImageWebp;

use Bitrix\Main\Type\DateTime;

/**
 * Processes pending WebP conversion jobs under an exclusive lock.
 */
final class Worker
{
    /**
     * Process up to $batches batches (each of Config::getBatchSize jobs).
     *
     * @return array{processed:int,success:int,failed:int,skipped_lock:bool}
     */
    public static function process(int $batches = 1): array
    {
        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped_lock' => false,
        ];

        if (!Config::isEnabled()) {
            return $stats;
        }

        $lockFh = fopen(Config::getLockPath(), 'c+');
        if ($lockFh === false) {
            Logger::error('Cannot open lock file: ' . Config::getLockPath());
            $stats['skipped_lock'] = true;

            return $stats;
        }

        if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
            fclose($lockFh);
            $stats['skipped_lock'] = true;

            return $stats;
        }

        try {
            $batches = max(1, $batches);
            for ($i = 0; $i < $batches; $i++) {
                $batchStats = self::processBatch();
                $stats['processed'] += $batchStats['processed'];
                $stats['success'] += $batchStats['success'];
                $stats['failed'] += $batchStats['failed'];
                if ($batchStats['processed'] === 0) {
                    break;
                }
            }
        } finally {
            flock($lockFh, LOCK_UN);
            fclose($lockFh);
        }

        return $stats;
    }

    /**
     * @return array{processed:int,success:int,failed:int}
     */
    private static function processBatch(): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        $maxAttempts = Config::getMaxAttempts();

        $result = QueueTable::getList([
            'select' => [
                'ID',
                'ELEMENT_ID',
                'IBLOCK_ID',
                'TARGET_TYPE',
                'TARGET_CODE',
                'PROPERTY_VALUE_ID',
                'FILE_ID',
                'ATTEMPTS',
            ],
            'filter' => ['=STATUS' => QueueTable::STATUS_PENDING],
            'order' => ['ID' => 'ASC'],
            'limit' => Config::getBatchSize(),
        ]);

        while ($row = $result->fetch()) {
            $stats['processed']++;
            $id = (int)$row['ID'];
            $attempts = (int)$row['ATTEMPTS'];

            QueueTable::update($id, [
                'STATUS' => QueueTable::STATUS_WORKING,
                'DATE_UPDATE' => new DateTime(),
            ]);

            try {
                self::processJob($row);
                QueueTable::delete($id);
                $stats['success']++;
            } catch (StaleTargetException $e) {
                Logger::info(sprintf(
                    'job #%d obsolete: %s',
                    $id,
                    $e->getMessage()
                ));
                QueueTable::delete($id);
                $stats['success']++;
            } catch (\Throwable $e) {
                $attempts++;
                $error = $e->getMessage();
                Logger::error(sprintf(
                    'job #%d element=%d file=%d: %s',
                    $id,
                    (int)$row['ELEMENT_ID'],
                    (int)$row['FILE_ID'],
                    $error
                ));

                QueueTable::update($id, [
                    'STATUS' => $attempts >= $maxAttempts
                        ? QueueTable::STATUS_ERROR
                        : QueueTable::STATUS_PENDING,
                    'ATTEMPTS' => $attempts,
                    'LAST_ERROR' => mb_substr($error, 0, 2000),
                    'DATE_UPDATE' => new DateTime(),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function processJob(array $row): void
    {
        $fileId = (int)$row['FILE_ID'];
        if ($fileId <= 0) {
            throw new \RuntimeException('Empty FILE_ID');
        }

        if (!EnqueueService::isConvertibleFile($fileId)) {
            // Already webp or gone — treat as success (drop job).
            Logger::info('Skip non-convertible or already-webp file #' . $fileId);

            return;
        }

        $webp = Converter::convertFileId($fileId);
        ElementImageReplacer::replace(
            (int)$row['IBLOCK_ID'],
            (int)$row['ELEMENT_ID'],
            (string)$row['TARGET_TYPE'],
            (string)$row['TARGET_CODE'],
            isset($row['PROPERTY_VALUE_ID']) ? (int)$row['PROPERTY_VALUE_ID'] : null,
            $fileId,
            $webp
        );
    }
}
