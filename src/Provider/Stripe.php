<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentMandate;
use Stripe\StripeClient;
use Pantono\Utilities\ApplicationHelper;
use Pantono\Payments\Repository\StripeRepository;
use Pantono\Payments\Payments;
use Pantono\Payments\Model\PaymentWebhook;
use Pantono\Core\Application\WebApplication;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Pantono\Payments\Event\PaymentWebhookEvent;
use Pantono\Hydrator\Hydrator;
use Stripe\PaymentIntent;
use http\Env\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Twilio\TwiML\Voice\Pay;
use Pantono\Customers\Customers;
use Stripe\PaymentMethod;
use Stripe\Exception\ApiErrorException;

class Stripe extends AbstractProvider
{
    public const PROVIDER_ID = 1;
    private StripeRepository $repository;
    private Hydrator $hydrator;
    private Customers $customers;

    public function __construct(StripeRepository $repository, Hydrator $hydrator, Customers $customers)
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
        $this->customers = $customers;
    }

    private ?StripeClient $client = null;

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function initiate(Payment $payment): void
    {
        $params = [
            'currency' => $payment->getCurrency(),
            'amount' => $payment->getAmount()
        ];
        if ($payment->getDataField('description')) {
            $params['statement_descriptor'] = $payment->getDataField('description');
        }
        if ($payment->getDataField('metadata')) {
            $params['metadata'] = $payment->getDataField('metadata');
        }
        $intent = $this->getClient()->paymentIntents->create($params);
        $payment->setProviderId($intent->id);
        $payment->setRequestData($params);
        $payment->setResponseData($intent->toArray());;
        $payment->setDataValue('payment_intent_id', $intent->id);
        $payment->setDataValue('client_secret', $intent->client_secret);
        $this->payments->savePayment($payment);
    }

    public function lookupPaymentData(Payment $payment): ?PaymentIntent
    {
        $id = $payment->getDataField('payment_intent_id');
        if ($id) {
            return $this->getClient()->paymentIntents->retrieve($id);
        }
        return null;
    }

    public function handleResponse(array $data): ?Payment
    {
        return null;
    }

    public function processMandate(PaymentMandate $mandate): void
    {
        $baseUrl = $this->getConfig()->getApplicationConfig()->getValue('base_url');
        $mandateReturnUrl = $this->getGateway()->getSetting('mandate_return_url');
        if (!$baseUrl && !$mandateReturnUrl) {
            throw new \RuntimeException('Base url in config not set, or mandate_return_url not set in gateway config');
        }
        $returnUrl = $baseUrl . '/payments/stripe/setup-callback';
        if ($mandateReturnUrl) {
            $returnUrl = $mandateReturnUrl;
        }
        $stripeId = null;
        $customer = $mandate->getCustomer();
        if ($customer) {
            $customerId = $customer->getExternalIdByType('stripe');
            if (!$customerId) {
                $details = $customer->getDetails();
                if ($details) {
                    $stripeCustomer = $this->getClient()->customers->create([
                        'email' => $details->getEmail(),
                        'name' => $details->getForename() . ' ' . $details->getSurname(),
                        'metadata' => [
                            'customer_id' => $customer->getId()
                        ]
                    ]);
                    $customer->updateExternalId('stripe', $stripeCustomer->id);
                    $this->customers->saveCustomer($customer);
                }
            }
            $stripeId = $customer->getExternalIdByType('stripe');
        }
        if (!$stripeId) {
            throw new \RuntimeException('Customer cannot be created on stripe');
        }
        $response = $this->getClient()->checkout->sessions->create([
            'currency' => $mandate->getCurrency(),
            'customer' => $stripeId->getIdentifier(),
            'mode' => 'setup',
            'ui_mode' => 'embedded',
            'return_url' => $returnUrl,
        ]);
        $mandate->setDataValue('session_response', $response);
        $mandate->setReference($response->setup_intent);
        $this->getPayments()->saveMandate($mandate);
    }

    public function chargeMandate(PaymentMandate $mandate, int $amountInPence, string $description = 'Recurring charge'): Payment
    {
        if ($mandate->getStatus()->isActive() === false) {
            throw new \RuntimeException('Mandate is not available to charge');
        }
        $status = $this->payments->getPaymentStatusById(Payments::STATUS_PENDING);
        if ($status === null) {
            throw new \RuntimeException('Payment status not set');
        }
        $customerId = $mandate->getCustomer()?->getExternalIdByType('stripe')?->getIdentifier();
        $methodId = $this->getClient()->paymentMethods->retrieve($mandate->getResponseData()['payment_method']);
        $methodName = $this->getPaymentMethodNameFromId($methodId);
        $response = $this->getClient()->paymentIntents->create([
            'amount' => $amountInPence,
            'currency' => 'gbp',
            'customer' => $customerId,
            'payment_method' => $mandate->getResponseData()['payment_method'],
            'off_session' => true,
            'confirm' => true,
            'description' => $description,
            'metadata' => [
                'order_id' => 'ORDER-123', // Your internal reference
            ],
        ]);
        $payment = new Payment();
        $payment->setAmount($amountInPence);
        $payment->setCurrency('gbp');
        $payment->setGateway($this->getGateway());
        $payment->setPaymentMethodName($methodName);
        $payment->setMandate($mandate);
        $payment->setStatus($status);
        $payment->setProviderId($response->id);
        $this->payments->savePayment($payment);
        return $payment;
    }

    public function getMandateBySetupIntentId(string $setupIntentId): ?PaymentMandate
    {
        return $this->hydrator->hydrate(PaymentMandate::class, $this->repository->getMandateBySetupIntentId($setupIntentId));
    }

    public function completeMandate(PaymentMandate $mandate, array $data): void
    {
        $mandate->setDataValue('complete_response', $data);
        $status = $this->payments->getMandateStatusById(Payments::MANDATE_STATUS_ACTIVE);
        if ($status === null) {
            throw new \RuntimeException('Mandate active status not set');
        }
        $mandate->setStatus($status);
        $this->payments->saveMandate($mandate);
    }

    public function verifyWebhook(PaymentWebhook $webhook): bool
    {
        if ($webhook->getRequest()) {
            $secret = $this->getGateway()->getSetting('webhook_secret');
            if ($secret) {
                if ($sig = $webhook->getRequest()->headers->get('stripe-signature')) {
                    try {
                        Webhook::constructEvent($webhook->getRequest()->getContent(), $sig, $secret);
                    } catch (SignatureVerificationException $e) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    private function getClient(): StripeClient
    {
        if (!$this->client) {
            foreach (['stripe_version', 'client_id', 'api_key', 'stripe_account'] as $variable) {
                $setting = $this->getGateway()->getSetting($variable);
                if ($setting !== null) {
                    $params[$variable] = $setting;
                }
            }
            if (!isset($params['api_key'])) {
                throw new \RuntimeException('Stripe API key not set');
            }
            $this->client = new StripeClient($params);
        }
        return $this->client;
    }


    private function getPaymentMethodNameFromId(string $id): string
    {
        try {
            $method = $this->getClient()->paymentMethods->retrieve($id);
            if ($method instanceof PaymentMethod) {
                $card = $method->card;
                if ($method->card) {
                    return $method->card->display_brand . ' ending ' . $method->card->last4;
                }
                return $method->type;
            }
        } catch (ApiErrorException $e) {

        }
        return 'Unknown';
    }
}
