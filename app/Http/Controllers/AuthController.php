<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'email' => 'email|required',
            'password' => 'required|min:6'
        ]);

        User::create($data);

        return response()->json(['message' => 'User registered'], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'email|required',
            'password' => 'required|min:6'
        ]);

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the Sanctum personal access token issued at login so the
        // personal_access_tokens table does not accumulate orphaned rows.
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    /**
     * Deprecated alias for logout(), kept mounted on GET /api/logout.
     *
     * Logout moved from GET to POST (state-changing verbs must not be GET).
     * The frontend and the API deploy independently, so an old frontend build
     * that still calls GET /api/logout must keep working while the new
     * frontend rolls out. This handler performs exactly the same token
     * revocation as logout() and can be deleted once the frontend that uses
     * POST /api/logout is live in production.
     */
    public function legacyLogout(Request $request)
    {
        Log::warning('Deprecated GET /api/logout endpoint was used.');

        $response = $this->logout($request);

        $response->headers->set('Deprecation', 'true');
        $response->headers->set(
            'Warning',
            '299 - "GET /api/logout is deprecated, use POST /api/logout instead."'
        );

        return $response;
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
