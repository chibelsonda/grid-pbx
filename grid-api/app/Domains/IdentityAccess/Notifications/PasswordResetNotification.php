<?php

namespace App\Domains\IdentityAccess\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;

final class PasswordResetNotification extends ResetPassword implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your GridPBX password')
            ->greeting('Reset your GridPBX password')
            ->line('We received a request to reset the password for your GridPBX account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in '
                .config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
            ->line('If you did not request a password reset, you can safely ignore this message.')
            ->salutation('GridPBX');
    }
}
