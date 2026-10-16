<?php

namespace App\Http\Controllers;

use App\Services\SqlDumpPreparer;
use App\Services\SqlDumpImporter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class SqlDumpController extends Controller
{
    public function create()
    {
        return view('sql-dump');
    }

    public function store(Request $request, SqlDumpPreparer $preparer)
    {
        $request->validate([
            'sql_dump' => ['required', 'file', 'mimes:sql,txt', 'max:524288'],
        ], [
            'sql_dump.max' => 'The SQL dump must not be larger than 512 MB.',
            'sql_dump.mimes' => 'Please upload a valid .sql file.',
        ]);

        $upload = $request->file('sql_dump');
        try {
            $result = $preparer->prepare(
                $upload->getRealPath(),
                storage_path('app/sql-preparer/'.bin2hex(random_bytes(12))),
                $upload->getClientOriginalName()
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['sql_dump' => $exception->getMessage()]);
        }

        return response()->download($result['path'], $result['download_name'], [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request, SqlDumpPreparer $preparer, DatabaseManager $databases)
    {
        $request->validate([
            'sql_dump' => ['required', 'file', 'mimes:sql,txt', 'max:524288'],
        ]);

        $upload = $request->file('sql_dump');
        $sourcePath = $upload->getRealPath();
        $originalName = $upload->getClientOriginalName();
        $directory = storage_path('app/sql-preparer/'.bin2hex(random_bytes(12)));

        return response()->stream(function () use ($preparer, $databases, $sourcePath, $originalName, $directory) {
            set_time_limit(0);
            $emit = function (int $percent, string $label, string $detail, string $status = 'working'): void {
                echo json_encode(compact('percent', 'label', 'detail', 'status'))."\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                $emit(22, 'Preparing SQL…', 'Cleaning directives and separating table files.');
                $result = $preparer->prepare($sourcePath, $directory, $originalName);
                $emit(40, 'Opening archive…', 'Preparing ordinary tables for import first.');

                $zip = new ZipArchive();
                if ($zip->open($result['path']) !== true || ! $zip->extractTo($directory)) {
                    throw new RuntimeException('The prepared archive could not be opened.');
                }
                $zip->close();

                $base = pathinfo($result['download_name'], PATHINFO_FILENAME);
                $base = preg_replace('/_prepared$/', '', $base);
                $paths = [
                    $directory.DIRECTORY_SEPARATOR.$base.'_tables.sql',
                    $directory.DIRECTORY_SEPARATOR.$base.'_refs.sql',
                ];

                $lastPercent = 40;
                $importer = new SqlDumpImporter($databases->connection('mysql'));
                $importer->import($paths, function (float $ratio) use ($emit, &$lastPercent): void {
                    $percent = 40 + (int) floor($ratio * 59);
                    if ($percent > $lastPercent) {
                        $lastPercent = $percent;
                        $emit($percent, 'Importing database…', 'Writing tables to kemri_publications.');
                    }
                });
                $emit(100, 'Import complete', 'All prepared tables were imported successfully.', 'complete');
            } catch (Throwable $exception) {
                $emit(0, 'Import failed', $exception->getMessage(), 'error');
            } finally {
                if (is_dir($directory)) {
                    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    @rmdir($directory);
                }
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
