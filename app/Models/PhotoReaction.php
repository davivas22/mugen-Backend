<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoReaction extends Model
{
    protected $fillable = ['journey_photo_id', 'user_id', 'reaction'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
