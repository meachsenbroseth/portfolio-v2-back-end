<?php

namespace App\Http\Controllers;

use App\Models\Experience;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $experience = Experience::paginate(10);
        return response()->json($experience, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'title' => 'required',
                'date' => 'required',
                'desc' => 'required',
            ]);

            Experience::create($data);

            return response()->json(['message' => 'Education create successfully']);
        } catch (Exception $e) {
            return response()->json(['massage' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $experience = Experience::findOrFail($id);
            return response()->json($experience, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Education not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json(['massage' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $experience = Experience::findOrFail($id);
        try {
            $data = $request->validate([
                'title' => 'required',
                'date' => 'required',
                'desc' => 'required',
            ]);

            $experience->update($data);

            return response()->json(['message' => 'Experience update successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Education not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json(['massage' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $experience = Experience::findOrFail($id);
        try {
            $experience->delete();
            return response()->json(['message' => 'Education delete successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Education not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json(['massage' => $e->getMessage()], 500);
        }
    }
}
