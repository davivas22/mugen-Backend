<?php

namespace App\Http\Controllers;

use App\Models\UserBadge;
use App\Services\BadgeService;

class BadgeController extends Controller
{
    // GET /user/badges
    public function index()
    {
        $badges = UserBadge::where('user_id', auth()->id())
            ->orderByDesc('unlocked_at')
            ->get()
            ->map(fn($b) => [
                'key'          => $b->badge_key,
                'name'         => BadgeService::badgeInfo($b->badge_key)['name'],
                'icon'         => BadgeService::badgeInfo($b->badge_key)['icon'],
                'description'  => BadgeService::badgeInfo($b->badge_key)['description'],
                'unlocked_at'  => $b->unlocked_at->toDateString(),
                'challenge_id' => $b->challenge_id,
            ]);

        // También devolver los que aún no tiene para mostrarlos como bloqueados
        $earned = $badges->pluck('key')->all();
        $all = collect(BadgeService::BADGES)->map(fn($info, $key) => [
            'key'         => $key,
            'name'        => $info['name'],
            'icon'        => $info['icon'],
            'description' => $info['description'],
            'earned'      => in_array($key, $earned),
            'unlocked_at' => $badges->firstWhere('key', $key)['unlocked_at'] ?? null,
        ])->values();

        return response()->json(['badges' => $all]);
    }
}
