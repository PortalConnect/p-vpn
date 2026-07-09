<?php

namespace PortalConnect\Payments\Tests;

use PortalConnect\Payments\Gateways\Cardlink\WebhookVerifier;
use Illuminate\Http\Request;
use Tests\TestCase;

class WebhookVerifierTest extends TestCase
{
    private WebhookVerifier $verifier;
    private string $key = 'top-secret-signature-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cardlink.signature_key' => $this->key]);
        $this->verifier = new WebhookVerifier();
    }

    public function test_verify_returns_true_for_valid_signature_header(): void
    {
        $body = '{"bill_id":"bill_1","payment_id":"pay_1","status":"SUCCESS","amount":200.00,"order_id":"42"}';
        $signature = hash_hmac('sha256', $body, $this->key);

        $request = Request::create('/webhooks/cardlink', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $this->assertTrue($this->verifier->verify($request));
    }

    public function test_verify_returns_false_for_invalid_signature(): void
    {
        $body = '{"a":1}';
        $request = Request::create('/webhooks/cardlink', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => 'totally-wrong',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $this->assertFalse($this->verifier->verify($request));
    }

    public function test_verify_returns_false_when_signature_header_missing(): void
    {
        $request = Request::create('/webhooks/cardlink', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        $this->assertFalse($this->verifier->verify($request));
    }

    public function test_verify_returns_false_when_signature_key_not_configured(): void
    {
        config(['cardlink.signature_key' => '']);
        $verifier = new WebhookVerifier();

        $request = Request::create('/webhooks/cardlink', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => 'whatever',
        ], '{}');

        $this->assertFalse($verifier->verify($request));
    }
}
