<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Challenge extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'invite_code',
        'cover_image',
        'duration_days',
        'start_date',
        'gym_days_per_week',
        'challenge_mode',
        'use_location',
        'meeting_point',
        'use_camera',
    ];

    protected $casts = [
        'gym_days_per_week' => 'array',
        'use_location'      => 'boolean',
        'use_camera'        => 'boolean',
        'start_date'        => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'challenge_members')
                    ->withPivot('joined_at')
                    ->withTimestamps();
    }

    public static function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }
}
