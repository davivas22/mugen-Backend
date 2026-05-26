<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workout extends Model
{
    protected $fillable = ['user_id', 'title', 'duration_minutes', 'calories', 'icon_name', 'challenge_id', 'reps'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
