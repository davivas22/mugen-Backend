<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JourneyPhoto extends Model
{
    protected $fillable = ['challenge_id', 'user_id', 'photo_path', 'note', 'photo_date'];

    protected $casts = [
        'photo_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }
}
