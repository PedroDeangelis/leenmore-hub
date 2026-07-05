<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull completed ESIGNON (전자위임) documents into the roster every hour.
Schedule::command('esignon:sync')->hourly()->withoutOverlapping();
