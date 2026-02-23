<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('rate:create')->hourly();

Schedule::command('trip:remainder')->hourly();

Schedule::command('rating:availables')->everyMinute();

Schedule::command('trip:request')->dailyAt('12:00')->timezone('America/Argentina/Buenos_Aires');

Schedule::command('trip:request')->dailyAt('19:00')->timezone('America/Argentina/Buenos_Aires');

Schedule::command('trip:visibilityclean')->dailyAt('03:00')->timezone('America/Argentina/Buenos_Aires');

// $schedule->command('georoute:build')->everyMinute();

Schedule::command('node:buildweights')->hourly();

Schedule::command('messages:email')->everyTenMinutes();

// TODO: add a job to check if trip is awaiting_payment after 30 minutes and send push/email?
// Evaluate badges daily at 2 AM
// Schedule::command('badges:evaluate')->dailyAt('02:00')->timezone('America/Argentina/Buenos_Aires');

// Calculate active users per month on the 1st of each month at 3 AM
Schedule::command('users:calculate-active-per-month')->monthlyOn(1, '03:00')->timezone('America/Argentina/Buenos_Aires');

// Clean up expired password reset tokens daily at 4 AM
Schedule::command('auth:cleanup-reset-tokens')->dailyAt('04:00')->timezone('America/Argentina/Buenos_Aires');
