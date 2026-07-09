<?php

namespace PortalConnect\Payments\Gateways\Cardlink;

use Illuminate\Http\Request;

class WebhookVerifier
{
    public function verify(Request $request): bool
    {
        $key = (string) config('cardlink.signature_key', '');
        if ($key === '') {
            return false;
        }

        $provided = (string) $request->header('X-Signature', '');
        if ($provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $key);

        return hash_equals($expected, $provided);
    }
}
