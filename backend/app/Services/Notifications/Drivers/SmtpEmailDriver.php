<?php

declare(strict_types=1);

namespace App\Services\Notifications\Drivers;

use App\Services\Notifications\Contracts\NotificationDriverInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SmtpEmailDriver implements NotificationDriverInterface
{
    public function send(string $recipient, string $content, ?string $subject = null): string
    {
        $emailSubject = $subject ?? 'ISP Carrier Notification';

        Mail::raw($content, function ($message) use ($recipient, $emailSubject): void {
            $message->to($recipient)
                ->subject($emailSubject);
        });

        return 'smtp_msg_'.Str::random(16);
    }
}
