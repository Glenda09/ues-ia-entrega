<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use Illuminate\Support\Facades\Bus;

class EmailService
{
    /**
     * Encola el envío de un correo HTML.
     */
    public function sendHtmlEmail(string $to, string $subject, string $html): void
    {
        Bus::dispatch(new SendEmailJob($to, $subject, $html));
    }
}
