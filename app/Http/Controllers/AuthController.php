<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('mugen-app')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        $token = $user->createToken('mugen-app')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // POST /auth/google
    public function googleLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->id_token,
        ]);

        if (!$response->ok()) {
            return response()->json(['message' => 'Token de Google inválido.'], 401);
        }

        $g = $response->json();

        if (empty($g['email'])) {
            return response()->json(['message' => 'No se pudo obtener el email de Google.'], 401);
        }

        $user = User::where('google_id', $g['sub'])
            ->orWhere('email', $g['email'])
            ->first();

        if ($user) {
            // Vincular google_id si aún no está
            if (!$user->google_id) {
                $user->update(['google_id' => $g['sub']]);
            }
        } else {
            $baseUsername = Str::slug($g['name'] ?? explode('@', $g['email'])[0]);
            $username     = $baseUsername;
            $i            = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . $i++;
            }

            $user = User::create([
                'name'      => $g['name'] ?? $g['email'],
                'email'     => $g['email'],
                'google_id' => $g['sub'],
                'username'  => $username,
                'avatar'    => $g['picture'] ?? null,
                'password'  => Hash::make(Str::random(32)),
            ]);
        }

        $token = $user->createToken('google-login')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }
}
