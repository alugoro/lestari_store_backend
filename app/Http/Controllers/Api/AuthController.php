<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Login user dan generate access + refresh token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // Cek user exist, password benar, dan akun aktif
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda tidak aktif. Hubungi administrator.'],
            ]);
        }

        // Hapus semua token lama user ini (optional - untuk single device login)
        // $user->tokens()->delete();

        // Generate Access Token (expired 30 menit)
        $accessToken = $user->createToken('access-token', ['*'], Carbon::now()->addMinutes(30))->plainTextToken;

        // Generate Refresh Token (expired 7 hari)
        $refreshToken = $user->createToken('refresh-token', ['refresh'], Carbon::now()->addDays(7))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                ],
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 1800, // 30 menit dalam detik
            ]
        ], 200);
    }

    /**
     * Refresh access token menggunakan refresh token
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        // Extract token dari Bearer format jika ada
        $refreshToken = $request->refresh_token;
        if (str_starts_with($refreshToken, 'Bearer ')) {
            $refreshToken = substr($refreshToken, 7);
        }

        // Cari token di database
        $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($refreshToken);

        if (!$tokenModel) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token tidak valid'
            ], 401);
        }

        // Cek apakah token sudah expired
        if ($tokenModel->expires_at && $tokenModel->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token sudah expired. Silakan login kembali.'
            ], 401);
        }

        // Cek apakah ini benar refresh token (bukan access token)
        if (!in_array('refresh', $tokenModel->abilities)) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid untuk refresh'
            ], 401);
        }

        $user = $tokenModel->tokenable;

        // Cek apakah user masih aktif
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif. Hubungi administrator.'
            ], 403);
        }

        $user->tokens()->where('name', 'access-token')->delete();

        $newAccessToken = $user->createToken('access-token', ['*'], Carbon::now()->addMinutes(30))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token berhasil di-refresh',
            'data' => [
                'access_token' => $newAccessToken,
                'token_type' => 'Bearer',
                'expires_in' => 1800,
            ]
        ], 200);
    }

    /**
     * Logout user (hapus semua token)
     */
    public function logout(Request $request)
    {
        // Hapus semua token user (access & refresh)
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ], 200);
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ]
        ], 200);
    }
}