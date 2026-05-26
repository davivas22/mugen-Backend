<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workout;
use App\Models\Challenge;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function stats()
    {
        $totalUsers     = User::whereNull('provider')->count();
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
}
