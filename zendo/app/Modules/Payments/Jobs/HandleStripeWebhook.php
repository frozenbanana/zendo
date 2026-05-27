<?php

namespace App\Modules\Payments\Jobs;

use App\Modules\Payments\Enums\InvoiceStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\WebhookProcessStatus;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\StripeWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleStripeWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $stripeWebhookId,
    ) {}

    public function handle(): void
    {
        $webhook = StripeWebhook::findOrFail($this->stripeWebhookId);

        if ($webhook->isProcessed()) {
            return;
        }

        try {
            match ($webhook->type) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($webhook),
                'payment.intent.succeeded' => $this->handlePaymentIntentSucceeded($webhook),
                'payment.intent.payment_failed' => $this->handlePaymentIntentFailed($webhook),
                'charge.refunded' => $this->handleChargeRefunded($webhook),
                default => Log::info("Unhandled Stripe event type: {$webhook->type}"),
            };

            $webhook->update(['status' => WebhookProcessStatus::PROCESSED]);
        } catch (\Throwable $e) {
            $webhook->update([
                'status' => WebhookProcessStatus::FAILED,
                'error' => $e->getMessage(),
            ]);

            Log::error("Failed processing Stripe webhook {$webhook->stripe_event_id}: {$e->getMessage()}");

            throw $e;
        }
    }

    protected function handleCheckoutSessionCompleted(StripeWebhook $webhook): void
    {
        $payload = $webhook->payload;
        $sessionId = data_get($payload, 'data.object.id');

        $invoice = Invoice::where('stripe_checkout_session_id', $sessionId)->first();

        if ($invoice) {
            $invoice->update(['status' => InvoiceStatus::PAID]);
        }
    }

    protected function handlePaymentIntentSucceeded(StripeWebhook $webhook): void
    {
        $payload = $webhook->payload;
        $paymentIntentId = data_get($payload, 'data.object.id');

        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if ($payment) {
            $payment->update(['status' => PaymentStatus::COMPLETED]);
        }
    }

    protected function handlePaymentIntentFailed(StripeWebhook $webhook): void
    {
        $payload = $webhook->payload;
        $paymentIntentId = data_get($payload, 'data.object.id');

        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if ($payment) {
            $payment->update(['status' => PaymentStatus::FAILED]);
        }
    }

    protected function handleChargeRefunded(StripeWebhook $webhook): void
    {
        $payload = $webhook->payload;
        $paymentIntentId = data_get($payload, 'data.object.payment_intent');

        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if ($payment) {
            $payment->update(['status' => PaymentStatus::REFUNDED]);
            $payment->invoice->update(['status' => InvoiceStatus::REFUNDED]);
        }
    }
}
