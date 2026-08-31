<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Challenge;
use App\Models\SalaBattle;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SalaBattleController extends Controller
{
    // POST /battles  — crear reto
    public function store(Request $request)
    {
        $request->validate([
            'challenge_id'  => 'required|exists:challenges,id',
            'opponent_code' => 'required|string',
            'duration_days' => 'required|integer|min:7|max:30',
        ]);

        $mySala = Challenge::findOrFail($request->challenge_id);

        if (!$mySala->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'No eres miembro de esa sala.'], 403);
        }

        $opponent = Challenge::where('invite_code', strtoupper($request->opponent_code))->first();
        if (!$opponent) {
            return response()->json(['message' => 'No existe ninguna sala con ese código.'], 404);
        }
        if ($opponent->id === $mySala->id) {
            return response()->json(['message' => 'No puedes retarte a ti mismo.'], 422);
        }

        // Solo un reto activo o pendiente entre las mismas dos salas
        $existing = SalaBattle::where(function ($q) use ($mySala, $opponent) {
            $q->where('challenger_id', $mySala->id)->where('opponent_id', $opponent->id);
        })->orWhere(function ($q) use ($mySala, $opponent) {
            $q->where('challenger_id', $opponent->id)->where('opponent_id', $mySala->id);
        })->whereIn('status', ['pending', 'active'])->first();

        if ($existing) {
            return response()->json(['message' => 'Ya hay un reto activo entre estas salas.'], 422);
        }

        $start  = now()->addDay()->startOfDay()->toDateString();
        $end    = now()->addDays($request->duration_days)->endOfDay()->toDateString();

        $battle = SalaBattle::create([
            'challenger_id' => $mySala->id,
            'opponent_id'   => $opponent->id,
            'created_by'    => auth()->id(),
            'invite_code'   => strtoupper(Str::random(8)),
            'start_date'    => $start,
            'end_date'      => $end,
            'status'        => 'pending',
        ]);

        // Notificar a la sala rival
        $tokens = $opponent->members()
            ->whereNotNull('push_token')
            ->pluck('push_token')->toArray();

        if ($tokens) {
            PushNotificationService::send(
                $tokens,
                '¡Reto recibido! 🥊',
                "{$mySala->name} quiere retarlos. ¿Aceptan?",
                ['type' => 'battle', 'battle_id' => $battle->id],
                null,
                'mugen-social'
            );
        }

        return response()->json($this->formatBattle($battle), 201);
    }

    // POST /battles/{id}/accept — aceptar reto (miembro de la sala retada)
    public function accept($id)
    {
        $battle = SalaBattle::findOrFail($id);

        if ($battle->status !== 'pending') {
            return response()->json(['message' => 'Este reto ya no está pendiente.'], 422);
        }

        $opponent = Challenge::findOrFail($battle->opponent_id);
        if (!$opponent->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Solo un miembro de la sala retada puede aceptar.'], 403);
        }

        $battle->update(['status' => 'active']);

        // Notificar a la sala retadora
        $challenger = Challenge::findOrFail($battle->challenger_id);
        $tokens = $challenger->members()
            ->whereNotNull('push_token')
            ->pluck('push_token')->toArray();

        if ($tokens) {
            PushNotificationService::send(
                $tokens,
                '¡Reto aceptado! 🔥',
                "{$opponent->name} aceptó el reto. ¡Empieza mañana!",
                ['type' => 'battle', 'battle_id' => $battle->id],
                null,
                'mugen-social'
            );
        }

        return response()->json($this->formatBattle($battle->fresh()));
    }

    // GET /battles — listar retos de mis salas
    public function index()
    {
        $mySalaIds = Challenge::whereHas('members', fn($q) => $q->where('user_id', auth()->id()))
            ->pluck('id');

        $battles = SalaBattle::with(['challenger', 'opponent'])
            ->where(fn($q) => $q->whereIn('challenger_id', $mySalaIds)->orWhereIn('opponent_id', $mySalaIds))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($b) => $this->formatBattle($b));

        return response()->json($battles);
    }

    // GET /battles/{id} — stats en vivo
    public function show($id)
    {
        $battle = SalaBattle::with(['challenger.members', 'opponent.members'])->findOrFail($id);

        // Verificar que el usuario pertenece a alguna de las dos salas
        $userId = auth()->id();
        $inChallenger = $battle->challenger->members()->where('user_id', $userId)->exists();
        $inOpponent   = $battle->opponent->members()->where('user_id', $userId)->exists();

        if (!$inChallenger && !$inOpponent) {
            return response()->json(['message' => 'No eres parte de este reto.'], 403);
        }

        $since = $battle->start_date->toDateString();
        $until = min($battle->end_date->toDateString(), now()->toDateString());

        $challengerStats = $this->salaStats($battle->challenger_id, $battle->challenger->members->pluck('id')->toArray(), $since, $until, $battle);
        $opponentStats   = $this->salaStats($battle->opponent_id,   $battle->opponent->members->pluck('id')->toArray(),   $since, $until, $battle);

        // Terminar automáticamente si ya pasó la fecha
        if ($battle->status === 'active' && now()->isAfter($battle->end_date)) {
            $winnerId = $challengerStats['pct'] >= $opponentStats['pct']
                ? $battle->challenger_id
                : $battle->opponent_id;
            $battle->update(['status' => 'finished', 'winner_id' => $winnerId]);
        }

        return response()->json([
            'battle'    => $this->formatBattle($battle->fresh()),
            'challenger'=> array_merge(['sala' => ['id' => $battle->challenger_id, 'name' => $battle->challenger->name]], $challengerStats),
            'opponent'  => array_merge(['sala' => ['id' => $battle->opponent_id,   'name' => $battle->opponent->name]],   $opponentStats),
            'my_sala'   => $inChallenger ? 'challenger' : 'opponent',
        ]);
    }

    // ─── helpers ───────────────────────────────────────────────────────────────

    private function salaStats(int $salaId, array $memberIds, string $since, string $until, SalaBattle $battle): array
    {
        $totalDays = Attendance::whereIn('user_id', $memberIds)
            ->where('challenge_id', $salaId)
            ->where('status', 'confirmed')
            ->whereBetween('attended_date', [$since, $until])
            ->count();

        $daysPassed  = max(1, Carbon::parse($since)->diffInDays(Carbon::parse($until)) + 1);
        $possible    = count($memberIds) * $daysPassed;
        $totalBattle = count($memberIds) * (Carbon::parse($battle->start_date)->diffInDays($battle->end_date) + 1);

        return [
            'total_days'  => $totalDays,
            'possible'    => $possible,
            'total_battle'=> $totalBattle,
            'pct'         => $possible > 0 ? round(($totalDays / $possible) * 100) : 0,
            'member_count'=> count($memberIds),
        ];
    }

    private function formatBattle(SalaBattle $b): array
    {
        return [
            'id'             => $b->id,
            'challenger_id'  => $b->challenger_id,
            'challenger_name'=> $b->challenger?->name,
            'opponent_id'    => $b->opponent_id,
            'opponent_name'  => $b->opponent?->name,
            'start_date'     => $b->start_date?->toDateString(),
            'end_date'       => $b->end_date?->toDateString(),
            'status'         => $b->status,
            'winner_id'      => $b->winner_id,
            'invite_code'    => $b->invite_code,
        ];
    }
}
