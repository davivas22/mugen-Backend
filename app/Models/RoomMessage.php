<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomMessage extends Model
{
    protected $fillable = ['challenge_id', 'sender_id', 'content'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function reads()
    {
        return $this->hasMany(MessageRead::class, 'message_id');
    }
}
