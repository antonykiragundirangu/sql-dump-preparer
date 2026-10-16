<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use Throwable;

class SqlDumpImporter
{
    public function __construct(private ConnectionInterface $connection)
    {
    }

    public function import(array $paths, callable $progress): void
    {
        if (config('database.connections.mysql.database') !== 'kemri_publications') {
            throw new RuntimeException('Import blocked: the configured database must be kemri_publications.');
        }

        $totalBytes = max(1, array_sum(array_map(fn ($path) => filesize($path) ?: 0, $paths)));
        $completedBytes = 0;
        $pdo = $this->connection->getPdo();

        foreach ($paths as $path) {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new RuntimeException('A prepared SQL file could not be opened for import.');
            }

            $statement = '';
            try {
                while (($line = fgets($handle)) !== false) {
                    $trimmed = trim($line);
                    if ($statement === '' && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
                        $progress(($completedBytes + ftell($handle)) / $totalBytes);
                        continue;
                    }

                    $statement .= $line;
                    if (str_ends_with(rtrim($line), ';')) {
                        try {
                            $pdo->exec($statement);
                        } catch (Throwable $exception) {
                            throw new RuntimeException('Database import failed near: '.mb_substr(trim($statement), 0, 120), 0, $exception);
                        }
                        $statement = '';
                    }
                    $progress(($completedBytes + ftell($handle)) / $totalBytes);
                }
            } finally {
                fclose($handle);
            }

            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
            $completedBytes += filesize($path) ?: 0;
        }

        $progress(1);
    }
}
