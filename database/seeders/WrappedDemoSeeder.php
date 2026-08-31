<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Challenge;
use App\Models\RoomMessage;
use App\Models\User;
use App\Models\UserBadge;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WrappedDemoSeeder extends Seeder
{
    public function run(): void
    {
        $mainUser = User::first();
        if (!$mainUser) {
            $this->command->error('No hay usuarios. Regístrate primero en la app.');
            return;
        }

        // ─── Crear usuarios demo ──────────────────────────────────
        $extras = [
            ['name' => 'Carlos Mendez',  'email' => 'carlos@demo.com',  'username' => 'carlos_fit'],
            ['name' => 'Ana García',     'email' => 'ana@demo.com',     'username' => 'ana_gym'],
            ['name' => 'Diego Ruiz',     'email' => 'diego@demo.com',   'username' => 'diego_ruiz'],
            ['name' => 'Luisa Vargas',   'email' => 'luisa@demo.com',   'username' => 'luisa_early'],
        ];

        $users = [$mainUser];
        foreach ($extras as $e) {
            $users[] = User::firstOrCreate(
                ['email' => $e['email']],
                array_merge($e, ['password' => Hash::make('demo123')])
            );
        }

        // ─── Crear la sala demo ───────────────────────────────────
        $challenge = Challenge::create([
            'user_id'           => $mainUser->id,
            'name'              => 'Los Titanes',
            'invite_code'       => 'TITAN25',
            'duration_days'     => 365,
            'start_date'        => now()->subMonths(12)->toDateString(),
            'gym_days_per_week' => [1, 2, 3, 4, 5],
            'challenge_mode'    => 'camera',
            'use_location'      => false,
            'use_camera'        => true,
            'meeting_point'     => '',
        ]);

        foreach ($users as $u) {
            $challenge->members()->attach($u->id, ['joined_at' => now()->subMonths(12)]);
        }

        $this->seedAttendances($challenge, $users);
        $this->seedMessages($challenge, $users);
        $this->seedBadges($challenge, $users);

        $this->command->info("✅ Sala creada: \"{$challenge->name}\" (ID: {$challenge->id})");
        $this->command->info("   Entra a la sala con ID {$challenge->id} para ver el Wrapped");
    }

    // ─── Patrones de asistencia por usuario ──────────────────────────────────
    private function seedAttendances(Challenge $challenge, array $users): void
    {
        // días/mes y hora base por usuario (12 meses)
        $patterns = [
            // mainUser — constante, madruga algunos días, fotos ocasionales
            0 => ['days' => [18,16,20,15,19,17,14,18,16,20,17,12], 'hour' => 7,  'cam' => 0.35],
            // Carlos — el más constante, tarde media, poca cámara
            1 => ['days' => [22,21,23,20,22,21,19,22,20,23,21,19], 'hour' => 9,  'cam' => 0.20],
            // Ana — moderada, confirma muchas fotos de otros, sube muchas propias
            2 => ['days' => [12,10,14,11,13,10, 8,12,11,13,10, 9], 'hour' => 18, 'cam' => 0.65],
            // Diego — el donante: solo enero y un poco de febrero
            3 => ['days' => [ 4, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], 'hour' => 11, 'cam' => 0.00],
            // Luisa — madrugadora total, siempre a las 5-6am
            4 => ['days' => [15,14,16,13,15,14,12,15,14,16,13,10], 'hour' => 5,  'cam' => 0.45],
        ];

        foreach ($users as $idx => $user) {
            $p = $patterns[$idx] ?? $patterns[0];

            for ($m = 0; $m < 12; $m++) {
                $daysTarget = $p['days'][$m] ?? 0;
                if ($daysTarget === 0) continue;

                $monthStart  = now()->subMonths(11 - $m)->startOfMonth();
                $daysInMonth = $monthStart->daysInMonth;

                $possible = range(1, $daysInMonth);
                shuffle($possible);
                $selected = array_slice($possible, 0, min($daysTarget, $daysInMonth));
                sort($selected);

                foreach ($selected as $day) {
                    $date = $monthStart->copy()->setDay($day);
                    if ($date->isFuture()) continue;

                    $hour   = max(5, min(22, $p['hour'] + rand(-1, 1)));
                    $isCam  = (rand(0, 100) / 100) < $p['cam'];
                    $confBy = null;
                    if ($isCam) {
                        // Ana confirma a todos, mainUser confirma a Ana
                        $confBy = $idx === 2 ? $users[0]->id : $users[2]->id;
                    }

                    $ts = $date->copy()->setHour($hour)->setMinute(rand(0, 59));

                    Attendance::create([
                        'user_id'         => $user->id,
                        'challenge_id'    => $challenge->id,
                        'attended_date'   => $date->toDateString(),
                        'status'          => 'confirmed',
                        'validation_type' => $isCam ? 'camera' : 'gps',
                        'photo_path'      => null,
                        'confirmed_by'    => $confBy,
                        'created_at'      => $ts,
                        'updated_at'      => $ts,
                    ]);
                }
            }
        }
    }

    // ─── Mensajes de sala ─────────────────────────────────────────────────────
    private function seedMessages(Challenge $challenge, array $users): void
    {
        $msgs = [
            'Ya llegué 💪', 'Hoy piernas... ugh', 'Alguien va mañana?',
            'Record personal hoy!', 'La sala está llena', '6am squad 🌅',
            'No falten esta semana', 'Carlos lo hizo de nuevo 🔥',
            'Mañana no puedo, trabajo', 'Ya voy llegando', 'Terminé 🙌',
            'Quién confirma mi foto?', 'Racha en peligro ⚠️', '¡Salvé la racha!',
            'Semana perfecta 💯', 'Que dolor de piernas', 'Nos vemos mañana',
            'Nueva rutina esta semana', 'Hoy no se puede fallar', 'Último día del mes!',
            'Empezamos bien el mes', 'Luisa ya estaba cuando llegué 😅',
            'Diego... dónde andas?', 'Ánimo equipo', 'La racha sigue 🔥',
        ];

        for ($i = 0; $i < 85; $i++) {
            $user = $users[array_rand($users)];
            $ts   = now()->subDays(rand(0, 360));
            RoomMessage::create([
                'challenge_id' => $challenge->id,
                'sender_id'    => $user->id,
                'content'      => $msgs[array_rand($msgs)],
                'created_at'   => $ts,
                'updated_at'   => $ts,
            ]);
        }
    }

    // ─── Badges ───────────────────────────────────────────────────────────────
    private function seedBadges(Challenge $challenge, array $users): void
    {
        $assign = [
            0 => ['primer_dia','racha_7','racha_30','madrugador','fotogenico','fundador'],
            1 => ['primer_dia','racha_7','racha_30','racha_100','constante'],
            2 => ['primer_dia','racha_7','confirmador','fotogenico'],
            3 => ['primer_dia'],
            4 => ['primer_dia','racha_7','madrugador'],
        ];

        foreach ($users as $idx => $user) {
            foreach ($assign[$idx] ?? ['primer_dia'] as $key) {
                UserBadge::firstOrCreate(
                    ['user_id' => $user->id, 'badge_key' => $key],
                    ['challenge_id' => $challenge->id, 'unlocked_at' => now()->subDays(rand(10, 300))]
                );
            }
        }
    }
}
