<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Scraping
|--------------------------------------------------------------------------
|
| Scraping otomatis dijalankan setiap minggu (Senin jam 02:00 WIB).
| Untuk mengaktifkan, pastikan cron entry berikut sudah ditambahkan:
|
| * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
| Atau di Windows Task Scheduler, jalankan setiap menit:
| php artisan schedule:run
|
*/

Schedule::command('scraping:run')
    ->weeklyOn(1, '02:00')    // Senin jam 02:00
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scraping-schedule.log'));

