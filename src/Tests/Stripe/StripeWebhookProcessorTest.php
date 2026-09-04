<?php

namespace Pantono\Payments\Tests\Stripe;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentStatus;
use Pantono\Payments\Payments;
use PHPUnit\Framework\TestCase;
use Stripe\Event;
use Pantono\Payments\Provider\Stripe\StripeWebhookProcessor;
use Pantono\Customers\Customers;

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

    public function testPaymentIntentSucceededSetsPaypalPaymentMethodName(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'paypal',
                    'paypal' => [
                        'payer_email' => 'buyer@example.com',
                        'payer_id' => 'PAYER123',
                    ],
                ],
            ],
        ]);

        $this->assertSame([], $payment->getCardData());
        $this->assertNull($payment->getAuthCode());
        $this->assertSame('PayPal (buyer@example.com)', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededSetsSepaDebitPaymentMethodName(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'sepa_debit',
                    'sepa_debit' => [
                        'bank_code' => '37040044',
                        'country' => 'DE',
                        'last4' => '3000',
                    ],
                ],
            ],
        ]);

        $this->assertSame([], $payment->getCardData());
        $this->assertSame('SEPA Direct Debit ending 3000', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededSetsBacsDebitPaymentMethodName(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'bacs_debit',
                    'bacs_debit' => [
                        'last4' => '2345',
                        'sort_code' => '108800',
                    ],
                ],
            ],
        ]);

        $this->assertSame('Bacs Direct Debit ending 2345', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededSetsKlarnaPaymentMethodNameWithoutDetail(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'klarna',
                    'klarna' => [
                        'payment_method_category' => 'pay_later',
                    ],
                ],
            ],
        ]);

        $this->assertSame('Klarna', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededFallsBackToHumanisedTypeForUnknownMethod(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'some_new_method',
                    'some_new_method' => [],
                ],
            ],
        ]);

        $this->assertSame('Some New Method', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededLeadsWithWalletForApplePay(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'card',
                    'card' => [
                        'brand' => 'visa',
                        'display_brand' => 'Visa',
                        'last4' => '4242',
                        'wallet' => [
                            'type' => 'apple_pay',
                            'dynamic_last4' => '9999',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Apple Pay (Visa ending 4242)', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededLeadsWithWalletForGooglePay(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'card',
                    'card' => [
                        'brand' => 'mastercard',
                        'last4' => '4444',
                        'wallet' => ['type' => 'google_pay'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Google Pay (mastercard ending 4444)', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededUsesWalletAloneWhenCardIsUnknown(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'card',
                    'card' => [
                        'wallet' => ['type' => 'link'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Link', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededDescribesCardPresentWallet(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'card_present',
                    'card_present' => [
                        'brand' => 'amex',
                        'last4' => '0005',
                        'wallet' => ['type' => 'samsung_pay'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Samsung Pay (amex ending 0005)', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededDescribesAmazonPayFundingCard(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'amazon_pay',
                    'amazon_pay' => [
                        'funding' => [
                            'type' => 'card',
                            'card' => [
                                'brand' => 'visa',
                                'last4' => '1111',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Amazon Pay (visa ending 1111)', $payment->getPaymentMethodName());
    }

    public function testPaymentIntentSucceededDescribesMobilepayCard(): void
    {
        $payment = $this->processPaymentIntentSucceeded([
            'latest_charge' => [
                'payment_method_details' => [
                    'type' => 'mobilepay',
                    'mobilepay' => [
                        'card' => [
                            'brand' => 'mastercard',
                            'last4' => '5555',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('MobilePay (mastercard ending 5555)', $payment->getPaymentMethodName());
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
        //Status id is not asserted here, the succeeded handler currently looks up
        //Payments::MANDATE_STATUS_EXPIRED rather than a payment status
        $payments->expects($this->once())
            ->method('getPaymentStatusById')
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

        $customers = $this->createStub(Customers::class);

        $processor = new StripeWebhookProcessor($payments, $event, $customers);
        $processor->process();

        $this->assertSame($status, $payment->getStatus());

        return $payment;
    }
}
