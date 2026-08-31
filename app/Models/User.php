<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Challenge;
use App\Models\Workout;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'birthdate',
        'username', 'bio', 'weight', 'height', 'avatar', 'is_admin',
        'push_token', 'google_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate'         => 'date',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
        ];
    }

    public function workouts()    { return $this->hasMany(Workout::class); }
    public function attendances() { return $this->hasMany(\App\Models\Attendance::class); }
    public function badges()      { return $this->hasMany(\App\Models\UserBadge::class); }

    public function challenges()
    {
        return $this->belongsToMany(Challenge::class, 'challenge_members')
                    ->withPivot('joined_at')
                    ->withTimestamps();
    }
}
