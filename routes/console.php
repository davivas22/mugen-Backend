<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Aviso de racha en peligro — todos los días a las 8pm
Schedule::command('streak:warn')->dailyAt('20:00');

// El Fantasma — detectar miembros ausentes 3+ días, notificar a las 7pm
Schedule::command('mugen:check-ghosts')->dailyAt('19:00');
