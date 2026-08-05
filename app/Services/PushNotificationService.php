<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private const EXPO_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * Envía notificación a uno o varios tokens.
     * Soporta imagen (rich notification) para Android.
     */
    public static function send(
        string|array $tokens,
        string $title,
        string $body,
        array  $data     = [],
        ?string $imageUrl = null,
        string  $channel  = 'mugen-messages'
    ): void {
        $tokens = (array) $tokens;
        $tokens = array_filter($tokens); // eliminar nulls
        if (empty($tokens)) return;

        $android = ['channelId' => $channel, 'priority' => 'high'];
        if ($imageUrl) {
            $android['imageUrl'] = $imageUrl;
        }

        $messages = array_map(fn($token) => [
            'to'      => $token,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
            'sound'   => 'default',
            'android' => $android,
        ], $tokens);

        try {
            $res = Http::withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate',
            ])->post(self::EXPO_URL, $messages);

            Log::info('[Push] Enviado a ' . count($tokens) . ' token(s). Status: ' . $res->status());
            if ($res->failed()) {
                Log::warning('[Push] Respuesta: ' . $res->body());
            }
        } catch (\Throwable $e) {
            Log::error('[Push] Error: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene los tokens de push de todos los miembros de una sala,
     * excluyendo opcionalmente al remitente.
     */
    public static function tokensForRoom(int $challengeId, ?int $excludeUserId = null): array
    {
        $query = \DB::table('challenge_members')
            ->join('users', 'users.id', '=', 'challenge_members.user_id')
            ->where('challenge_members.challenge_id', $challengeId)
            ->whereNotNull('users.push_token');

        if ($excludeUserId) {
            $query->where('users.id', '!=', $excludeUserId);
        }

        return $query->pluck('users.push_token')->all();
    }
}
