<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Daily Reports Scheduler
// Runs every minute and sends reports to salons that are due based on their configured daily_report_time.
Schedule::command('reports:send-daily')
    ->everyMinute()
    ->timezone('Europe/Sarajevo')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Daily reports sent successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Daily reports failed to send');
    });

// Appointment Reminders Scheduler
// Day-before reminder: every day at 18:00 Sarajevo time
Schedule::command('appointments:send-reminders --type=day_before')
    ->dailyAt('18:00')
    ->timezone('Europe/Sarajevo')
    ->name('appointments-reminders-day-before')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Day-before appointment reminders sent successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Day-before appointment reminders failed to send');
    });

// Same-day reminder: every day at 08:00 Sarajevo time
Schedule::command('appointments:send-reminders --type=same_day')
    ->dailyAt('08:00')
    ->timezone('Europe/Sarajevo')
    ->name('appointments-reminders-same-day')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Same-day appointment reminders sent successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Same-day appointment reminders failed to send');
    });

// Auto-complete expired appointments
// Runs every 5 minutes to keep analytics and revenue consistent.
Schedule::command('appointments:complete-expired')
    ->everyFiveMinutes()
    ->timezone('Europe/Sarajevo')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Auto-complete expired appointments command failed');
    });
