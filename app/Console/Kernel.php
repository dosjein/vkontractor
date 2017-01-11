<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Config;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\MessageResponse::class,  
        Commands\Trigger::class 
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('vk:messages')
                  ->everyMinute();
        //guess for debug reasons - had incident on GroupChat leave message hang
        $schedule->command('cache:clear')
                  ->everyTenMinutes();



        //!!!! REBELISH FEATURE LIST
        if (Config::get('app.mode') == 'rebel'){

                $schedule->command('cache:clear 1 1 1')
                          ->everyTenMinutes();
                $schedule->command('vk:trigger')
                  ->daily();            
        }

    }
}
