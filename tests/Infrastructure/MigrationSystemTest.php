<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use PHPUnit\Framework\TestCase;

class MigrationSystemTest extends TestCase
{
    public function testMigrationFilesExist(): void
    {
        $migrationDir = dirname(__DIR__, 2) . '/migrations';

        $this->assertDirectoryExists($migrationDir, 'Migration directory should exist');

        $criticalMigrations = [
            '20250920_migration_tracking.sql',
            '20250920_schema_safe.sql',
        ];

        foreach ($criticalMigrations as $migration) {
            $filePath = $migrationDir . '/' . $migration;
            $this->assertFileExists($filePath, "Critical migration file {$migration} should exist");
            $this->assertNotEmpty(file_get_contents($filePath), "Migration file {$migration} should not be empty");
        }
    }

    public function testMigrationScriptsHaveValidSyntax(): void
    {
        $scriptsDir = dirname(__DIR__, 2) . '/tools/db';

        $scripts = [
            'migrate.php',
            'create-database.php',
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

    public function testSafeSchemaMigrationUsesSafeStatements(): void
    {
        $schemaFile = dirname(__DIR__, 2) . '/migrations/20250920_schema_safe.sql';
        $content = file_get_contents($schemaFile);

        // Should use CREATE IF NOT EXISTS
        $this->assertStringContainsString(
            'CREATE TABLE IF NOT EXISTS',
            $content,
            'Safe schema migration should use CREATE TABLE IF NOT EXISTS'
        );

        // Should NOT contain destructive DROP statements
        $this->assertStringNotContainsString(
            'DROP TABLE',
            $content,
            'Safe schema migration should not contain DROP TABLE statements'
        );

        // Should contain essential tables
        $essentialTables = ['players', 'planets', 'buildings', 'technologies'];
        foreach ($essentialTables as $table) {
            $this->assertStringContainsString(
                $table,
                $content,
                "Safe schema migration should define {$table} table"
            );
        }
    }

    public function testMigrationTrackingTableDefinition(): void
    {
        $trackingFile = dirname(__DIR__, 2) . '/migrations/20250920_migration_tracking.sql';
        $content = file_get_contents($trackingFile);

        // Should create migrations table
        $this->assertStringContainsString(
            'CREATE TABLE IF NOT EXISTS migrations',
            $content,
            'Migration tracking should create migrations table'
        );

        // Should have required columns
        $requiredColumns = ['filename', 'applied_at', 'checksum'];
        foreach ($requiredColumns as $column) {
            $this->assertStringContainsString(
                $column,
                $content,
                "Migration tracking table should have {$column} column"
            );
        }
    }
}
