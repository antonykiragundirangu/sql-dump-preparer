<?php

namespace Tests\Unit;

use App\Services\SqlDumpPreparer;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class SqlDumpPreparerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sql-preparer-'.bin2hex(random_bytes(5));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) unlink($file);
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function test_it_transforms_and_splits_a_mysql_dump(): void
    {
        $source = $this->directory.DIRECTORY_SEPARATOR.'input.sql';
        file_put_contents($source, <<<'SQL'
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
;/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET .codex\skills\.system\imagegen\agents\openai.yaml CLIENT=@@CHARACTER_SET_CLIENT */;/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
-- Table structure for table `people`
DROP TABLE IF EXISTS `people`;
CREATE TABLE `people` (`id` int AUTO_INCREMENT) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
INSERT INTO `people` VALUES (1);
-- Table structure for table `refs`
DROP TABLE IF EXISTS `refs`;
CREATE TABLE `refs` (`id` int AUTO_INCREMENT) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
INSERT INTO `refs` VALUES (1);
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
SQL);
        $result = (new SqlDumpPreparer())->prepare($source, $this->directory, 'publication dump.sql');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($result['path']) === true);
        $main = $zip->getFromName('publication_dump_tables.sql');
        $refs = $zip->getFromName('publication_dump_refs.sql');
        $zip->close();

        $this->assertStringContainsString('CREATE TABLE `wpkm_people`', $main);
        $this->assertStringContainsString('AUTO_INCREMENT=1', $main);
        $this->assertStringNotContainsString('utf8mb4_0900_ai_ci', $main);
        $this->assertStringNotContainsString('wpkm_refs', $main);
        $this->assertStringNotContainsString('SET TIME_ZONE=@OLD_TIME_ZONE', $main);
        $this->assertStringNotContainsString('/*!401', $main);
        $this->assertStringContainsString('CREATE TABLE `wpkm_refs`', $refs);
        $this->assertStringNotContainsString('wpkm_people', $refs);
        $this->assertStringNotContainsString('SET TIME_ZONE=@OLD_TIME_ZONE', $refs);
        $this->assertStringNotContainsString('SET SQL_MODE=@OLD_SQL_MODE', $main);
        $this->assertStringNotContainsString('.codex', $refs);
    }
}
