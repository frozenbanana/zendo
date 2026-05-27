<?php

use App\Modules\Payments\Enums\InvoiceStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\InvoiceLineItem;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Models\StripeWebhook;
use App\Modules\Tenancy\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);
    app()->instance(Tenant::class, $this->tenant);
});

describe('Invoice', function () {
    test('can create an invoice', function () {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'status' => InvoiceStatus::PENDING,
            'total_cents' => 10000,
            'currency' => 'EUR',
        ]);

        expect($invoice)->toBeInstanceOf(Invoice::class);
        expect($invoice->status)->toBe(InvoiceStatus::PENDING);
        expect($invoice->total_cents)->toBe(10000);
    });

    test('invoice has line items', function () {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'status' => InvoiceStatus::PENDING,
            'total_cents' => 15000,
            'currency' => 'EUR',
        ]);

        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Event Registration',
            'quantity' => 1,
            'unit_price_cents' => 15000,
            'total_cents' => 15000,
            'type' => 'event',
        ]);

        expect($invoice->lineItems)->toHaveCount(1);
        expect($invoice->lineItems->first()->description)->toBe('Event Registration');
    });

    test('invoice status checks work', function () {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'status' => InvoiceStatus::PENDING,
            'total_cents' => 5000,
            'currency' => 'EUR',
        ]);

        expect($invoice->isPending())->toBeTrue();
        expect($invoice->isPaid())->toBeFalse();

        $invoice->update(['status' => InvoiceStatus::PAID]);
        expect($invoice->fresh()->isPaid())->toBeTrue();
    });
});

describe('Payment', function () {
    test('can create a payment for an invoice', function () {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'status' => InvoiceStatus::PENDING,
            'total_cents' => 10000,
            'currency' => 'EUR',
        ]);

        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'method' => 'card',
            'amount_cents' => 10000,
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
        ]);

        expect($payment)->toBeInstanceOf(Payment::class);
        expect($invoice->fresh()->payment)->not->toBeNull();
        expect($payment->isCompleted())->toBeFalse();
    });

    test('payment status checks work', function () {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'status' => InvoiceStatus::PENDING,
            'total_cents' => 10000,
            'currency' => 'EUR',
        ]);

        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'method' => 'card',
            'amount_cents' => 10000,
            'currency' => 'EUR',
            'status' => PaymentStatus::COMPLETED,
        ]);

        expect($payment->isCompleted())->toBeTrue();
        expect($payment->isFailed())->toBeFalse();
    });
});

describe('Refund', function () {
    test('can create a refund for a payment', function () {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'status' => InvoiceStatus::PAID,
            'total_cents' => 10000,
            'currency' => 'EUR',
        ]);

        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'method' => 'card',
            'amount_cents' => 10000,
            'currency' => 'EUR',
            'status' => PaymentStatus::COMPLETED,
        ]);

        $refund = Refund::create([
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_cents' => 5000,
            'reason' => 'Partial cancellation',
            'status' => 'PENDING',
        ]);

        expect($refund)->toBeInstanceOf(Refund::class);
        expect($refund->payment->id)->toBe($payment->id);
        expect($refund->invoice->id)->toBe($invoice->id);
    });
});

describe('StripeWebhook', function () {
    test('can create and check webhook status', function () {
        $webhook = StripeWebhook::create([
            'stripe_event_id' => 'evt_test_'.str()->random(12),
            'type' => 'checkout.session.completed',
            'payload' => ['data' => ['object' => ['id' => 'cs_test_123']]],
            'status' => 'PENDING',
        ]);

        expect($webhook)->toBeInstanceOf(StripeWebhook::class);
        expect($webhook->isProcessed())->toBeFalse();
        expect($webhook->isFailed())->toBeFalse();
    });
});
