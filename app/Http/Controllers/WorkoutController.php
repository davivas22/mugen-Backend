<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    public function index(Request $request)
    {
        $workouts = $request->user()->workouts()->latest()->take(20)->get();
        return response()->json(['workouts' => $workouts]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'            => 'required|string|max:100',
            'duration_minutes' => 'required|integer|min:0',
            'calories'         => 'nullable|integer|min:0',
            'icon_name'        => 'nullable|string|max:50',
            'challenge_id'     => 'nullable|integer|exists:challenges,id',
            'reps'             => 'nullable|integer|min:0',
        ]);

        $workout = $request->user()->workouts()->create([
            'title'            => $request->title,
            'duration_minutes' => $request->duration_minutes,
            'calories'         => $request->calories ?? 0,
            'icon_name'        => $request->icon_name ?? 'dumbbell',
            'challenge_id'     => $request->challenge_id,
            'reps'             => $request->reps ?? 0,
        ]);

        return response()->json(['workout' => $workout], 201);
    }

    public function stats(Request $request)
    {
        $user = $request->user();

        $totalWorkouts  = $user->workouts()->count();
        $totalHours     = (int) round($user->workouts()->sum('duration_minutes') / 60);
        $totalCalories  = (int) $user->workouts()->sum('calories');
        $totalMinutes   = (int) $user->workouts()->sum('duration_minutes');
        $totalReps      = (int) $user->workouts()->sum('reps');
        $streak         = $this->calculateStreak($user);
        $todayWorkouts  = (int) $user->workouts()->whereDate('created_at', today())->count();
        $todayCalories  = (int) $user->workouts()->whereDate('created_at', today())->sum('calories');
        $todayMinutes   = (int) $user->workouts()->whereDate('created_at', today())->sum('duration_minutes');
        $points         = $totalWorkouts * 10 + $totalHours * 5;

        return response()->json([
            'total_workouts'  => $totalWorkouts,
            'total_hours'     => $totalHours,
            'total_calories'  => $totalCalories,
            'total_minutes'   => $totalMinutes,
            'total_reps'      => $totalReps,
            'streak'          => $streak,
            'today_workouts'  => $todayWorkouts,
            'today_calories'  => $todayCalories,
            'today_minutes'   => $todayMinutes,
            'points'          => $points,
        ]);
    }

    public function weekly(Request $request)
    {
        $user        = $request->user();
        $dayLabels   = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
        $startOfWeek = now()->startOfWeek(1);
        $weekly      = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $mins = (int) $user->workouts()
                ->whereDate('created_at', $date->toDateString())
                ->sum('duration_minutes');

            $weekly[] = ['day' => $dayLabels[$i], 'mins' => $mins];
        }

        return response()->json(['weekly' => $weekly]);
    }

    public function destroy($id)
    {
        $workout = Workout::findOrFail($id);
        $workout->delete();
        return response()->json(['message' => 'Workout eliminado correctamente.']);
    }

    private function calculateStreak($user): int
    {
        $streak = 0;
        $date   = now()->startOfDay();

        // If today has no workout, still check from yesterday
        if (! $user->workouts()->whereDate('created_at', $date->toDateString())->exists()) {
            $date = $date->subDay();
        }

        while (true) {
            if (! $user->workouts()->whereDate('created_at', $date->toDateString())->exists()) {
                break;
            }
            $streak++;
            $date = $date->subDay();
        }

        return $streak;
    }
}
