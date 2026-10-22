<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `projects.slug` column already has a unique index, which fully
     * covers lookups by slug. The extra non-unique index is redundant.
     */
    public function up(): void
    {
        if (Schema::hasIndex('projects', 'projects_slug_index')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropIndex('projects_slug_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('projects', 'projects_slug_index')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->index('slug');
            });
        }
    }
};
