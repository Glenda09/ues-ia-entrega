<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $to;
    public string $subject;
    public string $html;

    public function __construct(string $to, string $subject, string $html)
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->html = $html;
    }

    public function handle(): void
    {
        // Enviar correo HTML
        Mail::html($this->html, function ($message) {
            $message->to($this->to)
                ->subject($this->subject);
        });

        Log::channel('mail')->info('Email enviado', [
            'to' => $this->to,
            'subject' => $this->subject,
        ]);
    }
}
