<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBadge extends Model
{
    protected $fillable = ['user_id', 'badge_key', 'challenge_id', 'unlocked_at'];

    protected $casts = ['unlocked_at' => 'datetime'];

    public function user()      { return $this->belongsTo(User::class); }
    public function challenge() { return $this->belongsTo(Challenge::class); }
}
