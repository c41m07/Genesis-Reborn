<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use PHPUnit\Framework\TestCase;

class MigrationSystemTest extends TestCase
{
    public function testInitialMigrationIsAvailable(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $legacyDir = $projectRoot . '/migrations';
        $this->assertFalse(is_dir($legacyDir), 'Legacy migrations directory should be retired after cleanup.');

        $migrationPath = $projectRoot . '/database/migrations/V1_0_0__init_schema.sql';
        $this->assertFileExists($migrationPath, 'Initial migration must exist.');

        $content = file_get_contents($migrationPath);
        $this->assertNotFalse($content, 'Initial migration should be readable.');
        $this->assertStringContainsString('-- migrate:up', $content);
        $this->assertStringContainsString('-- migrate:down', $content);
        $this->assertStringContainsString('CREATE TABLE players', $content);
        $this->assertStringNotContainsString('player_technology_queue_legacy', $content);
    }

    public function testSchemaDumpExistsAndMatchesIntent(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $schemaPath = $projectRoot . '/schema.sql';
        $this->assertFileExists($schemaPath, 'Reference schema dump must exist.');

        $schemaContent = file_get_contents($schemaPath);
        $this->assertNotFalse($schemaContent, 'Reference schema dump should be readable.');
        $this->assertStringContainsString('CREATE TABLE players', $schemaContent);
        $this->assertStringContainsString('CREATE TABLE planets', $schemaContent);
        $this->assertStringNotContainsString('CREATE TABLE resources', $schemaContent);
        $this->assertStringNotContainsString('player_technology_queue_legacy', $schemaContent);
    }

    public function testMigrationScriptsHaveValidSyntax(): void
    {
        $scriptsDir = dirname(__DIR__, 2) . '/tools/db';

        $scripts = [
            'migrate.php',
            'backup.php',
        ];

        foreach ($scripts as $script) {
            $scriptPath = $scriptsDir . '/' . $script;
            $this->assertFileExists($scriptPath, "Migration script {$script} should exist");

            $output = [];
            $returnCode = 0;
            exec("php -l {$scriptPath} 2>&1", $output, $returnCode);

            $this->assertEquals(
                0,
                $returnCode,
                "Migration script {$script} should have valid PHP syntax. Output: " . implode("\n", $output)
            );
        }
    }
}
