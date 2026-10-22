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
     * Build a fake uploaded image.
     *
     * `UploadedFile::fake()->image()` requires the GD extension, which is not
     * guaranteed on CI or developer machines. `fake()->create()` produces an
     * equivalent upload with an explicit mime type and no GD dependency, so
     * the storage assertions below always run.
     */
    protected function fakeImage(string $name = 'cover.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'image/jpeg');
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
        Storage::fake('public');

        $response = $this->actingAs($this->adminUser())->postJson('/api/projects', [
            'title'   => 'With Images',
            'status'  => 'in_progress',
            'image'   => $this->fakeImage('cover.jpg'),
            'gallery' => [$this->fakeImage('one.jpg')],
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
        Storage::fake('public');

        $user = $this->adminUser();

        $created = $this->actingAs($user)->postJson('/api/projects', [
            'title'   => 'Gallery',
            'status'  => 'completed',
            'gallery' => [
                $this->fakeImage('one.jpg'),
                $this->fakeImage('two.jpg'),
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
        Storage::fake('public');

        $user = $this->adminUser();

        $this->actingAs($user)->postJson('/api/projects', [
            'title'  => 'Doomed',
            'status' => 'completed',
            'image'  => $this->fakeImage('cover.jpg'),
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
