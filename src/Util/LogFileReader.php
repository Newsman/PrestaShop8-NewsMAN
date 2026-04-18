<?php
/**
 * Copyright © Dazoot Software S.R.L. All rights reserved.
 *
 * @author Newsman by Dazoot <support@newsman.com>
 * @copyright Copyright © Dazoot Software S.R.L. All rights reserved.
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * @website https://www.newsman.ro/
 */

declare(strict_types=1);

namespace PrestaShop\Module\Newsmanv8\Util;

use PrestaShop\Module\Newsmanv8\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

class LogFileReader
{
    private const FILE_PATTERN = 'newsman_*.log';
    private const FILENAME_REGEX = '/^newsman_\d{4}-\d{2}-\d{2}\.log$/';
    private const CHUNK_SIZE = 8192;
    private const CLEANUP_MARKER = 'newsman_cleanup.marker';
    private const CLEANUP_INTERVAL = 86400;

    private string $logDir;

    public function __construct(
        private Config $config,
    ) {
        $this->logDir = _PS_ROOT_DIR_ . '/var/logs/';
    }

    /**
     * @return array<string> Log filenames sorted by date descending
     */
    public function getFiles(): array
    {
        $files = glob($this->logDir . self::FILE_PATTERN);

        if (false === $files || empty($files)) {
            return [];
        }

        $filenames = array_map('basename', $files);
        rsort($filenames);

        return $filenames;
    }

    public function isValidFilename(string $filename): bool
    {
        return 1 === preg_match(self::FILENAME_REGEX, $filename);
    }

    public function deleteFile(string $filename): bool
    {
        if (!$this->isValidFilename($filename)) {
            return false;
        }

        $path = $this->logDir . basename($filename);

        if (!is_file($path)) {
            return false;
        }

        return @unlink($path);
    }

    public function getFileSize(string $filename): int
    {
        if (!$this->isValidFilename($filename)) {
            return 0;
        }

        $path = $this->logDir . basename($filename);

        if (!is_file($path)) {
            return 0;
        }

        return (int) filesize($path);
    }

    /**
     * Read the last N lines from a log file without loading it entirely into memory.
     * Uses a chunked reverse-read strategy: reads fixed-size blocks from the
     * end of the file until enough newlines are found.
     *
     * @return array{0: array<string>, 1: int} [lines, totalLines (-1 if unknown)]
     */
    public function readTail(string $filename, int $lineCount): array
    {
        if (!$this->isValidFilename($filename)) {
            return [[], 0];
        }

        $filePath = $this->logDir . basename($filename);

        if (!is_file($filePath) || !is_readable($filePath)) {
            return [[], 0];
        }

        $handle = fopen($filePath, 'rb');

        if (false === $handle) {
            return [[], 0];
        }

        $fileSize = (int) fstat($handle)['size'];

        if (0 === $fileSize) {
            fclose($handle);

            return [[], 0];
        }

        $tail = '';
        $offset = $fileSize;
        $linesFound = 0;
        $needLines = $lineCount + 1;

        while ($offset > 0 && $linesFound < $needLines) {
            $readSize = min(self::CHUNK_SIZE, $offset);
            $offset -= $readSize;
            fseek($handle, $offset);
            $chunk = fread($handle, $readSize);

            if (false === $chunk) {
                break;
            }

            $tail = $chunk . $tail;
            $linesFound = substr_count($tail, "\n");
        }

        fclose($handle);

        $allLines = explode("\n", $tail);

        if ('' === end($allLines)) {
            array_pop($allLines);
        }

        $totalLines = (0 === $offset) ? count($allLines) : -1;
        $lines = array_slice($allLines, -$lineCount);

        return [$lines, $totalLines];
    }

    /**
     * Delete log files older than the configured retention period.
     * Throttled by a marker file — runs at most once every 24 hours.
     */
    public function cleanOldLogs(): void
    {
        $markerFile = $this->logDir . self::CLEANUP_MARKER;

        if (is_file($markerFile) && (time() - (int) filemtime($markerFile)) < self::CLEANUP_INTERVAL) {
            return;
        }

        $retentionDays = $this->config->getLogCleanDays();
        $threshold = strtotime('-' . $retentionDays . ' days');

        if (false === $threshold) {
            return;
        }

        $files = glob($this->logDir . self::FILE_PATTERN);

        if (false === $files) {
            return;
        }

        foreach ($files as $file) {
            if (preg_match('/newsman_(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
                $fileDate = strtotime($matches[1]);

                if (false !== $fileDate && $fileDate < $threshold) {
                    @unlink($file);
                }
            }
        }

        @touch($markerFile);
    }
}
