<?php

use Illuminate\Support\Facades\Schedule;

// Schedule daily report generation setiap hari jam 00:30 (setelah tengah malam)
Schedule::command('report:generate-daily')->dailyAt('00:30');

// Atau bisa juga manual trigger via cron job:
// 0 1 * * * cd /path-to-project && php artisan report:generate-daily