<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = ['challenge_id', 'user_id', 'attended_date', 'validation_type', 'status', 'photo_path', 'confirmed_by'];

    protected $casts = ['attended_date' => 'date'];

    public function user()      { return $this->belongsTo(User::class); }
    public function challenge() { return $this->belongsTo(Challenge::class); }
    public function confirmer() { return $this->belongsTo(User::class, 'confirmed_by'); }
}
