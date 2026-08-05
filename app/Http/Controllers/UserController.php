<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Challenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\PushNotificationService;

class UserController extends Controller
{

    public function index()
{
    $users = \App\Models\User::select('id', 'name', 'email', 'username', 'weight', 'height', 'created_at')->get();
    return response()->json(['users' => $users]);
}

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        Log::info('[Profile] request received', [
            'user_id'    => $user->id,
            'has_avatar' => $request->hasFile('avatar'),
            'files'      => array_keys($request->allFiles()),
            'input_keys' => array_keys($request->all()),
        ]);

        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'username' => 'nullable|string|max:50|unique:users,username,' . $user->id,
            'bio'      => 'nullable|string|max:300',
            'weight'   => 'nullable|numeric|min:1|max:500',
            'height'   => 'nullable|numeric|min:1|max:300',
            // 'file' instead of 'image' — React Native sometimes sends
            // a content-type that fools PHP's MIME detection
            'avatar'   => 'nullable|file|max:5120',
        ]);

        $data = [];

        if ($request->has('name'))     $data['name']     = $request->name;
        if ($request->has('username')) $data['username'] = $request->username;
        if ($request->has('bio'))      $data['bio']      = $request->bio;
        if ($request->has('weight') && $request->weight !== null) $data['weight'] = (int) $request->weight;
        if ($request->has('height') && $request->height !== null) $data['height'] = (int) $request->height;

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            Log::info('[Profile] avatar file received', [
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'is_valid'      => $file->isValid(),
            ]);

            if ($file->isValid()) {
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $path = $file->store('avatars', 'public');
                $data['avatar'] = $path;
                Log::info('[Profile] avatar stored at: ' . $path);
            } else {
                Log::warning('[Profile] avatar file invalid, error: ' . $file->getError());
            }
        }

        if (! empty($data)) {
            // Use fill + save to bypass any fillable caching issues
            $user->fill($data)->save();
        }

        $fresh = $user->fresh();
        Log::info('[Profile] saved, avatar=' . $fresh->avatar);

        return response()->json(['user' => $fresh]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    public function savePushToken(Request $request)
    {
        $request->validate(['push_token' => 'required|string|max:500']);
        $request->user()->update(['push_token' => $request->push_token]);
        return response()->json(['ok' => true]);
    }

    public function show($id)
    {
        $user = User::select('id', 'name', 'username', 'avatar', 'bio')->findOrFail($id);
        return response()->json(['user' => $user]);
    }

    // GET /feed — actividad reciente de compañeros de sala
    public function feed(Request $request)
    {
        $userId      = auth()->id();
        $challengeIds = Challenge::whereHas('members', fn($q) => $q->where('users.id', $userId))->pluck('id');

        if ($challengeIds->isEmpty()) {
            return response()->json(['feed' => []]);
        }

        $attendances = DB::table('attendances')
            ->join('users',      'users.id',      '=', 'attendances.user_id')
            ->join('challenges', 'challenges.id', '=', 'attendances.challenge_id')
            ->whereIn('attendances.challenge_id', $challengeIds)
            ->where('attendances.user_id', '!=', $userId)
            ->where('attendances.attended_date', '>=', now()->subDays(7)->toDateString())
            ->select(
                'attendances.id',
                DB::raw("'attendance' as type"),
                'users.name as user_name',
                'users.avatar as user_avatar',
                'challenges.name as challenge_name',
                DB::raw('attendances.attended_date as event_date'),
                DB::raw('NULL as photo_path'),
                DB::raw('NULL as photo_id'),
            )
            ->orderByDesc('attendances.attended_date')
            ->limit(15)
            ->get();

        $photos = DB::table('journey_photos')
            ->join('users',      'users.id',      '=', 'journey_photos.user_id')
            ->join('challenges', 'challenges.id', '=', 'journey_photos.challenge_id')
            ->whereIn('journey_photos.challenge_id', $challengeIds)
            ->where('journey_photos.user_id', '!=', $userId)
            ->where('journey_photos.created_at', '>=', now()->subDays(7))
            ->select(
                'journey_photos.id',
                DB::raw("'journey_photo' as type"),
                'users.name as user_name',
                'users.avatar as user_avatar',
                'challenges.name as challenge_name',
                DB::raw('DATE(journey_photos.created_at) as event_date'),
                'journey_photos.photo_path',
                DB::raw('journey_photos.id as photo_id'),
            )
            ->orderByDesc('journey_photos.created_at')
            ->limit(15)
            ->get();

        $feed = collect($attendances)->merge($photos)
            ->sortByDesc('event_date')
            ->values()
            ->map(fn($r) => [
                'type'           => $r->type,
                'user_name'      => $r->user_name,
                'user_avatar'    => $r->user_avatar,
                'challenge_name' => $r->challenge_name,
                'photo_path'     => $r->photo_path,
                'photo_id'       => $r->photo_id,
                'event_date'     => $r->event_date,
                'text'           => $r->type === 'attendance'
                    ? "{$r->user_name} hizo check-in en {$r->challenge_name} 🏋️"
                    : "{$r->user_name} subió una foto en {$r->challenge_name} 📸",
            ]);

        return response()->json(['feed' => $feed]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->workouts()->delete();
        DB::table('challenge_members')->where('user_id', $id)->delete();
        Challenge::where('user_id', $id)->delete();
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }
}
