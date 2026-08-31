<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Challenge;
use App\Models\JourneyPhoto;
use App\Models\RoomMessage;
use App\Models\UserBadge;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WrappedController extends Controller
{
    public function show(Request $request, $challengeId)
    {
        $challenge = Challenge::with('members')->findOrFail($challengeId);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro.'], 403);
        }

        $months = min((int) ($request->query('months', 12)), 24);
        $since  = now()->subMonths($months)->startOfDay()->toDateString();
        $userId = auth()->id();

        // ─── Asistencias personales ───────────────────────────────
        $myAttendances = Attendance::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->where('status', 'confirmed')
            ->where('attended_date', '>=', $since)
            ->orderBy('attended_date')
            ->get();

        $totalDays = $myAttendances->count();

        // Días planeados
        $gymDays     = $challenge->gym_days_per_week ?? [];
        $weeksInPeriod = max(1, (int) Carbon::parse($since)->diffInWeeks(now()));
        $plannedDays   = count($gymDays) * $weeksInPeriod;
        $attendancePct = $plannedDays > 0 ? min(100, round(($totalDays / $plannedDays) * 100)) : 0;

        // Hora favorita (usando created_at porque attended_date no tiene hora)
        $favoriteHour = null;
        if ($totalDays > 0) {
            $row = Attendance::where('user_id', $userId)
                ->where('challenge_id', $challengeId)
                ->where('status', 'confirmed')
                ->where('attended_date', '>=', $since)
                ->selectRaw('HOUR(created_at) as hour, COUNT(*) as cnt')
                ->groupBy('hour')->orderByDesc('cnt')->first();
            $favoriteHour = $row?->hour;
        }

        // Día favorito de la semana
        $dayNames = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $favoriteDay = null;
        if ($totalDays > 0) {
            $row = Attendance::where('user_id', $userId)
                ->where('challenge_id', $challengeId)
                ->where('status', 'confirmed')
                ->where('attended_date', '>=', $since)
                ->selectRaw('DAYOFWEEK(attended_date) as dow, COUNT(*) as cnt')
                ->groupBy('dow')->orderByDesc('cnt')->first();
            $favoriteDay = $row ? ($dayNames[$row->dow - 1] ?? null) : null;
        }

        // Racha máxima histórica
        $maxStreak = $this->computeMaxStreak($myAttendances);

        // Primera asistencia
        $firstCheckin = $myAttendances->first()?->attended_date?->toDateString();

        // Fotos por cámara
        $cameraPhotos = Attendance::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->where('status', 'confirmed')
            ->where('validation_type', 'camera')
            ->where('attended_date', '>=', $since)
            ->count();

        // Checkins tempranos/nocturnos (para personalidad)
        $earlyCount = Attendance::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->where('status', 'confirmed')
            ->where('attended_date', '>=', $since)
            ->whereRaw('HOUR(created_at) < 7')->count();

        $lateCount = Attendance::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->where('status', 'confirmed')
            ->where('attended_date', '>=', $since)
            ->whereRaw('HOUR(created_at) >= 19')->count();

        // Fotos del journey (sección de progreso personal)
        $journeyPhotos = JourneyPhoto::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->count();

        // Badges ganados en el período
        $badgesEarned = UserBadge::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->where('unlocked_at', '>=', $since)
            ->pluck('badge_key')->toArray();

        // Confirmaciones hechas por el usuario
        $confirmationsDone = Attendance::where('confirmed_by', $userId)
            ->where('challenge_id', $challengeId)
            ->count();

        // Confirmaciones recibidas por el usuario (sus fotos confirmadas por otros)
        $confirmationsReceived = Attendance::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->where('validation_type', 'camera')
            ->whereNotNull('confirmed_by')
            ->where('attended_date', '>=', $since)
            ->count();

        // Evolución mensual — días por mes
        $monthlyRaw = Attendance::where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->where('status', 'confirmed')
            ->where('attended_date', '>=', $since)
            ->selectRaw('YEAR(attended_date) as yr, MONTH(attended_date) as mo, COUNT(*) as cnt')
            ->groupBy('yr', 'mo')
            ->orderBy('yr')->orderBy('mo')
            ->get();

        $monthNames = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $monthlyEvolution = [];
        $bestMonth = null; $bestMonthDays = 0;
        for ($i = 11; $i >= 0; $i--) {
            $target = now()->subMonths($i);
            $yr = (int) $target->format('Y');
            $mo = (int) $target->format('n');
            $row = $monthlyRaw->first(fn($r) => (int)$r->yr === $yr && (int)$r->mo === $mo);
            $cnt = $row ? (int)$row->cnt : 0;
            $monthlyEvolution[] = ['month' => $monthNames[$mo - 1], 'days' => $cnt];
            if ($cnt > $bestMonthDays) { $bestMonthDays = $cnt; $bestMonth = $monthNames[$mo - 1]; }
        }

        // Días perdidos (tenía que ir pero no fue)
        $missedDays = max(0, $plannedDays - $totalDays);

        // Personalidad
        $personality = $this->computePersonality(
            $totalDays, $plannedDays, $attendancePct, $maxStreak,
            $earlyCount, $lateCount, $cameraPhotos, $confirmationsDone, $journeyPhotos
        );

        // ─── Estadísticas de sala ─────────────────────────────────
        $memberIds = $challenge->members()->pluck('users.id');

        $salaTotalDays = Attendance::whereIn('user_id', $memberIds)
            ->where('challenge_id', $challengeId)
            ->where('status', 'confirmed')
            ->where('attended_date', '>=', $since)
            ->count();

        // Ranking completo de miembros con avatares
        $membersRanking = DB::table('attendances')
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->whereIn('attendances.user_id', $memberIds)
            ->where('attendances.challenge_id', $challengeId)
            ->where('attendances.status', 'confirmed')
            ->where('attendances.attended_date', '>=', $since)
            ->selectRaw('attendances.user_id, users.name, users.avatar, COUNT(*) as cnt')
            ->groupBy('attendances.user_id', 'users.name', 'users.avatar')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn($r) => ['user_id' => $r->user_id, 'name' => $r->name, 'avatar' => $r->avatar, 'days' => $r->cnt])
            ->values()->toArray();

        $myRank = collect($membersRanking)->search(fn($r) => $r['user_id'] === $userId);
        $myRank = $myRank !== false ? $myRank + 1 : null;

        $mostConsistent = $membersRanking[0] ?? null;
        $leastConsistent = count($membersRanking) > 1 ? $membersRanking[count($membersRanking) - 1] : null;

        // Día más activo de la sala
        $mostActiveDayRow = Attendance::whereIn('user_id', $memberIds)
            ->where('challenge_id', $challengeId)
            ->where('status', 'confirmed')
            ->where('attended_date', '>=', $since)
            ->selectRaw('attended_date, COUNT(*) as cnt')
            ->groupBy('attended_date')->orderByDesc('cnt')->first();

        // Badges totales de la sala
        $salaTotalBadges = UserBadge::whereIn('user_id', $memberIds)
            ->where('challenge_id', $challengeId)
            ->where('unlocked_at', '>=', $since)
            ->count();

        // Mensajes de sala
        $messageCount = RoomMessage::where('challenge_id', $challengeId)
            ->where('created_at', '>=', $since)
            ->count();

        // Quien más confirmó fotos
        $topConfRow = DB::table('attendances')
            ->join('users', 'attendances.confirmed_by', '=', 'users.id')
            ->whereIn('attendances.confirmed_by', $memberIds)
            ->where('attendances.challenge_id', $challengeId)
            ->whereNotNull('attendances.confirmed_by')
            ->selectRaw('attendances.confirmed_by, users.name, COUNT(*) as cnt')
            ->groupBy('attendances.confirmed_by', 'users.name')
            ->orderByDesc('cnt')
            ->first();

        $mostConfirmations = $topConfRow ? [
            'name'  => $topConfRow->name,
            'count' => $topConfRow->cnt,
        ] : null;

        // Miembro con racha grupal — día donde TODOS fueron
        $allMemberCount = $memberIds->count();
        $groupStreakDay = DB::table('attendances')
            ->whereIn('user_id', $memberIds)
            ->where('challenge_id', $challengeId)
            ->where('status', 'confirmed')
            ->where('attended_date', '>=', $since)
            ->selectRaw('attended_date, COUNT(DISTINCT user_id) as present')
            ->groupBy('attended_date')
            ->havingRaw('present = ?', [$allMemberCount])
            ->count();

        return response()->json([
            'period_months' => $months,
            'sala_name'     => $challenge->name,
            'personal' => [
                'total_days'            => $totalDays,
                'planned_days'          => $plannedDays,
                'missed_days'           => $missedDays,
                'attendance_pct'        => $attendancePct,
                'max_streak'            => $maxStreak,
                'favorite_hour'         => $favoriteHour,
                'favorite_day'          => $favoriteDay,
                'first_checkin'         => $firstCheckin,
                'camera_photos'         => $cameraPhotos,
                'journey_photos'        => $journeyPhotos,
                'confirmations_received'=> $confirmationsReceived,
                'badges_earned'         => count($badgesEarned),
                'badges_keys'           => $badgesEarned,
                'monthly_evolution'     => $monthlyEvolution,
                'best_month'            => $bestMonth,
                'best_month_days'       => $bestMonthDays,
                'my_rank'               => $myRank,
                'personality'           => $personality,
            ],
            'sala' => [
                'total_days'        => $salaTotalDays,
                'member_count'      => $allMemberCount,
                'members_ranking'   => $membersRanking,
                'most_consistent'   => $mostConsistent,
                'least_consistent'  => $leastConsistent,
                'total_badges'      => $salaTotalBadges,
                'message_count'     => $messageCount,
                'most_active_day'   => $mostActiveDayRow?->attended_date,
                'most_confirmations'=> $mostConfirmations,
                'group_streak_days' => $groupStreakDay,
            ],
        ]);
    }

    private function computeMaxStreak($attendances): int
    {
        if ($attendances->isEmpty()) return 0;

        $dates = $attendances
            ->map(fn($a) => $a->attended_date->toDateString())
            ->unique()->sort()->values();

        $max = 1;
        $cur = 1;

        for ($i = 1; $i < $dates->count(); $i++) {
            $diff = Carbon::parse($dates[$i - 1])->diffInDays(Carbon::parse($dates[$i]));
            if ($diff === 1) { $cur++; $max = max($max, $cur); }
            else             { $cur = 1; }
        }

        return $max;
    }

    private function computePersonality(
        int $total, int $planned, int $pct, int $maxStreak,
        int $early, int $late, int $camera, int $confirmations, int $journeyPhotos = 0
    ): array {
        $totalPhotos = $camera + $journeyPhotos;

        if ($total == 0) return [
            'key' => 'fantasma', 'icon' => 'eye-off-outline',
            'name' => 'El Fantasma',
            'desc' => 'Ni apareciste. La sala ni sabe que existes. La membresía la pagó el viento.',
        ];
        if ($total == 1) return [
            'key' => 'dia_unico', 'icon' => 'flag-outline',
            'name' => 'El Día Único',
            'desc' => 'Fuiste exactamente un día. Ese día fue épico. El resto del tiempo, misterio.',
        ];
        if ($total <= 3) return [
            'key' => 'donante', 'icon' => 'cash-outline',
            'name' => 'El Donante Voluntario',
            'desc' => 'Le das dinero al gym con mucho cariño y sin esperar nada a cambio. Generoso.',
        ];
        if ($maxStreak <= 7 && $pct < 25) return [
            'key' => 'motivado_semana', 'icon' => 'battery-half-outline',
            'name' => 'El Motivado de 1 Semana',
            'desc' => 'Enero fue tu mejor mes. Cada lunes es un nuevo comienzo. Que nunca termina.',
        ];
        if ($pct < 20) return [
            'key' => 'excusas', 'icon' => 'close-circle-outline',
            'name' => 'El Maestro de las Excusas',
            'desc' => '"Mañana sí voy" — tú, cada semana, desde hace meses.',
        ];
        if ($pct < 40) return [
            'key' => 'eventual', 'icon' => 'shuffle-outline',
            'name' => 'El Eventual',
            'desc' => 'Vas cuando te acuerdas, que no es seguido. Pero el gym te conoce la cara.',
        ];
        if ($total > 0 && $early > $total * 0.4) return [
            'key' => 'madrugador', 'icon' => 'sunny-outline',
            'name' => 'El Madrugador',
            'desc' => 'El gym es tuyo a las 6am. Mientras el mundo duerme, tú ya has entrenado.',
        ];
        if ($total > 0 && $late > $total * 0.4) return [
            'key' => 'nocturno', 'icon' => 'moon-outline',
            'name' => 'El Nocturno',
            'desc' => 'Cuando todos se van, tú llegas. Las pesas de noche pesan diferente. 7pm en adelante.',
        ];
        if ($maxStreak >= 30) return [
            'key' => 'imparable', 'icon' => 'flame-outline',
            'name' => 'El Imparable',
            'desc' => 'Nada te detiene. Eres la razón por la que los demás no faltan.',
        ];
        if ($confirmations >= 15) return [
            'key' => 'verificador', 'icon' => 'shield-checkmark-outline',
            'name' => 'El Verificador',
            'desc' => 'Sin ti, nadie sería oficial. Eres el árbitro moral de la sala.',
        ];
        if ($totalPhotos >= 10) return [
            'key' => 'fotografo', 'icon' => 'camera-outline',
            'name' => 'El Fotogénico',
            'desc' => 'Cada visita merece documentarse. Tus fotos de asistencia y journey lo demuestran.',
        ];
        if ($pct >= 90 && $maxStreak >= 21) return [
            'key' => 'gym_rat', 'icon' => 'barbell-outline',
            'name' => 'El Gym Rat',
            'desc' => 'El gym es tu segundo hogar. Si no vas un día, algo va mal. Eres el más dedicado.',
        ];
        if ($pct >= 80) return [
            'key' => 'constante', 'icon' => 'checkmark-circle-outline',
            'name' => 'El Constante',
            'desc' => 'No eres el más ruidoso, pero siempre estás. La consistencia es tu superpoder.',
        ];
        return [
            'key' => 'guerrero', 'icon' => 'fitness-outline',
            'name' => 'El Guerrero',
            'desc' => 'Vas, entrenas y vuelves. Sin excusas, sin drama. Eso es suficiente.',
        ];
    }
}
