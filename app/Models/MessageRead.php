<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['message_id', 'user_id', 'read_at'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->read_at = now();
        });
    }
}
