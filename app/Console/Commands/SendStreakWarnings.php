<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class SendStreakWarnings extends Command
{
    protected $signature   = 'streak:warn';
    protected $description = 'Envía notificaciones a usuarios con racha en peligro (sin check-in hoy)';

    public function handle(): void
    {
        $today = now()->toDateString();

        // Usuarios que tienen asistencia confirmada reciente pero NO hoy
        $users = User::whereHas('attendances', function ($q) {
                $q->where('status', 'confirmed')
                  ->where('attended_date', '>=', now()->subDays(30)->toDateString());
            })
            ->whereDoesntHave('attendances', function ($q) use ($today) {
                $q->where('status', 'confirmed')
                  ->where('attended_date', $today);
            })
            ->whereNotNull('push_token')
            ->get();

        $count = 0;

        foreach ($users as $user) {
            $streak = $this->getStreakForUser($user->id);
            if ($streak === 0) continue;

            PushNotificationService::send(
                [$user->push_token],
                "⚠️ ¡Tu racha de {$streak} día" . ($streak !== 1 ? 's' : '') . " está en peligro!",
                '¡Tienes hasta medianoche para registrar tu asistencia!',
                ['type' => 'streak_warning'],
                null,
                'mugen-social'
            );

            $count++;
        }

        $this->info("Avisos enviados a {$count} usuario(s).");
    }

    private function getStreakForUser(int $userId): int
    {
        $streak = 0;
        $date   = now()->subDay()->startOfDay();

        for ($i = 0; $i < 365; $i++) {
            $attended = Attendance::where('user_id', $userId)
                ->where('attended_date', $date->toDateString())
                ->where('status', 'confirmed')
                ->exists();

            if (!$attended) break;

            $streak++;
            $date = $date->subDay();
        }

        return $streak;
    }
}
