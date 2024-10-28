<?php

namespace App\Console\Commands;

use App\Jobs\RegisterBotJob;
use Illuminate\Console\Command;

class DispatchRegisterBotJob extends Command
{
    protected $signature = 'register-bot:run';
    protected $description = 'Dispatch the RegisterBotJob';

    public function handle()
    {
        RegisterBotJob::dispatch();
        $this->info('RegisterBotJob dispatched successfully.');
    }
}
