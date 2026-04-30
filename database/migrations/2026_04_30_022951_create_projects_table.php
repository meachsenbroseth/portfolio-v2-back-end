<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->date('date');
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->enum('status', ['completed', 'in_progress']);
            $table->text('desc')->nullable();
            $table->json('technologies')->nullable();
            $table->string('live_demo')->nullable();
            $table->string('github_link')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
