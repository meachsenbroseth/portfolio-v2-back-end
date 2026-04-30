<?php

namespace App\Http\Controllers;

use App\Models\Education;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class EducationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $education = Education::paginate(10);

        return response()->json($education, 200);
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
                'institution' => 'required'
            ]);

            Education::create($data);

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
            $education = Education::findOrFail($id);
            return response()->json($education, 200);
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
        $education = Education::findOrFail($id);
        try {
            $data = $request->validate([
                'title' => 'required',
                'date' => 'required',
                'desc' => 'required',
                'institution' => 'required'
            ]);

            $education->update($data);

            return response()->json(['message' => 'Education create successfully']);
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
        $education = Education::findOrFail($id);
        try {
            $education->delete();
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
