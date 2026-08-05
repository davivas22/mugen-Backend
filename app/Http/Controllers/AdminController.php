<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workout;
use App\Models\Challenge;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function stats()
    {
        $totalUsers     = User::count();
        $totalWorkouts  = Workout::count();
        $totalCalories  = (int) Workout::sum('calories');
        $totalMinutes   = (int) Workout::sum('duration_minutes');
        $totalReps      = (int) Workout::sum('reps');
        $totalChallenges = Challenge::count();

        $todayWorkouts = (int) Workout::whereDate('created_at', today())->count();
        $todayCalories = (int) Workout::whereDate('created_at', today())->sum('calories');
        $todayMinutes  = (int) Workout::whereDate('created_at', today())->sum('duration_minutes');
        $activeUsersToday = (int) Workout::whereDate('created_at', today())
            ->distinct('user_id')->count('user_id');
        $attendancesToday = (int) Attendance::whereDate('attended_date', today())->count();

        return response()->json([
            'total_users'       => $totalUsers,
            'total_workouts'    => $totalWorkouts,
            'total_calories'    => $totalCalories,
            'total_minutes'     => $totalMinutes,
            'total_reps'        => $totalReps,
            'total_challenges'  => $totalChallenges,
            'today_workouts'    => $todayWorkouts,
            'today_calories'    => $todayCalories,
            'today_minutes'     => $todayMinutes,
            'active_users_today' => $activeUsersToday,
            'attendances_today'  => $attendancesToday,
        ]);
    }

    public function weekly()
    {
        $dayLabels   = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
        $startOfWeek = now()->startOfWeek(1);
        $weekly      = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $mins = (int) Workout::whereDate('created_at', $date->toDateString())
                ->sum('duration_minutes');
            $weekly[] = ['day' => $dayLabels[$i], 'mins' => $mins];
        }

        return response()->json(['weekly' => $weekly]);
    }

    public function workouts(Request $request)
    {
        $query = Workout::with('user:id,name,email,username');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $workouts = $query->latest()->take(50)->get();

        return response()->json(['workouts' => $workouts]);
    }

    public function userChallenges($userId)
    {
        $challenges = Challenge::whereHas('members', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->orWhere('user_id', $userId)
            ->withCount('members')
            ->get();

        return response()->json(['challenges' => $challenges]);
    }

    public function destroyChallenge($id)
    {
        $challenge = Challenge::findOrFail($id);
        $challenge->members()->detach();
        $challenge->delete();

        return response()->json(['message' => 'Challenge eliminado correctamente.']);
    }

    public function destroyWorkout($id)
    {
        $workout = Workout::findOrFail($id);
        $workout->delete();

        return response()->json(['message' => 'Workout eliminado correctamente.']);
    }

    public function removeMember($challengeId, $userId)
    {
        $challenge = Challenge::findOrFail($challengeId);

        if ($challenge->members()->count() <= 1) {
            return response()->json([
                'message' => 'No se puede expulsar al único miembro de la sala.'
            ], 422);
        }

        $challenge->members()->detach($userId);

        return response()->json(['message' => 'Miembro eliminado correctamente.']);
    }

    // ── USUARIOS (CRUD admin) ────────────────────────────
    public function storeUser(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'username' => 'nullable|string|max:50|unique:users,username',
            'weight'   => 'nullable|numeric|min:1|max:500',
            'height'   => 'nullable|numeric|min:1|max:300',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'username' => $request->username ?: null,
            'weight'   => $request->weight ?: null,
            'height'   => $request->height ?: null,
        ]);

        return response()->json(['user' => $user], 201);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $user->id,
            'username' => 'sometimes|nullable|string|max:50|unique:users,username,' . $user->id,
            'weight'   => 'sometimes|nullable|numeric|min:1|max:500',
            'height'   => 'sometimes|nullable|numeric|min:1|max:300',
        ]);

        $data = $request->only(['name', 'email', 'username', 'weight', 'height']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json(['user' => $user->fresh()]);
    }

    // ── CHALLENGES (CRUD admin) ──────────────────────────
    public function storeChallenge(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:50',
            'duration_days'  => 'required|integer|min:1',
            'start_date'     => 'required|date',
            'challenge_mode' => 'required|string',
            'user_id'        => 'required|integer|exists:users,id',
        ]);

        $challenge = Challenge::create([
            'user_id'           => $request->user_id,
            'name'              => $request->name,
            'invite_code'       => Challenge::generateInviteCode(),
            'duration_days'     => $request->duration_days,
            'start_date'        => $request->start_date,
            'gym_days_per_week' => $request->gym_days_per_week ?? [],
            'challenge_mode'    => $request->challenge_mode,
        ]);

        $challenge->members()->attach($request->user_id, ['joined_at' => now()]);

        return response()->json(['challenge' => $challenge], 201);
    }

    public function updateChallenge(Request $request, $id)
    {
        $challenge = Challenge::findOrFail($id);

        $request->validate([
            'name'           => 'sometimes|string|max:50',
            'duration_days'  => 'sometimes|integer|min:1',
            'start_date'     => 'sometimes|date',
            'challenge_mode' => 'sometimes|string',
        ]);

        $challenge->update($request->only(['name', 'duration_days', 'start_date', 'challenge_mode']));

        return response()->json(['challenge' => $challenge->fresh()]);
    }

    // ── WORKOUTS (CRUD admin) ─────────────────────────────
    public function storeWorkout(Request $request)
    {
        $request->validate([
            'title'            => 'required|string|max:100',
            'duration_minutes' => 'required|integer|min:0',
            'calories'         => 'nullable|integer|min:0',
            'reps'             => 'nullable|integer|min:0',
            'user_id'          => 'required|integer|exists:users,id',
        ]);

        $workout = Workout::create([
            'user_id'          => $request->user_id,
            'title'            => $request->title,
            'duration_minutes' => $request->duration_minutes,
            'calories'         => $request->calories ?? 0,
            'reps'             => $request->reps ?? 0,
            'icon_name'        => 'dumbbell',
        ]);

        return response()->json(['workout' => $workout], 201);
    }

    public function updateWorkout(Request $request, $id)
    {
        $workout = Workout::findOrFail($id);

        $request->validate([
            'title'            => 'sometimes|string|max:100',
            'duration_minutes' => 'sometimes|integer|min:0',
            'calories'         => 'sometimes|nullable|integer|min:0',
            'reps'             => 'sometimes|nullable|integer|min:0',
        ]);

        $workout->update($request->only(['title', 'duration_minutes', 'calories', 'reps']));

        return response()->json(['workout' => $workout->fresh()]);
    }

    // ── ASISTENCIA (admin) ────────────────────────────────
    public function attendanceIndex(Request $request)
    {
        $query = Attendance::with(['user:id,name,username', 'challenge:id,name']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $attendances = $query->latest()->take(200)->get();

        return response()->json(['attendances' => $attendances]);
    }

    public function userAttendance($id)
    {
        $attendances = Attendance::with('challenge:id,name')
            ->where('user_id', $id)
            ->latest()
            ->get();

        return response()->json(['attendances' => $attendances]);
    }

    public function destroyAttendance($id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();

        return response()->json(['message' => 'Asistencia eliminada correctamente.']);
    }
}
