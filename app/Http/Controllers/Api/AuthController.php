<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'nip' => ['required', 'string', 'max:18'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('unit')->where('nip', preg_replace('/\D/', '', $credentials['nip']))->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'NIP atau password tidak sesuai.'], 422);
        }

        $plainToken = Str::random(80);
        $user->forceFill(['api_token' => hash('sha256', $plainToken)])->save();

        return response()->json([
            'token' => $plainToken,
            'user' => $this->userData($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userData($request->attributes->get('authUser')->load('unit'))]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
        ]);

        $user = $request->attributes->get('authUser');
        $user->forceFill(['password' => $validated['password']])->save();

        return response()->json(['message' => 'Password berhasil diperbarui.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('authUser')->forceFill(['api_token' => null])->save();

        return response()->json(['message' => 'Anda telah keluar.']);
    }

    private function userData(User $user): array
{
    return [
        'id' => $user->id,
        'name' => $user->name,
        'nip' => $user->nip,
        'email' => $user->email,
        'role' => $user->role,
        'position' => $user->position,
        'unit' => $user->unit?->name,
        'unit_id' => $user->unit_id,
        'is_active' => (bool) $user->is_active,
    ];
}
}
