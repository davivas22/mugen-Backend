<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:50',
            'duration_days'     => 'required|integer|min:1',
            'start_date'        => 'required|date',
            'gym_days_per_week' => 'required|string',
            'challenge_mode'    => 'required|string',
            'cover_image'       => 'nullable|image|max:5120',
        ]);

        $imagePath = null;
        if ($request->hasFile('cover_image')) {
            $imagePath = $request->file('cover_image')->store('challenges', 'public');
        }

        $challenge = Challenge::create([
            'user_id'           => auth()->id(),
            'name'              => $request->name,
            'invite_code'       => Challenge::generateInviteCode(),
            'cover_image'       => $imagePath,
            'duration_days'     => $request->duration_days,
            'start_date'        => $request->start_date,
            'gym_days_per_week' => $request->gym_days_per_week,
            'challenge_mode'    => $request->challenge_mode,
            'use_location'      => $request->use_location === '1',
            'meeting_point'     => $request->meeting_point ?? '',
            'use_camera'        => $request->use_camera === '1',
        ]);

        // El creador también es miembro
        $challenge->members()->attach(auth()->id(), ['joined_at' => now()]);

        return response()->json(['challenge' => $challenge], 201);
    }

    public function index()
    {
        $challenges = Challenge::with('user:id,name')
            ->withCount('members')
            ->latest()
            ->get();

        return response()->json(['challenges' => $challenges]);
    }

    // Buscar desafío por código (público, sin auth)
    public function findByCode(string $code)
    {
        $challenge = Challenge::with('user:id,name')
            ->withCount('members')
            ->where('invite_code', strtoupper($code))
            ->firstOrFail();

        return response()->json(['challenge' => $challenge]);
    }

    // Desafíos del usuario autenticado (donde es miembro)
    public function mine(Request $request)
    {
        $user = $request->user();

        $challenges = Challenge::whereHas('members', fn($q) => $q->where('users.id', $user->id))
            ->with('user:id,name')
            ->withCount('members')
            ->latest()
            ->get();

        return response()->json(['challenges' => $challenges]);
    }

    // Unirse al desafío (requiere auth)
    public function join(string $code)
    {
        $challenge = Challenge::where('invite_code', strtoupper($code))->firstOrFail();

        if ($challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Ya eres miembro de este desafío.'], 409);
        }

        $challenge->members()->attach(auth()->id(), ['joined_at' => now()]);

        return response()->json(['challenge' => $challenge->load('user:id,name')], 200);
    }
}
