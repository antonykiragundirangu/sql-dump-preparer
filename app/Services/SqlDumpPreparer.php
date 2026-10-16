<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SqlDumpPreparer
{
    private const PREFIX = 'wpkm_';

    public function prepare(string $sourcePath, string $outputDirectory, string $originalName): array
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('The uploaded SQL dump could not be read.');
        }
        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException('The output directory could not be created.');
        }

        $base = $this->safeBaseName($originalName);
        $mainPath = $outputDirectory.DIRECTORY_SEPARATOR.$base.'_tables.sql';
        $refsPath = $outputDirectory.DIRECTORY_SEPARATOR.$base.'_refs.sql';
        $zipPath = $outputDirectory.DIRECTORY_SEPARATOR.$base.'_prepared.zip';
        $input = fopen($sourcePath, 'rb');
        $main = fopen($mainPath, 'wb');
        $refs = fopen($refsPath, 'wb');
        if ($input === false || $main === false || $refs === false) {
            throw new RuntimeException('One or more processing files could not be opened.');
        }

        $currentTable = null;
        $seenTable = false;
        $refsFound = false;
        $tableCount = 0;

        try {
            while (($line = fgets($input)) !== false) {
                $line = $this->stripSessionDirectives($line);
                if ($line === null) {
                    continue;
                }

                if (preg_match('/^-- Table structure for table `([^`]+)`/', $line, $match) === 1) {
                    $currentTable = $match[1];
                    $seenTable = true;
                    $tableCount++;
                    $refsFound = $refsFound || $this->isRefsTable($currentTable);
                }

                $line = $this->transformLine($line);
                if (! $seenTable) {
                    fwrite($main, $line);
                    fwrite($refs, $line);
                } elseif ($this->isRefsTable($currentTable)) {
                    fwrite($refs, $line);
                } else {
                    fwrite($main, $line);
                }
            }
        } finally {
            fclose($input);
            fclose($main);
            fclose($refs);
        }

        if (! $seenTable || ! $refsFound) {
            $this->removeFiles([$mainPath, $refsPath]);
            throw new RuntimeException(! $seenTable
                ? 'No MySQL table definitions were found in the uploaded file.'
                : 'The dump does not contain a refs or wpkm_refs table.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->removeFiles([$mainPath, $refsPath]);
            throw new RuntimeException('The ZIP archive could not be created.');
        }
        $zip->addFile($mainPath, basename($mainPath));
        $zip->addFile($refsPath, basename($refsPath));
        $zip->close();
        $this->removeFiles([$mainPath, $refsPath]);

        return ['path' => $zipPath, 'download_name' => basename($zipPath), 'table_count' => $tableCount];
    }

    private function transformLine(string $line): string
    {
        $line = str_replace(' COLLATE=utf8mb4_0900_ai_ci', '', $line);
        $line = preg_replace('/\bAUTO_INCREMENT=\d+\b/i', 'AUTO_INCREMENT=1', $line) ?? $line;
        $line = preg_replace_callback(
            '/\b(CREATE\s+TABLE|DROP\s+TABLE\s+IF\s+EXISTS|INSERT\s+INTO|LOCK\s+TABLES|ALTER\s+TABLE|REFERENCES)\s+`([^`]+)`/i',
            fn (array $match) => $match[1].' `'.$this->prefix($match[2]).'`',
            $line
        ) ?? $line;

        return preg_replace_callback(
            '/^(-- (?:Table structure|Dumping data) for table )`([^`]+)`/',
            fn (array $match) => $match[1].'`'.$this->prefix($match[2]).'`',
            $line
        ) ?? $line;
    }

    /**
     * Remove mysqldump session SET directives, even when malformed text or
     * multiple directives have been placed on the same line.
     */
    private function stripSessionDirectives(string $line): ?string
    {
        if (preg_match('/\/\*!\d+\s+SET\b/i', $line) !== 1) {
            return $line;
        }

        $cleaned = preg_replace('/\/\*!\d+\s+SET\b.*?\*\/;?/i', '', $line) ?? $line;

        return preg_match('/^[\s;\'"]*$/', $cleaned) === 1 ? null : $cleaned;
    }

    private function prefix(string $table): string
    {
        return str_starts_with($table, self::PREFIX) ? $table : self::PREFIX.$table;
    }

    private function isRefsTable(?string $table): bool
    {
        return in_array($table, ['refs', self::PREFIX.'refs'], true);
    }

    private function safeBaseName(string $name): string
    {
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($name, PATHINFO_FILENAME)) ?: 'sql_dump';
        return trim($base, '_') ?: 'sql_dump';
    }

    private function removeFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
