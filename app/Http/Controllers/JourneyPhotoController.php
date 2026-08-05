<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\JourneyPhoto;
use App\Models\MessageRead;
use App\Models\PhotoReaction;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JourneyPhotoController extends Controller
{
    public function index(Request $request, $challengeId)
    {
        $photos = JourneyPhoto::where('challenge_id', $challengeId)
            ->where('user_id', $request->user()->id)
            ->orderBy('photo_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'photo_path' => $p->photo_path,
                'note'       => $p->note,
                'photo_date' => $p->photo_date->toDateString(),
            ]);

        return response()->json(['entries' => $photos]);
    }

    public function store(Request $request, $challengeId)
    {
        $request->validate([
            'photo'      => 'required|file|max:8192',
            'note'       => 'nullable|string|max:300',
            'photo_date' => 'required|date',
        ]);

        $path = $request->file('photo')->store('journey', 'public');

        $sender = $request->user();

        $photo = JourneyPhoto::create([
            'challenge_id' => $challengeId,
            'user_id'      => $sender->id,
            'photo_path'   => $path,
            'note'         => $request->input('note'),
            'photo_date'   => $request->input('photo_date'),
        ]);

        // Rich push notification con imagen a los compañeros de sala
        $challenge  = \App\Models\Challenge::find($challengeId);
        $imageUrl   = config('app.url') . '/storage/' . $path;
        $body       = $request->input('note')
            ? '"' . \Str::limit($request->input('note'), 80) . '"'
            : 'Mira el nuevo progreso 💪';

        $tokens = PushNotificationService::tokensForRoom($challengeId, $sender->id);
        PushNotificationService::send(
            $tokens,
            $sender->name . ' subió una foto 📸',
            $body,
            ['type' => 'journey_photo', 'challenge_id' => (int) $challengeId, 'photo_id' => $photo->id],
            $imageUrl,
            'mugen-social'
        );

        return response()->json([
            'entry' => [
                'id'         => $photo->id,
                'photo_path' => $photo->photo_path,
                'note'       => $photo->note,
                'photo_date' => $photo->photo_date->toDateString(),
            ]
        ], 201);
    }

    // GET /challenges/{id}/journey/all — todas las fotos de la sala (red social)
    public function allPhotos(Request $request, $challengeId)
    {
        $challenge = Challenge::findOrFail($challengeId);
        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro de esta sala.'], 403);
        }

        $userId = auth()->id();

        $photos = JourneyPhoto::where('challenge_id', $challengeId)
            ->with('user:id,name,avatar')
            ->orderByDesc('photo_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($p) use ($userId) {
                $reactions = PhotoReaction::where('journey_photo_id', $p->id)
                    ->with('user:id,name')
                    ->get()
                    ->groupBy('reaction')
                    ->map(fn($g, $emoji) => [
                        'emoji' => $emoji,
                        'count' => $g->count(),
                        'mine'  => $g->contains('user_id', $userId),
                    ])
                    ->values();

                return [
                    'id'         => $p->id,
                    'photo_path' => $p->photo_path,
                    'note'       => $p->note,
                    'photo_date' => $p->photo_date->toDateString(),
                    'user_id'    => $p->user_id,
                    'user_name'  => $p->user->name ?? '',
                    'user_avatar'=> $p->user->avatar,
                    'reactions'  => $reactions,
                    'is_own'     => $p->user_id === $userId,
                ];
            });

        return response()->json(['entries' => $photos]);
    }

    // POST /journey/{id}/react
    public function react(Request $request, $photoId)
    {
        $request->validate(['reaction' => 'required|string|max:10']);

        JourneyPhoto::findOrFail($photoId);
        $userId   = auth()->id();
        $existing = PhotoReaction::where('journey_photo_id', $photoId)->where('user_id', $userId)->first();

        if ($existing) {
            if ($existing->reaction === $request->reaction) {
                $existing->delete();
                return response()->json(['action' => 'removed']);
            }
            $existing->update(['reaction' => $request->reaction]);
            return response()->json(['action' => 'changed']);
        }

        PhotoReaction::create([
            'journey_photo_id' => $photoId,
            'user_id'          => $userId,
            'reaction'         => $request->reaction,
        ]);

        return response()->json(['action' => 'added']);
    }

    public function destroy(Request $request, $photoId)
    {
        $photo = JourneyPhoto::where('id', $photoId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        Storage::disk('public')->delete($photo->photo_path);
        $photo->delete();

        return response()->json(['message' => 'Eliminado']);
    }
}
