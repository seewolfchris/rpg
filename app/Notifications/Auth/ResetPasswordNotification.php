<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use InvalidArgumentException;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        if (! $notifiable instanceof CanResetPassword) {
            throw new InvalidArgumentException('Reset password notification requires CanResetPassword notifiable.');
        }

        $userName = $notifiable instanceof User
            ? $notifiable->name
            : 'Nutzer';
        $expirationMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Passwort zurücksetzen | '.config('app.name'))
            ->view('mail.auth.password-reset', [
                'appName' => config('app.name', 'C76-RPG'),
                'userName' => $userName,
                'resetUrl' => $this->resetUrl($notifiable),
                'expirationMinutes' => $expirationMinutes,
            ]);
    }

    /**
     * @param  CanResetPassword  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
