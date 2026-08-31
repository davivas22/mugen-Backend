<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Challenge;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckGhostMembers extends Command
{
    protected $signature   = 'mugen:check-ghosts';
    protected $description = 'Notifica a las salas cuando un miembro lleva 3+ días sin ir';

    public function handle(): void
    {
        $challenges = Challenge::with('members')->get();

        foreach ($challenges as $challenge) {
            $members = $challenge->members;
            if ($members->count() < 2) continue;

            foreach ($members as $member) {
                // Días que normalmente va (promedio últimas 4 semanas)
                $recentDays = Attendance::where('user_id', $member->id)
                    ->where('challenge_id', $challenge->id)
                    ->where('status', 'confirmed')
                    ->where('attended_date', '>=', now()->subWeeks(4)->toDateString())
                    ->count();

                // Si va menos de 2 veces por semana en promedio, no es "regular" y no se cuenta como fantasma
                if ($recentDays < 8) continue;

                // Última asistencia
                $lastAttendance = Attendance::where('user_id', $member->id)
                    ->where('challenge_id', $challenge->id)
                    ->where('status', 'confirmed')
                    ->orderByDesc('attended_date')
                    ->first();

                if (!$lastAttendance) continue;

                $daysMissing = Carbon::parse($lastAttendance->attended_date)->diffInDays(now());

                if ($daysMissing >= 3 && $daysMissing <= 3) {
                    // Notificar a los demás miembros (no al fantasma mismo)
                    $otherTokens = $members
                        ->where('id', '!=', $member->id)
                        ->whereNotNull('push_token')
                        ->pluck('push_token')
                        ->toArray();

                    if ($otherTokens) {
                        PushNotificationService::send(
                            $otherTokens,
                            "👻 {$member->name} desapareció",
                            "Lleva {$daysMissing} días sin ir al gym en {$challenge->name}. ¿Alguien lo rescata?",
                            ['type' => 'ghost', 'challenge_id' => $challenge->id, 'user_id' => $member->id],
                            null,
                            'mugen-social'
                        );
                    }
                }
            }
        }

        $this->info('Check de fantasmas completado.');
    }
}
