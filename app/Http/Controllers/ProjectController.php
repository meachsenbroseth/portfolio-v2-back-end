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
                'technologies' => 'nullable|array',           // expect array from frontend
                'technologies.*' => 'string|max:50',
                'live_url'    => 'nullable|url|max:255',
                'github_url'  => 'nullable|url|max:255',
                'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'gallery'     => 'nullable|array',           // fixed: was 'gallery.*'
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

            // JSON-encode technologies if it came as a plain string (e.g. from FormData)
            // FormData sends arrays as repeated keys — Laravel parses them as array already
            // But if sent as JSON string, decode it first
            if (isset($data['technologies']) && is_string($data['technologies'])) {
                $data['technologies'] = json_decode($data['technologies'], true) ?? [];
            }

            $project = Project::create($data);

            return response()->json([
                'message' => 'Project created successfully',
                'data'    => $project,
            ], 201);
        } catch (ValidationException $e) {
            // Return validation errors properly, not as 500
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
    public function show(string $id)
    {
        try {
            $project = Project::findOrFail($id);
            return response()->json($project, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
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
                'description'    => 'nullable|string',
                'technologies'   => 'nullable|array',
                'technologies.*' => 'string|max:50',
                'live_url'       => 'nullable|url|max:255',
                'github_url'     => 'nullable|url|max:255',

                // New cover image (optional)
                'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

                // New gallery images to ADD (optional)
                'gallery'        => 'nullable|array',
                'gallery.*'      => 'image|mimes:jpg,jpeg,png,webp|max:2048',

                // Existing gallery paths the frontend wants to KEEP
                'existing_gallery'   => 'nullable|array',
                'existing_gallery.*' => 'string',
            ]);

            // ── Cover image ──────────────────────────────
            if ($request->hasFile('image')) {
                // Delete old cover from storage
                if ($project->image) {
                    Storage::disk('public')->delete($project->image);
                }
                $data['image'] = $request->file('image')
                    ->store('projects/covers', 'public');
            }

            // ── Gallery ──────────────────────────────────
            $keptPaths  = $request->input('existing_gallery', []);
            $hasNewFiles = $request->hasFile('gallery');

            if ($hasNewFiles || $request->has('existing_gallery')) {
                // Delete gallery images that were removed by the user
                $removedPaths = array_diff($project->gallery ?? [], $keptPaths);
                if ($removedPaths) {
                    Storage::disk('public')->delete(array_values($removedPaths));
                }

                // Store new gallery images
                $newPaths = [];
                if ($hasNewFiles) {
                    $newPaths = collect($request->file('gallery'))
                        ->map(fn($file) => $file->store('projects/gallery', 'public'))
                        ->values()
                        ->toArray();
                }

                $data['gallery'] = array_merge($keptPaths, $newPaths);
            }

            // ── Technologies string guard ─────────────────
            if (isset($data['technologies']) && is_string($data['technologies'])) {
                $data['technologies'] = json_decode($data['technologies'], true) ?? [];
            }

            // ── Regenerate slug if title changed ──────────
            if (isset($data['title'])) {
                $slug = Str::slug($data['title']);
                $count = Project::where('slug', 'LIKE', "{$slug}%")->count();
                $data['slug'] = $count ? "{$slug}-{$count}" : $slug;
            }

            $project->update($data);

            return response()->json([
                'message' => 'Project updated successfully',
                'data'    => $project->fresh(),
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
            return response()->json([
                'message' => 'Server error',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $project = Project::findOrFail($id);
        try {
            $project->delete();
            return response()->json(['message' => 'Education delete successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Education not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json(['massage' => 'Server error'], 500);
        }
    }
}
