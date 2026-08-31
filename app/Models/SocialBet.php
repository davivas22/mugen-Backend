<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialBet extends Model
{
    protected $fillable = [
        'challenge_id',
        'creator_id',
        'target_user_id',
        'bet_type',
        'description',
        'stake',
        'status',
        'outcome',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'outcome'     => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }
}
