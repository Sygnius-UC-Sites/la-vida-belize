<?php

namespace Tests\Feature;

use Tests\TestCase;

class MySqlMigrationCompatibilityTest extends TestCase
{
    public function test_base_content_seed_uses_numeric_values_for_boolean_columns(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_06_10_100000_seed_base_demo_content.php'
        ));

        $this->assertIsString($migration);
        $this->assertDoesNotMatchRegularExpression(
            "/'accepts_pets'\\s*=>\\s*'(?:true|false)'/",
            $migration
        );
    }
}
