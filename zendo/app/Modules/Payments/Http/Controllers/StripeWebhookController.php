<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Payments\Jobs\HandleStripeWebhook;
use App\Modules\Payments\Models\StripeWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $secret,
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $stripeEventId = $event->id;

        if (StripeWebhook::where('stripe_event_id', $stripeEventId)->exists()) {
            return response()->json(['message' => 'Already processed'], 200);
        }

        $webhook = StripeWebhook::create([
            'stripe_event_id' => $stripeEventId,
            'type' => $event->type,
            'payload' => $event->toArray(),
            'status' => 'PENDING',
        ]);

        HandleStripeWebhook::dispatch($webhook->id);

        return response()->json(['message' => 'Webhook received'], 200);
    }
}
