<?php

namespace App\Providers;

use App\Services\Otp\LogOtpNotifier;
use App\Services\Otp\MailOtpNotifier;
use App\Services\Otp\OtpNotifier;
use App\Services\Otp\SmsOtpNotifier;
use App\Services\Sms\HttpSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SMS transport, used by the sms OTP channel.
        $this->app->bind(SmsSender::class, function ($app) {
            return match (config('sms.driver', 'log')) {
                'http' => new HttpSmsSender(config('sms.http', [])),
                default => new LogSmsSender,
            };
        });

        // OTP delivery channel. Swapping mail for sms - or Telegram later - is
        // one env value; nothing in OtpService changes.
        $this->app->bind(OtpNotifier::class, function ($app) {
            return match (config('otp.channel', 'mail')) {
                'sms' => new SmsOtpNotifier($app->make(SmsSender::class)),
                'log' => new LogOtpNotifier,
                default => new MailOtpNotifier,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
