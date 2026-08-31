<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaBattle extends Model
{
    protected $fillable = [
        'challenger_id', 'opponent_id', 'created_by',
        'invite_code', 'start_date', 'end_date', 'status', 'winner_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function challenger() { return $this->belongsTo(Challenge::class, 'challenger_id'); }
    public function opponent()   { return $this->belongsTo(Challenge::class, 'opponent_id'); }
    public function createdBy()  { return $this->belongsTo(User::class, 'created_by'); }
    public function winner()     { return $this->belongsTo(Challenge::class, 'winner_id'); }
}
