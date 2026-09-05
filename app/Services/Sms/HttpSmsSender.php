<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generic HTTP driver.
 *
 * Most Indian SMS gateways (MSG91, Fast2SMS, TextLocal, Gupshup) accept a
 * flat GET or POST of key/value pairs, so the endpoint, method and parameter
 * names are all configuration rather than code. Set them in config/sms.php
 * and no class needs to change to switch provider.
 */
class HttpSmsSender implements SmsSender
{
    public function __construct(private readonly array $config)
    {
    }

    public function send(string $phoneNumber, string $message): bool
    {
        $endpoint = (string) ($this->config['endpoint'] ?? '');

        if ($endpoint === '') {
            Log::error('[SMS] No endpoint configured for the http driver.');

            return false;
        }

        // Provider-specific field names are mapped here rather than hardcoded.
        $payload = array_merge($this->config['params'] ?? [], [
            ($this->config['to_field'] ?? 'to') => $phoneNumber,
            ($this->config['message_field'] ?? 'message') => $message,
        ]);

        try {
            $request = Http::timeout((int) ($this->config['timeout'] ?? 10))
                ->withHeaders($this->config['headers'] ?? []);

            $response = strtolower($this->config['method'] ?? 'post') === 'get'
                ? $request->get($endpoint, $payload)
                : $request->asForm()->post($endpoint, $payload);

            if ($response->successful()) {
                return true;
            }

            Log::error('[SMS] Provider rejected the message.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            // Never let a gateway outage become a 500 mid-login.
            Log::error('[SMS] Delivery failed: ' . $e->getMessage(), ['exception' => $e]);
        }

        return false;
    }
}
