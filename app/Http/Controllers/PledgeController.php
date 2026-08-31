<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Challenge;
use App\Models\MonthlyPledge;
use Illuminate\Http\Request;

class PledgeController extends Controller
{
    // GET /challenges/{id}/pledges
    public function index($challengeId)
    {
        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro.'], 403);
        }

        $month = now()->month;
        $year  = now()->year;

        $memberIds = $challenge->members()->pluck('users.id');

        $pledges = MonthlyPledge::with('user')
            ->whereIn('user_id', $memberIds)
            ->where('challenge_id', $challengeId)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $pledgedIds = $pledges->pluck('user_id')->toArray();

        $withPledge = $pledges->map(function ($p) use ($challengeId, $month, $year) {
            $currentDays = Attendance::where('user_id', $p->user_id)
                ->where('challenge_id', $challengeId)
                ->where('status', 'confirmed')
                ->whereMonth('attended_date', $month)
                ->whereYear('attended_date', $year)
                ->count();

            return [
                'user_id'     => $p->user_id,
                'name'        => $p->user->name,
                'avatar'      => $p->user->avatar,
                'target_days' => $p->target_days,
                'current_days'=> $currentDays,
                'fulfilled'   => $currentDays >= $p->target_days,
                'has_pledge'  => true,
            ];
        });

        $withoutPledge = $challenge->members()
            ->whereNotIn('users.id', $pledgedIds)
            ->get(['users.id', 'users.name', 'users.avatar'])
            ->map(fn($u) => [
                'user_id'     => $u->id,
                'name'        => $u->name,
                'avatar'      => $u->avatar,
                'target_days' => null,
                'current_days'=> null,
                'fulfilled'   => false,
                'has_pledge'  => false,
            ]);

        $myPledge = $pledges->firstWhere('user_id', auth()->id());

        return response()->json([
            'month'     => $month,
            'year'      => $year,
            'my_pledge' => $myPledge?->target_days,
            'pledges'   => $withPledge->concat($withoutPledge)->sortByDesc('current_days')->values(),
        ]);
    }

    // POST /challenges/{id}/pledges
    public function store(Request $request, $challengeId)
    {
        $request->validate(['target_days' => 'required|integer|min:1|max:31']);

        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro.'], 403);
        }

        $pledge = MonthlyPledge::updateOrCreate(
            [
                'user_id'      => auth()->id(),
                'challenge_id' => $challengeId,
                'month'        => now()->month,
                'year'         => now()->year,
            ],
            ['target_days' => $request->target_days]
        );

        return response()->json($pledge, 201);
    }
}
