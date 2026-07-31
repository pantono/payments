<?php

namespace Pantono\Payments\Tests\Stripe;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentStatus;
use Pantono\Payments\Payments;
use PHPUnit\Framework\TestCase;
use Stripe\Event;
use Pantono\Payments\Provider\Stripe\StripeWebhookProcessor;

class StripeWebhookProcessorTest extends TestCase
{
    public function testPaymentIntentSucceededSetsCardDataAuthCodeAndPaymentMethodName(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'charges' => [
                'data' => [
                    [
                        'payment_method_details' => [
                            'type' => 'card',
                            'card' => [
                                'brand' => 'visa',
                                'display_brand' => 'Visa',
                                'last4' => '4242',
                                'exp_month' => 12,
                                'exp_year' => 2030,
                                'authorization_code' => '123456',
                                'network_transaction_id' => 'net_123',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            'brand' => 'visa',
            'display_brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'authorization_code' => '123456',
            'network_transaction_id' => 'net_123',
        ], $payment->getCardData());
        $this->assertSame('123456', $payment->getAuthCode());
        $this->assertSame('Visa ending 4242', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededSetsCardDataFromLatestCharge(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'card',
                    'card' => [
                        'brand' => 'mastercard',
                        'last4' => '4444',
                        'network_transaction_id' => 'net_456',
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            'brand' => 'mastercard',
            'last4' => '4444',
            'network_transaction_id' => 'net_456',
        ], $payment->getCardData());
        $this->assertSame('net_456', $payment->getAuthCode());
        $this->assertSame('mastercard ending 4444', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededWithoutCardDataDoesNotSetCardFields(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => 'ch_123',
        ]);

        $this->assertSame([], $payment->getCardData());
        $this->assertNull($payment->getAuthCode());
        $this->assertNull($payment->getPaymentMethodName());
    }

    private function processPaymentIntentSucceeded(array $paymentIntentData): Payment
    {
        $payment = new Payment();
        $status = new PaymentStatus();
        $event = Event::constructFrom([
            'id' => 'evt_123',
            'object' => 'event',
            'type' => Event::PAYMENT_INTENT_SUCCEEDED,
            'data' => [
                'object' => array_merge([
                    'id' => 'pi_123',
                    'object' => 'payment_intent',
                ], $paymentIntentData),
            ],
        ]);

        $payments = $this->getMockBuilder(Payments::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPaymentStatusById', 'getPaymentByProviderId', 'addHistoryToPayment', 'savePayment'])
            ->getMock();
        $payments->expects($this->once())
            ->method('getPaymentStatusById')
            ->with(Payments::STATUS_COMPLETED)
            ->willReturn($status);
        $payments->expects($this->exactly(2))
            ->method('getPaymentByProviderId')
            ->with('pi_123')
            ->willReturn($payment);
        $payments->expects($this->once())
            ->method('addHistoryToPayment');
        $payments->expects($this->exactly(2))
            ->method('savePayment')
            ->with($payment);

        $processor = new StripeWebhookProcessor($payments, $event);
        $processor->process();

        $this->assertSame($status, $payment->getStatus());

        return $payment;
    }
}
