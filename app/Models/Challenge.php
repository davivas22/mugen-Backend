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
        'gym_lat',
        'gym_lng',
        'gym_radius_meters',
        'enable_bets',
        'is_private',
    ];

    protected $casts = [
        'gym_days_per_week' => 'array',
        'use_location'      => 'boolean',
        'use_camera'        => 'boolean',
        'enable_bets'       => 'boolean',
        'is_private'        => 'boolean',
        'start_date'        => 'date',
        'gym_lat'           => 'float',
        'gym_lng'           => 'float',
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

    public function joinRequests()
    {
        return $this->hasMany(RoomJoinRequest::class);
    }

    public static function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }
}
