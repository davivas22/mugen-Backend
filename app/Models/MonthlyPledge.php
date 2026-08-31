<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyPledge extends Model
{
    protected $fillable = ['user_id', 'challenge_id', 'target_days', 'month', 'year'];

    protected $casts = [
        'target_days' => 'integer',
        'month'       => 'integer',
        'year'        => 'integer',
    ];

    public function user()      { return $this->belongsTo(User::class); }
    public function challenge() { return $this->belongsTo(Challenge::class); }
}
