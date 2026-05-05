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
                'category'    => 'nullable|string|max:100',
                'date'        => 'nullable|string|max:10',
                'status'      => 'nullable|in:in_progress,completed,archived',
                'description' => 'nullable|string',
                'technologies' => 'nullable|array',
                'technologies.*' => 'string|max:50',
                'live_demo'    => 'nullable|url|max:255',
                'github_link'  => 'nullable|url|max:255',
                'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'gallery'     => 'nullable|array',
                'gallery.*'   => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            // Auto-generate slug
            $slug = Str::slug($data['title']);
            $count = Project::where('slug', 'LIKE', "{$slug}%")->count();
            $data['slug'] = $count ? "{$slug}-{$count}" : $slug;

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

            // Handle technologies if sent as JSON string
            if (isset($data['technologies']) && is_string($data['technologies'])) {
                $data['technologies'] = json_decode($data['technologies'], true) ?? [];
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
                'category'       => 'nullable|string|max:100',
                'date'           => 'nullable|string|max:10',
                'status'         => 'nullable|in:in_progress,completed,archived',
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

            // ── Cover image ──────────────────────────────
            // Check if image should be removed
            if ($request->input('remove_image') == '1' || $request->input('remove_image') === true) {
                if ($project->image && Storage::disk('public')->exists($project->image)) {
                    Storage::disk('public')->delete($project->image);
                }
                $data['image'] = null;
            }

            // Upload new cover image
            if ($request->hasFile('image')) {
                // Delete old cover from storage
                if ($project->image && Storage::disk('public')->exists($project->image)) {
                    Storage::disk('public')->delete($project->image);
                }
                $data['image'] = $request->file('image')
                    ->store('projects/covers', 'public');
            }

            // ── Gallery ──────────────────────────────────
            $keptPaths = $request->input('existing_gallery', []);
            $hasNewFiles = $request->hasFile('gallery');

            if ($hasNewFiles || $request->has('existing_gallery')) {
                // Delete gallery images that were removed by the user
                $currentGallery = $project->gallery ?? [];
                if (is_string($currentGallery)) {
                    $currentGallery = json_decode($currentGallery, true) ?? [];
                }

                $removedPaths = array_diff($currentGallery, $keptPaths);
                if (!empty($removedPaths)) {
                    foreach ($removedPaths as $path) {
                        if (Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                        }
                    }
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

            // ── Technologies string guard ─────────────────
            if (isset($data['technologies']) && is_string($data['technologies'])) {
                $data['technologies'] = json_decode($data['technologies'], true) ?? [];
            }

            // ── Regenerate slug if title changed ──────────
            if (isset($data['title']) && $data['title'] !== $project->title) {
                $slug = Str::slug($data['title']);
                $count = Project::where('slug', 'LIKE', "{$slug}%")
                    ->where('id', '!=', $project->id)
                    ->count();
                $data['slug'] = $count ? "{$slug}-{$count}" : $slug;
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

            // Delete associated images
            if ($project->image && Storage::disk('public')->exists($project->image)) {
                Storage::disk('public')->delete($project->image);
            }

            $gallery = $project->gallery ?? [];
            if (is_string($gallery)) {
                $gallery = json_decode($gallery, true) ?? [];
            }

            foreach ($gallery as $image) {
                if (Storage::disk('public')->exists($image)) {
                    Storage::disk('public')->delete($image);
                }
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
}
