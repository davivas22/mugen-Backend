<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyCommitment extends Model
{
    protected $fillable = ['user_id', 'challenge_id', 'week_start', 'committed_days'];

    protected $casts = [
        'week_start'     => 'date',
        'committed_days' => 'array',
    ];

    public function user()      { return $this->belongsTo(User::class); }
    public function challenge() { return $this->belongsTo(Challenge::class); }
}
