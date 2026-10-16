<?php

namespace Tests\Feature;

use App\Services\SqlDumpPreparer;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class SqlDumpImportTest extends TestCase
{
    public function test_preparation_failure_is_reported_in_the_import_stream(): void
    {
        $directory = null;
        $this->mock(SqlDumpPreparer::class, function (MockInterface $mock) use (&$directory): void {
            $mock->shouldReceive('prepare')->once()->andReturnUsing(
                function (string $source, string $output, string $name) use (&$directory): array {
                    $directory = $output;
                    mkdir($directory, 0775, true);
                    file_put_contents($directory.'/partial.sql', '-- incomplete output');

                    throw new RuntimeException('The dump does not contain a refs or wpkm_refs table.');
                }
            );
        });

        $response = $this->post('/import', [
            'sql_dump' => UploadedFile::fake()->createWithContent('dump.sql', '-- example SQL dump'),
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-ndjson');
        $updates = array_map(
            fn (string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            explode("\n", trim($response->streamedContent()))
        );

        $this->assertSame('working', $updates[0]['status']);
        $this->assertSame([
            'percent' => 0,
            'label' => 'Import failed',
            'detail' => 'The dump does not contain a refs or wpkm_refs table.',
            'status' => 'error',
        ], $updates[array_key_last($updates)]);
        $this->assertNotNull($directory);
        $this->assertDirectoryDoesNotExist($directory);
    }
}
