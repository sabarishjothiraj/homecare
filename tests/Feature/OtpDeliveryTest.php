<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Config;
use App\Models\User;
use App\Services\Otp\LogOtpNotifier;
use App\Services\Otp\MailOtpNotifier;
use App\Services\Otp\OtpMessage;
use App\Services\Otp\SmsOtpNotifier;
use App\Services\Sms\HttpSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The OTP delivery layer.
 *
 * Two layers, deliberately separate:
 *
 *   channel   (OtpNotifier)  - mail | sms | log. Owns the destination lookup
 *                              and decides what a message looks like.
 *   transport (SmsSender)    - http | log. Only used by the sms channel; owns
 *                              how bytes reach a provider.
 *
 * That is why "log" appears at both layers and means different things:
 * OTP_CHANNEL=log skips delivery entirely, while OTP_CHANNEL=sms with
 * SMS_DRIVER=log exercises the SMS channel without a provider account.
 */
class OtpDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function message(): OtpMessage
    {
        return new OtpMessage(
            user: User::factory()->create(['name' => 'Ravi Kumar', 'email' => 'ravi@example.com']),
            code: '482913',
            expiresInMinutes: 5,
            requestedAt: now(),
            ipAddress: '203.0.113.9',
        );
    }

    // ---------------------------------------------------------------- mail

    public function test_the_mail_channel_reads_its_address_from_admin_config(): void
    {
        Config::setValue('otp_email', 'owner@example.com');

        $this->assertSame('owner@example.com', (new MailOtpNotifier)->destination());
    }

    public function test_the_mail_channel_is_unconfigured_when_the_address_is_blank(): void
    {
        Config::setValue('otp_email', '');

        $this->assertNull((new MailOtpNotifier)->destination());
    }

    public function test_the_mail_channel_rejects_a_malformed_address(): void
    {
        Config::setValue('otp_email', 'not-an-address');

        $this->assertNull((new MailOtpNotifier)->destination());
    }

    public function test_the_mail_channel_sends_to_the_configured_approver(): void
    {
        Mail::fake();
        Config::setValue('otp_email', 'owner@example.com');

        $this->assertTrue((new MailOtpNotifier)->send($this->message()));

        Mail::assertSent(OtpMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    }

    /**
     * The subject carries the requester's name so the owner can triage from a
     * notification preview without opening the mail.
     */
    public function test_the_email_names_the_requester_and_carries_the_code(): void
    {
        Mail::fake();
        Config::setValue('otp_email', 'owner@example.com');

        (new MailOtpNotifier)->send($this->message());

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) {
            $subject = $mail->envelope()->subject;

            return str_contains($subject, 'Ravi Kumar') && str_contains($subject, '482913');
        });
    }

    public function test_the_mail_channel_sends_nothing_when_unconfigured(): void
    {
        Mail::fake();
        Config::setValue('otp_email', '');

        $this->assertFalse((new MailOtpNotifier)->send($this->message()));

        Mail::assertNothingSent();
    }

    /**
     * A dead SMTP host must read as "could not send", never as a 500 in the
     * middle of authenticating.
     */
    public function test_a_mail_transport_failure_is_reported_not_thrown(): void
    {
        Config::setValue('otp_email', 'owner@example.com');

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));

        $this->assertFalse((new MailOtpNotifier)->send($this->message()));
    }

    // ----------------------------------------------------------------- sms

    public function test_the_sms_channel_reads_its_number_from_admin_config(): void
    {
        Config::setValue('otp_phone_number', '+919876543210');

        $notifier = new SmsOtpNotifier($this->recordingSender());

        $this->assertSame('+919876543210', $notifier->destination());
    }

    public function test_the_sms_channel_sends_the_plain_text_message(): void
    {
        Config::setValue('otp_phone_number', '+919876543210');
        $sender = $this->recordingSender();

        $this->assertTrue((new SmsOtpNotifier($sender))->send($this->message()));

        $this->assertSame('+919876543210', $sender->to);
        $this->assertStringContainsString('482913', $sender->body);
        $this->assertStringContainsString('Ravi Kumar', $sender->body);
    }

    public function test_the_sms_channel_sends_nothing_when_no_number_is_set(): void
    {
        Config::setValue('otp_phone_number', '');
        $sender = $this->recordingSender();

        $this->assertFalse((new SmsOtpNotifier($sender))->send($this->message()));
        $this->assertNull($sender->to);
    }

    // ----------------------------------------------------------------- log

    public function test_the_log_channel_is_always_available(): void
    {
        $notifier = new LogOtpNotifier;

        $this->assertNotNull($notifier->destination());
        $this->assertTrue($notifier->send($this->message()));
    }

    // ------------------------------------------------------- http transport

    private function httpSender(array $overrides = []): HttpSmsSender
    {
        return new HttpSmsSender(array_merge([
            'endpoint' => 'https://sms.example.test/send',
            'method' => 'post',
            'to_field' => 'numbers',
            'message_field' => 'message',
            'params' => ['api_key' => 'k-123', 'route' => 'otp'],
            'headers' => ['authorization' => 'token-abc'],
        ], $overrides));
    }

    public function test_the_http_transport_posts_the_provider_field_names(): void
    {
        Http::fake(['*' => Http::response(['return' => true], 200)]);

        $this->assertTrue($this->httpSender()->send('+919876543210', 'Your code is 482913'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.example.test/send'
                && $request->method() === 'POST'
                && $request['numbers'] === '+919876543210'
                && $request['message'] === 'Your code is 482913'
                && $request['api_key'] === 'k-123'
                && $request->hasHeader('authorization', 'token-abc');
        });
    }

    public function test_the_http_transport_can_use_get_for_providers_that_want_it(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);

        $this->assertTrue($this->httpSender(['method' => 'get'])->send('+91987', 'hi'));

        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    public function test_the_http_transport_refuses_to_send_with_no_endpoint(): void
    {
        Http::fake();

        $this->assertFalse($this->httpSender(['endpoint' => ''])->send('+91987', 'hi'));

        Http::assertNothingSent();
    }

    public function test_a_provider_rejection_is_reported_as_failure(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid key'], 401)]);

        $this->assertFalse($this->httpSender()->send('+91987', 'hi'));
    }

    /**
     * A gateway outage must not escape as an exception mid-login.
     */
    public function test_a_connection_failure_is_swallowed_and_reported(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertFalse($this->httpSender()->send('+91987', 'hi'));
    }

    // --------------------------------------------------------------- helper

    /**
     * Captures what the sms channel handed to the transport.
     */
    private function recordingSender(): object
    {
        return new class implements SmsSender
        {
            public ?string $to = null;

            public ?string $body = null;

            public function send(string $phoneNumber, string $message): bool
            {
                $this->to = $phoneNumber;
                $this->body = $message;

                return true;
            }
        };
    }
}
