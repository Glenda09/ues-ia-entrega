<?php

namespace App\Tasks;
use Illuminate\Support\Facades\Log;

class TestTask
{
    public function execute()
    {
        Log::channel('system')->info('Schedules en ejecución');
    }
}
