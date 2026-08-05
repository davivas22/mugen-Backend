<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\MessageRead;
use App\Models\RoomMessage;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class RoomMessageController extends Controller
{
    // POST /challenges/{id}/messages
    public function store(Request $request, $challengeId)
    {
        $request->validate(['content' => 'required|string|max:500']);

        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro de esta sala.'], 403);
        }

        $message = RoomMessage::create([
            'challenge_id' => $challengeId,
            'sender_id'    => auth()->id(),
            'content'      => $request->content,
        ]);

        // El sender ya lo "leyó"
        MessageRead::create(['message_id' => $message->id, 'user_id' => auth()->id()]);

        $message->load('sender:id,name,avatar');

        // Push notification a los demás miembros de la sala
        $tokens = PushNotificationService::tokensForRoom($challengeId, auth()->id());
        PushNotificationService::send(
            $tokens,
            $message->sender->name . ' · ' . $challenge->name,
            $request->content,
            ['type' => 'room_message', 'challenge_id' => (int) $challengeId],
            null,
            'mugen-messages'
        );

        return response()->json([
            'message' => [
                'id'           => $message->id,
                'sender_id'    => $message->sender_id,
                'sender_name'  => $message->sender->name ?? '',
                'sender_avatar'=> $message->sender->avatar,
                'content'      => $message->content,
                'created_at'   => $message->created_at->toISOString(),
            ]
        ], 201);
    }

    // GET /challenges/{id}/messages — historial de sala
    public function index(Request $request, $challengeId)
    {
        $challenge = Challenge::findOrFail($challengeId);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro de esta sala.'], 403);
        }

        $userId = auth()->id();

        $messages = RoomMessage::where('challenge_id', $challengeId)
            ->with('sender:id,name,avatar')
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn($m) => [
                'id'            => $m->id,
                'sender_id'     => $m->sender_id,
                'sender_name'   => $m->sender->name ?? '',
                'sender_avatar' => $m->sender->avatar,
                'content'       => $m->content,
                'is_own'        => $m->sender_id === $userId,
                'created_at'    => $m->created_at->toISOString(),
            ]);

        // Marcar todos como leídos
        $ids = $messages->pluck('id')->all();
        if (!empty($ids)) {
            foreach ($ids as $mid) {
                MessageRead::firstOrCreate(['message_id' => $mid, 'user_id' => $userId]);
            }
        }

        return response()->json(['messages' => $messages]);
    }

    // GET /messages/inbox — mensajes no leídos de todas las salas
    public function inbox(Request $request)
    {
        $userId = auth()->id();

        $challengeIds = Challenge::whereHas('members', fn($q) => $q->where('users.id', $userId))
            ->pluck('id');

        $unread = RoomMessage::whereIn('challenge_id', $challengeIds)
            ->where('sender_id', '!=', $userId)
            ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $userId))
            ->with('sender:id,name,avatar', 'challenge:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($m) => [
                'id'             => $m->id,
                'challenge_id'   => $m->challenge_id,
                'challenge_name' => $m->challenge->name ?? '',
                'sender_name'    => $m->sender->name ?? '',
                'sender_avatar'  => $m->sender->avatar,
                'content'        => $m->content,
                'created_at'     => $m->created_at->toISOString(),
            ]);

        return response()->json(['messages' => $unread, 'count' => $unread->count()]);
    }

    // POST /messages/{id}/read
    public function markRead($messageId)
    {
        $message   = RoomMessage::findOrFail($messageId);
        $challenge = Challenge::findOrFail($message->challenge_id);

        if (!$challenge->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        MessageRead::firstOrCreate(['message_id' => $messageId, 'user_id' => auth()->id()]);

        return response()->json(['ok' => true]);
    }

    // POST /messages/read-all
    public function markAllRead()
    {
        $userId      = auth()->id();
        $challengeIds = Challenge::whereHas('members', fn($q) => $q->where('users.id', $userId))->pluck('id');

        $unreadIds = RoomMessage::whereIn('challenge_id', $challengeIds)
            ->where('sender_id', '!=', $userId)
            ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $userId))
            ->pluck('id');

        foreach ($unreadIds as $mid) {
            MessageRead::firstOrCreate(['message_id' => $mid, 'user_id' => $userId]);
        }

        return response()->json(['marked' => $unreadIds->count()]);
    }
}
