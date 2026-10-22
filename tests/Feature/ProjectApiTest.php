<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * `UploadedFile::fake()->image()` needs the GD extension. The production
     * image ships with GD, but local/CI machines may not, so the image
     * related assertions are skipped when it is unavailable.
     */
    protected function skipWithoutGd(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The GD extension is required to fake image uploads.');
        }
    }

    public function test_show_returns_the_project_for_a_known_slug(): void
    {
        Project::create([
            'slug'   => 'my-app',
            'title'  => 'My App',
            'date'   => '2026-01-01',
            'status' => 'completed',
            'desc'   => 'A description',
        ]);

        $this->getJson('/api/projects/my-app')
            ->assertOk()
            ->assertJsonPath('slug', 'my-app')
            ->assertJsonPath('title', 'My App');
    }

    public function test_show_returns_404_for_an_unknown_slug(): void
    {
        $this->getJson('/api/projects/does-not-exist')
            ->assertStatus(404)
            ->assertJson(['message' => 'Project not found']);
    }

    public function test_index_is_paginated(): void
    {
        Project::factory()->count(12)->create();

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 12);
    }

    public function test_store_generates_a_collision_free_slug(): void
    {
        Storage::fake('public');

        $user = $this->adminUser();

        $first = $this->actingAs($user)->postJson('/api/projects', [
            'title'  => 'My App',
            'status' => 'completed',
        ]);
        $first->assertCreated()->assertJsonPath('data.slug', 'my-app');

        $second = $this->actingAs($user)->postJson('/api/projects', [
            'title'  => 'My App',
            'status' => 'completed',
        ]);
        $second->assertCreated()->assertJsonPath('data.slug', 'my-app-1');

        $third = $this->actingAs($user)->postJson('/api/projects', [
            'title'  => 'My App',
            'status' => 'completed',
        ]);
        $third->assertCreated()->assertJsonPath('data.slug', 'my-app-2');
    }

    public function test_store_stores_the_cover_image_and_gallery(): void
    {
        $this->skipWithoutGd();

        Storage::fake('public');

        $response = $this->actingAs($this->adminUser())->postJson('/api/projects', [
            'title'   => 'With Images',
            'status'  => 'in_progress',
            'image'   => UploadedFile::fake()->image('cover.jpg'),
            'gallery' => [UploadedFile::fake()->image('one.jpg')],
        ]);

        $response->assertCreated();

        $project = Project::where('slug', 'with-images')->firstOrFail();

        $this->assertNotNull($project->image);
        Storage::disk('public')->assertExists($project->image);

        $this->assertCount(1, $project->gallery);
        Storage::disk('public')->assertExists($project->gallery[0]);
    }

    public function test_store_rejects_the_archived_status(): void
    {
        $response = $this->actingAs($this->adminUser())->postJson('/api/projects', [
            'title'  => 'Bad Status',
            'status' => 'archived',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_update_keeps_existing_gallery_images_and_deletes_removed_ones(): void
    {
        $this->skipWithoutGd();

        Storage::fake('public');

        $user = $this->adminUser();

        $created = $this->actingAs($user)->postJson('/api/projects', [
            'title'   => 'Gallery',
            'status'  => 'completed',
            'gallery' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ]);
        $created->assertCreated();

        $project = Project::where('slug', 'gallery')->firstOrFail();
        $keep = $project->gallery[0];
        $drop = $project->gallery[1];

        $response = $this->actingAs($user)->putJson('/api/projects/'.$project->id, [
            'existing_gallery' => [$keep],
        ]);

        $response->assertOk();

        $project->refresh();

        $this->assertSame([$keep], $project->gallery);
        Storage::disk('public')->assertExists($keep);
        Storage::disk('public')->assertMissing($drop);
    }

    public function test_updating_the_title_regenerates_the_slug_without_colliding(): void
    {
        $user = $this->adminUser();

        Project::create(['slug' => 'taken', 'title' => 'Taken', 'date' => '2026-01-01', 'status' => 'completed']);
        $other = Project::create(['slug' => 'other', 'title' => 'Other', 'date' => '2026-01-01', 'status' => 'completed']);

        $this->actingAs($user)->putJson('/api/projects/'.$other->id, ['title' => 'Taken'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'taken-1');
    }

    public function test_destroy_deletes_the_project_and_its_files(): void
    {
        $this->skipWithoutGd();

        Storage::fake('public');

        $user = $this->adminUser();

        $this->actingAs($user)->postJson('/api/projects', [
            'title'  => 'Doomed',
            'status' => 'completed',
            'image'  => UploadedFile::fake()->image('cover.jpg'),
        ])->assertCreated();

        $project = Project::where('slug', 'doomed')->firstOrFail();
        $image = $project->image;

        $this->actingAs($user)->deleteJson('/api/projects/'.$project->id)->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        Storage::disk('public')->assertMissing($image);
    }

    public function test_non_admin_users_cannot_create_projects(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson('/api/projects', [
            'title' => 'Nope',
        ]);

        $response->assertStatus(403);
    }
}
