<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\SocialBet;
use Illuminate\Http\Request;

class SocialBetController extends Controller
{
    // GET /challenges/{id}/social-bets
    public function index($challengeId)
    {
        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro.'], 403);
        }

        $bets = SocialBet::with(['creator:id,name,avatar', 'targetUser:id,name,avatar', 'resolvedBy:id,name'])
            ->where('challenge_id', $challengeId)
            ->latest()
            ->get()
            ->map(fn($b) => [
                'id'             => $b->id,
                'bet_type'       => $b->bet_type,
                'description'    => $b->description,
                'stake'          => $b->stake,
                'status'         => $b->status,
                'outcome'        => $b->outcome,
                'resolved_at'    => $b->resolved_at?->toISOString(),
                'creator'        => [
                    'id'     => $b->creator->id,
                    'name'   => $b->creator->name,
                    'avatar' => $b->creator->avatar,
                ],
                'target_user'    => [
                    'id'     => $b->targetUser->id,
                    'name'   => $b->targetUser->name,
                    'avatar' => $b->targetUser->avatar,
                ],
                'resolved_by'    => $b->resolvedBy ? $b->resolvedBy->name : null,
                'is_mine'        => $b->creator_id === auth()->id(),
                'is_about_me'    => $b->target_user_id === auth()->id(),
            ]);

        return response()->json(['bets' => $bets]);
    }

    // POST /challenges/{id}/social-bets
    public function store(Request $request, $challengeId)
    {
        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->enable_bets) {
            return response()->json(['message' => 'Las apuestas no están habilitadas en esta sala.'], 403);
        }

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro.'], 403);
        }

        $request->validate([
            'target_user_id' => 'required|integer|exists:users,id',
            'bet_type'       => 'required|string|in:first_to_fail,first_to_quit,most_days,complete_all,custom',
            'description'    => 'nullable|string|max:200',
            'stake'          => 'nullable|string|max:150',
        ]);

        if ($request->target_user_id == auth()->id()) {
            return response()->json(['message' => 'No puedes apostar sobre ti mismo.'], 422);
        }

        if (!$challenge->members()->where('user_id', $request->target_user_id)->exists()) {
            return response()->json(['message' => 'El usuario objetivo no es miembro de esta sala.'], 422);
        }

        $bet = SocialBet::create([
            'challenge_id'   => $challengeId,
            'creator_id'     => auth()->id(),
            'target_user_id' => $request->target_user_id,
            'bet_type'       => $request->bet_type,
            'description'    => $request->description,
            'stake'          => $request->stake,
            'status'         => 'open',
        ]);

        $bet->load(['creator:id,name,avatar', 'targetUser:id,name,avatar']);

        return response()->json([
            'bet' => [
                'id'          => $bet->id,
                'bet_type'    => $bet->bet_type,
                'description' => $bet->description,
                'stake'       => $bet->stake,
                'status'      => $bet->status,
                'outcome'     => null,
                'resolved_at' => null,
                'creator'     => ['id' => $bet->creator->id, 'name' => $bet->creator->name, 'avatar' => $bet->creator->avatar],
                'target_user' => ['id' => $bet->targetUser->id, 'name' => $bet->targetUser->name, 'avatar' => $bet->targetUser->avatar],
                'resolved_by' => null,
                'is_mine'     => true,
                'is_about_me' => $bet->target_user_id === auth()->id(),
            ],
        ], 201);
    }

    // POST /social-bets/{id}/resolve  — solo el creador de la sala puede resolver
    public function resolve(Request $request, $betId)
    {
        $bet = SocialBet::with('challenge')->findOrFail($betId);

        $challenge = $bet->challenge;

        // Solo el creador de la sala puede marcar una apuesta como resuelta
        if ($challenge->user_id !== auth()->id()) {
            return response()->json(['message' => 'Solo el creador de la sala puede resolver apuestas.'], 403);
        }

        if ($bet->status === 'resolved') {
            return response()->json(['message' => 'Esta apuesta ya fue resuelta.'], 409);
        }

        $request->validate([
            'outcome' => 'required|boolean',
        ]);

        $bet->update([
            'status'      => 'resolved',
            'outcome'     => $request->outcome,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        return response()->json(['message' => 'Apuesta resuelta.', 'bet' => $bet->fresh()]);
    }

    // DELETE /social-bets/{id}  — solo el creador de la apuesta o creador de la sala
    public function destroy($betId)
    {
        $bet = SocialBet::with('challenge')->findOrFail($betId);

        $isCreator     = $bet->creator_id === auth()->id();
        $isRoomCreator = $bet->challenge->user_id === auth()->id();

        if (!$isCreator && !$isRoomCreator) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta apuesta.'], 403);
        }

        $bet->delete();

        return response()->json(['message' => 'Apuesta eliminada.']);
    }
}
