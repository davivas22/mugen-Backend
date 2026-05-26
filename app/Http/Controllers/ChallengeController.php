<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Decode JSON string from FormData so the model 'array' cast doesn't double-encode
        $gymDays = json_decode($request->gym_days_per_week, true);

        $challenge = Challenge::create([
            'user_id'           => auth()->id(),
            'name'              => $request->name,
            'invite_code'       => Challenge::generateInviteCode(),
            'cover_image'       => $imagePath,
            'duration_days'     => $request->duration_days,
            'start_date'        => $request->start_date,
            'gym_days_per_week' => is_array($gymDays) ? $gymDays : [],
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

    public function leaderboard($id)
    {
        $period = request('period', 'semana');

        $dateFrom = match ($period) {
            'semana' => now()->startOfWeek(),
            'mes'    => now()->startOfMonth(),
            'año'    => now()->startOfYear(),
            default  => now()->startOfWeek(),
        };

        $participants = DB::table('challenge_members')
            ->join('users', 'users.id', '=', 'challenge_members.user_id')
            ->leftJoin('workouts', function ($join) use ($id, $dateFrom) {
                $join->on('workouts.user_id', '=', 'users.id')
                     ->where('workouts.challenge_id', '=', $id)
                     ->where('workouts.created_at', '>=', $dateFrom);
            })
            ->where('challenge_members.challenge_id', $id)
            ->select(
                'users.id',
                'users.name as username',
                'users.avatar',
                DB::raw('COALESCE(COUNT(workouts.id), 0) as sessions'),
                DB::raw('COALESCE(COUNT(workouts.id) * 10 + SUM(workouts.reps), 0) as points'),
                DB::raw('COALESCE(SUM(workouts.reps), 0) as total_reps')
            )
            ->groupBy('users.id', 'users.name', 'users.avatar')
            ->orderByDesc('points')
            ->get()
            ->map(fn ($item, $i) => [
                'id'         => $item->id,
                'username'   => $item->username,
                'avatar_url' => $item->avatar,
                'rank'       => $i + 1,
                'points'     => (int) $item->points,
                'sessions'   => (int) $item->sessions,
                'total_reps' => (int) $item->total_reps,
            ]);

        $challenge = Challenge::find($id);

        return response()->json([
            'challenge'    => [
                'name'        => $challenge->name ?? '',
                'invite_code' => $challenge->invite_code ?? '',
                'user_id'     => $challenge->user_id ?? null,
            ],
            'participants' => $participants,
        ]);
    }

    public function update(Request $request, $id)
    {
        $challenge = Challenge::findOrFail($id);

        if ($challenge->user_id !== auth()->id()) {
            return response()->json(['message' => 'No tienes permiso para editar esta sala.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $challenge->update(['name' => $request->name]);

        return response()->json(['challenge' => $challenge->fresh()]);
    }

    public function destroy($id)
    {
        $challenge = Challenge::findOrFail($id);

        if ($challenge->user_id !== auth()->id()) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta sala.'], 403);
        }

        $challenge->members()->detach();
        $challenge->delete();

        return response()->json(['message' => 'Sala eliminada correctamente.']);
    }
}
