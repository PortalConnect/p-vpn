<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\Exceptions\WebhookRejectedException;
use App\Services\Payments\PaymentFulfillmentService;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Единый вебхук для всех платёжных провайдеров: /webhooks/payment/{provider}.
 * Провайдер отвечает за подпись и разбор, fulfillment общий.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentManager $manager,
        private PaymentFulfillmentService $fulfillment,
        private PaymentService $payments,
    ) {
    }

    public function handle(Request $request, string $provider): Response
    {
        if (!$this->manager->has($provider)) {
            return response('unknown provider', 404);
        }

        $gateway = $this->manager->provider($provider);

        try {
            $result = $gateway->parseWebhook($request);

            $payment = $this->payments->findForWebhook($provider, $result);
            if (!$payment) {
                Log::warning('payment webhook: payment not found', [
                    'provider' => $provider,
                    'order_id' => $result->orderId,
                    'external_id' => $result->externalId,
                ]);
                return response('unknown payment', 404);
            }

            $outcome = $this->fulfillment->apply($payment, $result);

            Log::info('payment webhook processed', [
                'provider' => $provider,
                'payment_id' => $payment->id,
                'outcome' => $outcome,
            ]);

            return $gateway->successResponse();
        } catch (WebhookRejectedException $e) {
            Log::warning('payment webhook rejected', [
                'provider' => $provider,
                'reason' => $e->getMessage(),
            ]);
            return response($e->getMessage(), $e->httpStatus);
        }
    }

}
