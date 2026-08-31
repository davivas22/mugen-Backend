<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Challenge;
use App\Models\WeeklyCommitment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WeeklyCommitmentController extends Controller
{
    // GET /challenges/{id}/commitments
    public function index($challengeId)
    {
        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro.'], 403);
        }

        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $memberIds = $challenge->members()->pluck('users.id');

        $commitments = WeeklyCommitment::with('user')
            ->whereIn('user_id', $memberIds)
            ->where('challenge_id', $challengeId)
            ->where('week_start', $weekStart)
            ->get();

        $committedIds = $commitments->pluck('user_id')->toArray();

        // Para cada compromiso, calcular qué días ya cumplió esta semana
        $withCommitment = $commitments->map(function ($c) use ($challengeId, $weekStart) {
            $attendedDays = Attendance::where('user_id', $c->user_id)
                ->where('challenge_id', $challengeId)
                ->where('status', 'confirmed')
                ->whereBetween('attended_date', [$weekStart, now()->toDateString()])
                ->pluck('attended_date')
                ->map(fn($d) => Carbon::parse($d)->dayOfWeekIso - 1) // 0=Lun...6=Dom
                ->toArray();

            return [
                'user_id'        => $c->user_id,
                'name'           => $c->user->name,
                'avatar'         => $c->user->avatar,
                'committed_days' => $c->committed_days,
                'attended_days'  => $attendedDays,
                'has_commitment' => true,
            ];
        });

        $withoutCommitment = $challenge->members()
            ->whereNotIn('users.id', $committedIds)
            ->get(['users.id', 'users.name', 'users.avatar'])
            ->map(fn($u) => [
                'user_id'        => $u->id,
                'name'           => $u->name,
                'avatar'         => $u->avatar,
                'committed_days' => [],
                'attended_days'  => [],
                'has_commitment' => false,
            ]);

        $myCommitment = $commitments->firstWhere('user_id', auth()->id());

        return response()->json([
            'week_start'    => $weekStart,
            'my_commitment' => $myCommitment?->committed_days,
            'members'       => $withCommitment->concat($withoutCommitment)->values(),
        ]);
    }

    // POST /challenges/{id}/commitments
    public function store(Request $request, $challengeId)
    {
        $request->validate([
            'committed_days'   => 'required|array|min:1',
            'committed_days.*' => 'integer|min:0|max:6',
        ]);

        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro.'], 403);
        }

        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $commitment = WeeklyCommitment::updateOrCreate(
            [
                'user_id'      => auth()->id(),
                'challenge_id' => $challengeId,
                'week_start'   => $weekStart,
            ],
            ['committed_days' => $request->committed_days]
        );

        return response()->json($commitment, 201);
    }
}
