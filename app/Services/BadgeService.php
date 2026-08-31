<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use App\Models\UserBadge;

class BadgeService
{
    const BADGES = [
        // Racha
        'primer_dia'    => ['name' => 'Primera Marca',  'icon' => 'flag-outline',             'description' => 'Registraste tu primera asistencia'],
        'racha_7'       => ['name' => 'Semana de Fuego','icon' => 'flame-outline',            'description' => 'Lograste una racha de 7 días'],
        'racha_30'      => ['name' => 'Sin Excusas',    'icon' => 'barbell-outline',          'description' => 'Lograste una racha de 30 días'],
        'racha_100'     => ['name' => 'Leyenda',        'icon' => 'trophy-outline',           'description' => 'Lograste una racha de 100 días'],
        // Horario
        'madrugador'    => ['name' => 'Madrugador',     'icon' => 'sunny-outline',            'description' => 'Check-in antes de las 7am'],
        'nocturno'      => ['name' => 'Nocturno',       'icon' => 'moon-outline',             'description' => 'Check-in después de las 9pm'],
        // Constancia
        'comeback'      => ['name' => 'Comeback',       'icon' => 'refresh-circle-outline',   'description' => 'Volviste después de 7 días sin ir'],
        'fin_de_semana' => ['name' => 'Sin Descanso',   'icon' => 'calendar-outline',         'description' => 'Asististe sábado y domingo en la misma semana'],
        // Social / Cámara
        'fotogenico'    => ['name' => 'Fotogénico',     'icon' => 'camera-outline',           'description' => 'Subiste 10 fotos de asistencia'],
        'confirmador'   => ['name' => 'Verificador',    'icon' => 'shield-checkmark-outline', 'description' => 'Confirmaste 10 asistencias de otros'],
        // Fundador
        'fundador'      => ['name' => 'Fundador',       'icon' => 'home-outline',             'description' => 'Creaste tu primera sala'],
    ];

    public static function checkAndAward(int $userId, int $challengeId, int $streak, array $context = []): array
    {
        $newBadges = [];

        // primer_dia
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

        // madrugador — antes de las 7am
        $hour = $context['hour'] ?? now()->hour;
        if ($hour < 7 && self::award($userId, 'madrugador', $challengeId)) {
            $newBadges[] = 'madrugador';
        }

        // nocturno — después de las 9pm
        if ($hour >= 21 && self::award($userId, 'nocturno', $challengeId)) {
            $newBadges[] = 'nocturno';
        }

        // comeback — volver después de 7+ días sin ir
        $prev = Attendance::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->where('status', 'confirmed')
            ->orderByDesc('attended_at')
            ->skip(1)->first();

        if ($prev && $prev->attended_at->diffInDays(now()) >= 7) {
            if (self::award($userId, 'comeback', $challengeId)) {
                $newBadges[] = 'comeback';
            }
        }

        // fotogenico — 10 fotos de asistencia confirmadas
        $cameraCount = Attendance::where('user_id', $userId)
            ->where('status', 'confirmed')
            ->where('validation_type', 'camera')
            ->count();
        if ($cameraCount >= 10 && self::award($userId, 'fotogenico', $challengeId)) {
            $newBadges[] = 'fotogenico';
        }

        // fin_de_semana — sábado (WEEKDAY=5) y domingo (WEEKDAY=6) en los últimos 14 días
        $since = now()->subDays(14);
        $hasSat = Attendance::where('user_id', $userId)->where('status', 'confirmed')
            ->where('attended_at', '>=', $since)
            ->whereRaw('WEEKDAY(attended_at) = 5')->exists();
        $hasSun = Attendance::where('user_id', $userId)->where('status', 'confirmed')
            ->where('attended_at', '>=', $since)
            ->whereRaw('WEEKDAY(attended_at) = 6')->exists();
        if ($hasSat && $hasSun && self::award($userId, 'fin_de_semana', $challengeId)) {
            $newBadges[] = 'fin_de_semana';
        }

        self::notify($userId, $newBadges);

        return $newBadges;
    }

    public static function checkConfirmadorBadge(int $confirmerId, int $challengeId): void
    {
        $count = Attendance::where('confirmed_by', $confirmerId)->count();
        if ($count >= 10 && self::award($confirmerId, 'confirmador', $challengeId)) {
            self::notify($confirmerId, ['confirmador']);
        }
    }

    public static function checkFounderBadge(int $userId, int $challengeId): void
    {
        if (self::award($userId, 'fundador', $challengeId)) {
            self::notify($userId, ['fundador']);
        }
    }

    public static function badgeInfo(string $key): array
    {
        return self::BADGES[$key] ?? ['name' => $key, 'icon' => 'medal-outline', 'description' => ''];
    }

    private static function notify(int $userId, array $keys): void
    {
        if (empty($keys)) return;
        $user = User::find($userId);
        if (!$user || !$user->push_token) return;

        foreach ($keys as $key) {
            $info = self::BADGES[$key];
            PushNotificationService::send(
                [$user->push_token],
                '¡Nuevo logro desbloqueado!',
                "{$info['name']}: {$info['description']}",
                ['type' => 'badge', 'badge_key' => $key],
                null,
                'mugen-social'
            );
        }
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
