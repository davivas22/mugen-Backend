<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Challenge;
use App\Services\BadgeService;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    // POST /challenges/{id}/attend
    public function store(Request $request, $challengeId)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->gym_lat || !$challenge->gym_lng) {
            return response()->json(['message' => 'Esta sala no tiene ubicación del gym configurada.'], 422);
        }

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro de esta sala.'], 403);
        }

        $distance = $this->haversineDistance(
            (float) $request->lat,
            (float) $request->lng,
            (float) $challenge->gym_lat,
            (float) $challenge->gym_lng
        );

        $radius = $challenge->gym_radius_meters ?? 200;
        if ($distance > $radius) {
            return response()->json([
                'message'  => 'Estás muy lejos del gym.',
                'distance' => (int) round($distance),
                'radius'   => $radius,
            ], 422);
        }

        $gymDays = $challenge->gym_days_per_week ?? [];
        if (!empty($gymDays)) {
            $phpN         = (int) now()->format('N');
            $todayAppIdx  = $phpN === 7 ? 6 : $phpN - 1;
            if (!in_array($todayAppIdx, $gymDays)) {
                $names = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
                $gymDayNames = implode(', ', array_map(fn($i) => $names[$i], $gymDays));
                return response()->json([
                    'message' => "Hoy no es día de gym. Los días configurados son: $gymDayNames.",
                ], 422);
            }
        }

        $today = now()->toDateString();

        if (Attendance::where('challenge_id', $challengeId)
            ->where('user_id', auth()->id())
            ->where('attended_date', $today)
            ->where('status', 'confirmed')
            ->exists()) {
            return response()->json([
                'message'        => 'Ya registraste asistencia hoy.',
                'streak'         => $this->calculateChallengeStreak(auth()->id(), $challengeId, $gymDays),
                'attended_today' => true,
            ]);
        }

        Attendance::create([
            'challenge_id'    => $challengeId,
            'user_id'         => auth()->id(),
            'attended_date'   => $today,
            'status'          => 'confirmed',
            'validation_type' => 'gps',
        ]);

        $streak     = $this->calculateChallengeStreak(auth()->id(), $challengeId, $gymDays);
        $newBadges  = BadgeService::checkAndAward(auth()->id(), (int) $challengeId, $streak);

        return response()->json([
            'message'        => '¡Asistencia registrada!',
            'streak'         => $streak,
            'distance'       => (int) round($distance),
            'attended_today' => true,
            'new_badges'     => $newBadges,
        ], 201);
    }

    // GET /challenges/{id}/my-attendance
    public function myAttendance(Request $request, $challengeId)
    {
        $userId    = $request->user()->id;
        $challenge = Challenge::findOrFail($challengeId);
        $gymDays   = $challenge->gym_days_per_week ?? [];

        $dates = Attendance::where('challenge_id', $challengeId)
            ->where('user_id', $userId)
            ->where('status', 'confirmed')
            ->orderBy('attended_date', 'desc')
            ->pluck('attended_date')
            ->map(fn($d) => $d->toDateString());

        $streak = $this->calculateChallengeStreak($userId, $challengeId, $gymDays);

        $attendedToday = Attendance::where('challenge_id', $challengeId)
            ->where('user_id', $userId)
            ->where('attended_date', now()->toDateString())
            ->where('status', 'confirmed')
            ->exists();

        $pendingToday = Attendance::where('challenge_id', $challengeId)
            ->where('user_id', $userId)
            ->where('attended_date', now()->toDateString())
            ->where('status', 'pending')
            ->exists();

        return response()->json([
            'dates'          => $dates,
            'streak'         => $streak,
            'attended_today' => $attendedToday,
            'pending_today'  => $pendingToday,
        ]);
    }

    // POST /challenges/{id}/attend-camera
    public function attendCamera(Request $request, $challengeId)
    {
        $request->validate([
            'photo' => 'required|file|mimes:jpeg,jpg,png,webp|max:8192',
        ]);

        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->use_camera) {
            return response()->json(['message' => 'Esta sala no usa verificación por foto.'], 422);
        }

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro de esta sala.'], 403);
        }

        $gymDays = $challenge->gym_days_per_week ?? [];
        if (!empty($gymDays)) {
            $phpN        = (int) now()->format('N');
            $todayAppIdx = $phpN === 7 ? 6 : $phpN - 1;
            if (!in_array($todayAppIdx, $gymDays)) {
                $names       = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
                $gymDayNames = implode(', ', array_map(fn($i) => $names[$i], $gymDays));
                return response()->json(['message' => "Hoy no es día de gym. Días configurados: $gymDayNames."], 422);
            }
        }

        $today = now()->toDateString();

        // No permitir si ya tiene asistencia (confirmada o pendiente) hoy
        if (Attendance::where('challenge_id', $challengeId)
            ->where('user_id', auth()->id())
            ->where('attended_date', $today)
            ->exists()) {
            $confirmed = Attendance::where('challenge_id', $challengeId)
                ->where('user_id', auth()->id())
                ->where('attended_date', $today)
                ->where('status', 'confirmed')
                ->exists();

            return response()->json([
                'message'        => $confirmed ? 'Ya registraste asistencia hoy.' : 'Ya tienes una foto pendiente de confirmación.',
                'streak'         => $this->calculateChallengeStreak(auth()->id(), $challengeId, $gymDays),
                'attended_today' => $confirmed,
                'pending_today'  => !$confirmed,
            ]);
        }

        $path  = $request->file('photo')->store('attendances', 'public');
        $sender = $request->user();

        Attendance::create([
            'challenge_id'    => $challengeId,
            'user_id'         => $sender->id,
            'attended_date'   => $today,
            'status'          => 'pending',
            'photo_path'      => $path,
            'validation_type' => 'camera',
        ]);

        // Notificar a los compañeros para que confirmen
        $imageUrl = config('app.url') . '/storage/' . $path;
        $tokens   = PushNotificationService::tokensForRoom($challengeId, $sender->id);
        PushNotificationService::send(
            $tokens,
            $sender->name . ' necesita confirmación 📸',
            '¿Confirmas que esta foto es del gym?',
            ['type' => 'attendance_pending', 'challenge_id' => (int) $challengeId],
            $imageUrl,
            'mugen-social'
        );

        return response()->json([
            'message'       => '¡Foto enviada! Esperando confirmación de un compañero.',
            'pending_today' => true,
            'attended_today'=> false,
            'streak'        => $this->calculateChallengeStreak($sender->id, $challengeId, $gymDays),
        ], 201);
    }

    // GET /challenges/{id}/pending-attendances
    public function pendingAttendances(Request $request, $challengeId)
    {
        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro de esta sala.'], 403);
        }

        $pending = Attendance::where('challenge_id', $challengeId)
            ->where('status', 'pending')
            ->where('user_id', '!=', auth()->id())
            ->with('user:id,name,avatar')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id'         => $a->id,
                'user_id'    => $a->user_id,
                'user_name'  => $a->user->name ?? '',
                'user_avatar'=> $a->user->avatar,
                'photo_path' => $a->photo_path,
                'photo_url'  => $a->photo_path ? config('app.url') . '/storage/' . $a->photo_path : null,
                'date'       => $a->attended_date->toDateString(),
                'created_at' => $a->created_at->toISOString(),
            ]);

        return response()->json(['pending' => $pending]);
    }

    // POST /attendances/{id}/confirm
    public function confirmAttendance(Request $request, $attendanceId)
    {
        $attendance = Attendance::findOrFail($attendanceId);
        $challenge  = Challenge::findOrFail($attendance->challenge_id);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro de esta sala.'], 403);
        }

        if ($attendance->user_id === auth()->id()) {
            return response()->json(['message' => 'No puedes confirmar tu propia asistencia.'], 422);
        }

        if ($attendance->status === 'confirmed') {
            return response()->json(['message' => 'Esta asistencia ya fue confirmada.']);
        }

        $attendance->update([
            'status'       => 'confirmed',
            'confirmed_by' => auth()->id(),
        ]);

        $gymDays   = $challenge->gym_days_per_week ?? [];
        $streak    = $this->calculateChallengeStreak($attendance->user_id, $attendance->challenge_id, $gymDays);
        $newBadges = BadgeService::checkAndAward($attendance->user_id, (int) $attendance->challenge_id, $streak, [
            'hour' => $attendance->created_at->hour,
        ]);
        BadgeService::checkConfirmadorBadge(auth()->id(), (int) $attendance->challenge_id);

        // Notificar al dueño de la asistencia
        $owner = \App\Models\User::find($attendance->user_id);
        if ($owner && $owner->push_token) {
            $confirmer = $request->user();
            PushNotificationService::send(
                [$owner->push_token],
                '¡Asistencia confirmada! 🔥',
                $confirmer->name . ' confirmó tu foto del gym.',
                ['type' => 'attendance_confirmed', 'challenge_id' => (int) $attendance->challenge_id],
                null,
                'mugen-social'
            );
        }

        return response()->json([
            'message' => '¡Asistencia confirmada!',
            'streak'  => $streak,
        ]);
    }

    // POST /attendances/{id}/reject
    public function rejectAttendance(Request $request, $attendanceId)
    {
        $attendance = Attendance::findOrFail($attendanceId);
        $challenge  = Challenge::findOrFail($attendance->challenge_id);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro de esta sala.'], 403);
        }

        if ($attendance->user_id === auth()->id()) {
            return response()->json(['message' => 'No puedes rechazar tu propia asistencia.'], 422);
        }

        if ($attendance->status === 'confirmed') {
            return response()->json(['message' => 'Esta asistencia ya fue confirmada.'], 422);
        }

        if ($attendance->photo_path) {
            Storage::disk('public')->delete($attendance->photo_path);
        }
        $attendance->delete();

        return response()->json(['message' => 'Asistencia rechazada.']);
    }

    // POST /challenges/{id}/gym-location (creator only)
    public function setGymLocation(Request $request, $challengeId)
    {
        $request->validate([
            'lat'           => 'required|numeric',
            'lng'           => 'required|numeric',
            'radius_meters' => 'nullable|integer|min:50|max:2000',
        ]);

        $challenge = Challenge::findOrFail($challengeId);

        if ($challenge->user_id !== auth()->id()) {
            return response()->json(['message' => 'Solo el creador puede configurar la ubicación.'], 403);
        }

        $challenge->update([
            'gym_lat'           => $request->lat,
            'gym_lng'           => $request->lng,
            'gym_radius_meters' => $request->radius_meters ?? 200,
        ]);

        return response()->json([
            'message' => 'Ubicación del gym actualizada.',
            'gym_lat' => (float) $challenge->gym_lat,
            'gym_lng' => (float) $challenge->gym_lng,
        ]);
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function calculateChallengeStreak(int $userId, $challengeId, array $gymDays = []): int
    {
        $streak = 0;
        $date   = now()->startOfDay();

        if (!Attendance::where('challenge_id', $challengeId)
            ->where('user_id', $userId)
            ->where('attended_date', $date->toDateString())
            ->where('status', 'confirmed')
            ->exists()) {
            $date = $date->subDay();
        }

        for ($i = 0; $i < 365; $i++) {
            $phpN      = (int) $date->format('N');
            $dayAppIdx = $phpN === 7 ? 6 : $phpN - 1;
            $isGymDay  = empty($gymDays) || in_array($dayAppIdx, $gymDays);

            if (!$isGymDay) {
                $date = $date->subDay();
                continue;
            }

            $attended = Attendance::where('challenge_id', $challengeId)
                ->where('user_id', $userId)
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
