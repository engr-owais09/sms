<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('educore:process-trial-expiry')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('educore:backup-database --retention-days=14')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('educore:verify-db-roles')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('educore:cleanup-exports --hours=24')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();
