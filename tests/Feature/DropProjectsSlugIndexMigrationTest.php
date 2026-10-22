<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropProjectsSlugIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The live database was created before the redundant `projects_slug_index`
     * was removed from the create migration, so the index still exists there.
     * Re-creating it here reproduces that exact starting state.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasIndex('projects', 'projects_slug_index')) {
            Schema::table('projects', function ($table) {
                $table->index('slug');
            });
        }
    }

    public function test_it_drops_the_redundant_slug_index(): void
    {
        $this->assertTrue(Schema::hasIndex('projects', 'projects_slug_index'));

        $migration = require database_path('migrations/2026_10_22_000000_drop_projects_slug_index.php');
        $migration->up();

        $this->assertFalse(Schema::hasIndex('projects', 'projects_slug_index'));

        // The unique index that actually covers slug lookups is untouched.
        $this->assertTrue(Schema::hasIndex('projects', 'projects_slug_unique'));
    }

    public function test_it_is_idempotent(): void
    {
        $migration = require database_path('migrations/2026_10_22_000000_drop_projects_slug_index.php');

        $migration->up();
        $migration->up();

        $this->assertFalse(Schema::hasIndex('projects', 'projects_slug_index'));
    }

    public function test_the_down_migration_restores_the_index(): void
    {
        $migration = require database_path('migrations/2026_10_22_000000_drop_projects_slug_index.php');

        $migration->up();
        $migration->down();

        $this->assertTrue(Schema::hasIndex('projects', 'projects_slug_index'));
    }
}
