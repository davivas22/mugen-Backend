<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\RoomJoinRequest;
use App\Services\BadgeService;
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
            'gym_lat'           => $request->gym_lat ?? null,
            'gym_lng'           => $request->gym_lng ?? null,
            'is_private'        => $request->is_private === '1',
        ]);

        // El creador también es miembro
        $challenge->members()->attach(auth()->id(), ['joined_at' => now()]);

        BadgeService::checkFounderBadge(auth()->id(), $challenge->id);

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

        if ($challenge->is_private && $challenge->user_id !== auth()->id()) {
            $existing = RoomJoinRequest::where('challenge_id', $challenge->id)
                ->where('user_id', auth()->id())
                ->first();

            if ($existing) {
                if ($existing->status === 'pending') {
                    return response()->json(['message' => 'Ya tienes una solicitud pendiente.', 'pending' => true]);
                }
                if ($existing->status === 'rejected') {
                    $existing->update(['status' => 'pending']);
                    return response()->json(['pending' => true], 201);
                }
            } else {
                RoomJoinRequest::create([
                    'challenge_id' => $challenge->id,
                    'user_id'      => auth()->id(),
                    'status'       => 'pending',
                ]);
            }

            return response()->json(['pending' => true], 201);
        }

        $challenge->members()->attach(auth()->id(), ['joined_at' => now()]);

        return response()->json(['challenge' => $challenge->load('user:id,name'), 'pending' => false], 200);
    }

    // GET /challenges/{id}/requests (creador)
    public function joinRequests(Request $request, $id)
    {
        $challenge = Challenge::findOrFail($id);

        if ($challenge->user_id !== auth()->id()) {
            return response()->json(['message' => 'Solo el creador puede ver las solicitudes.'], 403);
        }

        $requests = RoomJoinRequest::where('challenge_id', $id)
            ->where('status', 'pending')
            ->with('user:id,name,avatar')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['requests' => $requests]);
    }

    // POST /challenges/{id}/requests/{requestId}/approve
    public function approveRequest(Request $request, $id, $requestId)
    {
        $challenge = Challenge::findOrFail($id);

        if ($challenge->user_id !== auth()->id()) {
            return response()->json(['message' => 'Solo el creador puede aprobar solicitudes.'], 403);
        }

        $joinRequest = RoomJoinRequest::where('challenge_id', $id)->findOrFail($requestId);

        if (!$challenge->members()->where('user_id', $joinRequest->user_id)->exists()) {
            $challenge->members()->attach($joinRequest->user_id, ['joined_at' => now()]);
        }
        $joinRequest->update(['status' => 'approved']);

        return response()->json(['message' => 'Solicitud aprobada.']);
    }

    // POST /challenges/{id}/requests/{requestId}/reject
    public function rejectRequest(Request $request, $id, $requestId)
    {
        $challenge = Challenge::findOrFail($id);

        if ($challenge->user_id !== auth()->id()) {
            return response()->json(['message' => 'Solo el creador puede rechazar solicitudes.'], 403);
        }

        $joinRequest = RoomJoinRequest::where('challenge_id', $id)->findOrFail($requestId);
        $joinRequest->update(['status' => 'rejected']);

        return response()->json(['message' => 'Solicitud rechazada.']);
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
            ->leftJoinSub(
                DB::table('workouts')
                    ->where('challenge_id', $id)
                    ->where('created_at', '>=', $dateFrom)
                    ->select('user_id',
                        DB::raw('COUNT(*) as sessions'),
                        DB::raw('COALESCE(SUM(reps), 0) as total_reps'))
                    ->groupBy('user_id'),
                'w', 'w.user_id', '=', 'users.id'
            )
            ->leftJoinSub(
                DB::table('attendances')
                    ->where('challenge_id', $id)
                    ->where('attended_date', '>=', $dateFrom->toDateString())
                    ->select('user_id', DB::raw('COUNT(*) as attendance_count'))
                    ->groupBy('user_id'),
                'a', 'a.user_id', '=', 'users.id'
            )
            ->where('challenge_members.challenge_id', $id)
            ->select(
                'users.id',
                'users.name as username',
                'users.avatar',
                DB::raw('COALESCE(w.sessions, 0) as sessions'),
                DB::raw('COALESCE(w.total_reps, 0) as total_reps'),
                DB::raw('COALESCE(a.attendance_count, 0) as attendance_count'),
                DB::raw('(COALESCE(w.sessions, 0) * 10 + COALESCE(w.total_reps, 0) + COALESCE(a.attendance_count, 0) * 20) as points')
            )
            ->orderByDesc('points')
            ->get()
            ->map(fn ($item, $i) => [
                'id'               => $item->id,
                'username'         => $item->username,
                'avatar_url'       => $item->avatar,
                'rank'             => $i + 1,
                'points'           => (int) $item->points,
                'sessions'         => (int) $item->sessions,
                'total_reps'       => (int) $item->total_reps,
                'attendance_count' => (int) $item->attendance_count,
            ]);

        $challenge = Challenge::find($id);

        return response()->json([
            'challenge'    => [
                'name'              => $challenge->name ?? '',
                'invite_code'       => $challenge->invite_code ?? '',
                'user_id'           => $challenge->user_id ?? null,
                'cover_image'       => $challenge->cover_image ?? null,
                'start_date'        => $challenge->start_date?->toDateString(),
                'duration_days'     => (int) ($challenge->duration_days ?? 30),
                'is_private'        => (bool) ($challenge->is_private ?? false),
                'use_location'      => (bool) ($challenge->use_location ?? false),
                'use_camera'        => (bool) ($challenge->use_camera ?? false),
                'enable_bets'       => (bool) ($challenge->enable_bets ?? false),
                'gym_lat'           => $challenge->gym_lat ? (float) $challenge->gym_lat : null,
                'gym_lng'           => $challenge->gym_lng ? (float) $challenge->gym_lng : null,
                'gym_radius_meters' => (int) ($challenge->gym_radius_meters ?? 200),
            ],
            'participants' => $participants,
        ]);
    }

    public function update(Request $request, $id)
    {
        $challenge = Challenge::findOrFail($id);
        $user = $request->user();

        // Admin puede editar cualquier sala; el creador sólo la suya
        if (!$user->is_admin && $challenge->user_id !== $user->id) {
            return response()->json(['message' => 'No tienes permiso para editar esta sala.'], 403);
        }

        $request->validate([
            'name'           => 'sometimes|string|max:50',
            'duration_days'  => 'sometimes|integer|min:1',
            'start_date'     => 'sometimes|date',
            'challenge_mode' => 'sometimes|string|in:tracking,honor',
            'cover_image'    => 'nullable|image|max:5120',
            'enable_bets'    => 'sometimes|boolean',
        ]);

        $data = $request->only(['name', 'duration_days', 'start_date', 'challenge_mode']);

        if ($request->has('enable_bets')) {
            $data['enable_bets'] = filter_var($request->enable_bets, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('cover_image')) {
            // Delete old cover if exists
            if ($challenge->cover_image) {
                \Storage::disk('public')->delete($challenge->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('challenges', 'public');
        }

        $challenge->update($data);

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
