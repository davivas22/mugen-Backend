<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomJoinRequest extends Model
{
    protected $table = 'room_join_requests';

    protected $fillable = ['challenge_id', 'user_id', 'status'];

    public function user()      { return $this->belongsTo(User::class); }
    public function challenge() { return $this->belongsTo(Challenge::class); }
}
