<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use App\Models\UserBadge;

class BadgeService
{
    const BADGES = [
        'primer_dia' => [
            'name'        => 'Primera Marca',
            'icon'        => '🔰',
            'description' => 'Registraste tu primera asistencia',
        ],
        'racha_7' => [
            'name'        => 'Semana de Fuego',
            'icon'        => '🔥',
            'description' => 'Lograste una racha de 7 días',
        ],
        'racha_30' => [
            'name'        => 'Sin Excusas',
            'icon'        => '💪',
            'description' => 'Lograste una racha de 30 días',
        ],
        'racha_100' => [
            'name'        => 'Leyenda',
            'icon'        => '👑',
            'description' => 'Lograste una racha de 100 días',
        ],
    ];

    public static function checkAndAward(int $userId, int $challengeId, int $streak): array
    {
        $newBadges = [];

        // primer_dia — primera asistencia confirmada en toda la app
        $totalConfirmed = Attendance::where('user_id', $userId)->where('status', 'confirmed')->count();
        if ($totalConfirmed === 1 && self::award($userId, 'primer_dia', $challengeId)) {
            $newBadges[] = 'primer_dia';
        }

        // racha_7, racha_30, racha_100
        foreach ([7 => 'racha_7', 30 => 'racha_30', 100 => 'racha_100'] as $days => $key) {
            if ($streak >= $days && self::award($userId, $key, $challengeId)) {
                $newBadges[] = $key;
            }
        }

        // Notificar al usuario por cada badge nuevo
        if (!empty($newBadges)) {
            $user = User::find($userId);
            if ($user && $user->push_token) {
                foreach ($newBadges as $key) {
                    $info = self::BADGES[$key];
                    PushNotificationService::send(
                        [$user->push_token],
                        "¡Nuevo logro desbloqueado! {$info['icon']}",
                        "{$info['name']}: {$info['description']}",
                        ['type' => 'badge', 'badge_key' => $key],
                        null,
                        'mugen-social'
                    );
                }
            }
        }

        return $newBadges;
    }

    public static function badgeInfo(string $key): array
    {
        return self::BADGES[$key] ?? ['name' => $key, 'icon' => '🏅', 'description' => ''];
    }

    private static function award(int $userId, string $key, int $challengeId): bool
    {
        if (UserBadge::where('user_id', $userId)->where('badge_key', $key)->exists()) {
            return false;
        }

        UserBadge::create([
            'user_id'      => $userId,
            'badge_key'    => $key,
            'challenge_id' => $challengeId,
            'unlocked_at'  => now(),
        ]);

        return true;
    }
}
