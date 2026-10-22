<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $project = Project::paginate(10);

        return response()->json($project, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'title'       => 'required|string|max:255',
                'date'        => 'nullable|string|max:10',
                'status'      => 'nullable|in:in_progress,completed',
                'desc'        => 'nullable|string',
                'technologies' => 'nullable|array',
                'technologies.*' => 'string|max:50',
                'live_demo'    => 'nullable|url|max:255',
                'github_link'  => 'nullable|url|max:255',
                'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'gallery'     => 'nullable|array',
                'gallery.*'   => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            // `projects.date` is NOT NULL in the database. The admin form
            // always sends a date, but an API client that omits it would
            // otherwise get a 500 from the database instead of a project.
            if (empty($data['date'])) {
                $data['date'] = now()->toDateString();
            }

            // Auto-generate a collision-safe slug
            $data['slug'] = $this->generateUniqueSlug($data['title']);

            // Store cover image
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')
                    ->store('projects/covers', 'public');
            }

            // Store gallery images
            if ($request->hasFile('gallery')) {
                $data['gallery'] = collect($request->file('gallery'))
                    ->map(fn($file) => $file->store('projects/gallery', 'public'))
                    ->values()
                    ->toArray();
            }

            $project = Project::create($data);

            return response()->json([
                'message' => 'Project created successfully',
                'data'    => $project,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($slug)
    {
        try {
            $project = Project::where('slug', $slug)->firstOrFail();
            return response()->json($project, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Project not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $project = Project::findOrFail($id);

            $data = $request->validate([
                'title'          => 'sometimes|string|max:255',
                'date'           => 'nullable|string|max:10',
                'status'         => 'nullable|in:in_progress,completed',
                'desc'    => 'nullable|string',
                'technologies'   => 'nullable|array',
                'technologies.*' => 'string|max:50',
                'live_demo'      => 'nullable|url|max:255',
                'github_link'     => 'nullable|url|max:255',
                'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'gallery'        => 'nullable|array',
                'gallery.*'      => 'image|mimes:jpg,jpeg,png,webp|max:2048',
                'existing_gallery'   => 'nullable|array',
                'existing_gallery.*' => 'string',
                'remove_image'   => 'nullable|boolean', // Add this field
            ]);

            // An empty date would be cast to an invalid date by the database.
            if (array_key_exists('date', $data) && $data['date'] === '') {
                unset($data['date']);
            }

            // ── Cover image ──────────────────────────────
            // Check if image should be removed
            if ($request->input('remove_image') == '1' || $request->input('remove_image') === true) {
                if ($project->image) {
                    Storage::disk('public')->delete($project->image);
                }
                $data['image'] = null;
            }

            // Upload new cover image
            if ($request->hasFile('image')) {
                // Delete old cover from storage
                if ($project->image) {
                    Storage::disk('public')->delete($project->image);
                }
                $data['image'] = $request->file('image')
                    ->store('projects/covers', 'public');
            }

            // ── Gallery ──────────────────────────────────
            $keptPaths = $request->input('existing_gallery', []);
            $hasNewFiles = $request->hasFile('gallery');

            if ($hasNewFiles || $request->has('existing_gallery')) {
                // Delete gallery images that were removed by the user (batch delete)
                $currentGallery = $project->gallery ?? [];
                $removedPaths = array_values(array_diff($currentGallery, $keptPaths));

                if (!empty($removedPaths)) {
                    Storage::disk('public')->delete($removedPaths);
                }

                // Store new gallery images
                $newPaths = [];
                if ($hasNewFiles) {
                    foreach ($request->file('gallery') as $file) {
                        $newPaths[] = $file->store('projects/gallery', 'public');
                    }
                }

                $data['gallery'] = array_merge($keptPaths, $newPaths);
            }

            // ── Regenerate slug if title changed ──────────
            if (isset($data['title']) && $data['title'] !== $project->title) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], (int) $project->id);
            }

            $project->update($data);

            // Add full image URLs to response
            $project->image_url = $project->image ? Storage::url($project->image) : null;
            $project->gallery_urls = $project->gallery ? array_map(function($item) {
                return Storage::url($item);
            }, $project->gallery) : [];

            return response()->json([
                'message' => 'Project updated successfully',
                'data'    => $project,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Project not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Project update error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $project = Project::findOrFail($id);

            // Delete associated images in a single batch request
            $filesToDelete = array_values(array_filter(
                array_merge([$project->image], $project->gallery ?? [])
            ));

            if (!empty($filesToDelete)) {
                Storage::disk('public')->delete($filesToDelete);
            }

            $project->delete();

            return response()->json([
                'message' => 'Project deleted successfully'
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Project not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build a slug that is guaranteed not to collide with an existing project.
     *
     * Uses the base slug when it is free, otherwise appends the next free
     * numeric suffix (e.g. "my-app", "my-app-1", "my-app-2", ...).
     */
    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'project';
        }

        $query = Project::query()->where(function ($q) use ($base) {
            $q->where('slug', $base)
                ->orWhere('slug', 'LIKE', $base . '-%');
        });

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        $slugs = $query->pluck('slug');

        // The base slug is free — use it as-is.
        if (! $slugs->contains($base)) {
            return $base;
        }

        // Otherwise find the highest numeric suffix in use and increment it.
        $maxSuffix = 0;
        $pattern = '/^' . preg_quote($base, '/') . '-(\d+)$/';

        foreach ($slugs as $candidate) {
            if (preg_match($pattern, (string) $candidate, $matches)) {
                $maxSuffix = max($maxSuffix, (int) $matches[1]);
            }
        }

        return $base . '-' . ($maxSuffix + 1);
    }
}
