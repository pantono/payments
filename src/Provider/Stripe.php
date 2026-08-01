<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentMandate;
use Stripe\StripeClient;
use Pantono\Payments\Repository\StripeRepository;
use Pantono\Payments\Payments;
use Pantono\Payments\Model\PaymentWebhook;
use Pantono\Hydrator\Hydrator;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Pantono\Customers\Customers;
use Stripe\Exception\ApiErrorException;
use Stripe\Event;
use Pantono\Payments\Provider\Stripe\StripeWebhookProcessor;
use Pantono\Logger\Logger;

class Stripe extends AbstractProvider
{
    public const PROVIDER_ID = 1;
    private StripeRepository $repository;
    private Hydrator $hydrator;
    private Customers $customers;
    private Logger $logger;

    public function __construct(StripeRepository $repository, Hydrator $hydrator, Customers $customers, Logger $logger)
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
        $this->customers = $customers;
        $this->logger = $logger;
    }

    private ?StripeClient $client = null;

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function initiate(Payment $payment): void
    {
        $currency = $payment->getCurrency();
        if (!$currency) {
            $currency = 'GBP';
        }
        $params = [
            'currency' => $currency,
            'amount' => $payment->getAmount()
        ];
        if ($payment->getDataField('description')) {
            $params['statement_descriptor'] = $payment->getDataField('description');
        }
        if ($payment->getDataField('metadata')) {
            $meta = json_decode($payment->getDataField('metadata'), true, 512, JSON_THROW_ON_ERROR);
            $params['metadata'] = $meta;
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

    public function initiateMandate(PaymentMandate $mandate): void
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
                if ($details && $customer->getId()) {
                    $params = [];
                    if ($details->getForename() && $details->getSurname()) {
                        $params['name'] = $details->getForename() . ' ' . $details->getSurname();
                    } elseif ($details->getForename()) {
                        $params['name'] = $details->getForename();
                    } elseif ($details->getSurname()) {
                        $params['name'] = $details->getSurname();
                    }
                    if ($details->getEmail()) {
                        $params['email'] = $details->getEmail();
                    }
                    if (!empty($params)) {
                        $stripeCustomer = $this->getClient()->customers->create($params);
                        $customer->updateExternalId('stripe', $stripeCustomer->id);
                        $this->customers->saveCustomer($customer);
                    }
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
        if (!$mandate->getStatus() || $mandate->getStatus()->isActive() === false) {
            throw new \RuntimeException('Mandate is not available to charge');
        }
        $status = $this->payments->getPaymentStatusById(Payments::STATUS_PENDING);
        if ($status === null) {
            throw new \RuntimeException('Payment status not set');
        }
        $customerId = $mandate->getCustomer()?->getExternalIdByType('stripe')?->getIdentifier();
        if (!$customerId) {
            throw new \RuntimeException('Stripe customer id not found');
        }
        $methodId = $this->getClient()->paymentMethods->retrieve($mandate->getResponseData()['payment_method']);
        $methodName = $this->getPaymentMethodNameFromId($methodId);
        $response = $this->getClient()->paymentIntents->create([
            'amount' => $amountInPence,
            'currency' => 'gbp',
            'customer' => $customerId,
            'payment_method' => $mandate->getResponseData()['payment_method'],
            'off_session' => true,
            'confirm' => true,
            'description' => $description
        ]);
        $payment = new Payment();
        $payment->setAmount($amountInPence);
        $payment->setCurrency('gbp');
        $payment->setGateway($this->getGateway());
        $payment->setPaymentMethodName($methodName);
        $payment->setMandate($mandate);
        $payment->setReference($description);
        $payment->setDateCreated(new \DateTimeImmutable());
        $payment->setDateUpdated(new \DateTimeImmutable());
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

    public function verifyWebhook(PaymentWebhook $webhook): ?Event
    {
        if ($webhook->getRequest()) {
            $secret = $this->getGateway()->getSetting('webhook_secret');
            if ($secret) {
                if ($sig = $webhook->getRequest()->headers->get('stripe-signature')) {
                    try {
                        return Webhook::constructEvent($webhook->getRequest()->getContent(), $sig, $secret);
                    } catch (SignatureVerificationException $e) {
                        return null;
                    }
                }
            }
        }
        return null;
    }

    public function ingestWebhook(PaymentWebhook $webhook): void
    {
        if ($webhook->getRequest()) {
            $secret = $this->getGateway()->getSetting('stripe_webhook_secret');
            if ($secret) {
                if ($sig = $webhook->getSingleHeader('stripe-signature')) {
                    try {
                        $event = Webhook::constructEvent($webhook->getRequest()->getContent(), $sig, $secret);
                        $webhook->setVerified(true);
                        $webhook->setDecodedData($event->toArray());
                        $this->payments->saveWebhook($webhook);
                        $processor = new StripeWebhookProcessor($this->payments, $event);
                        $processor->process();
                    } catch (SignatureVerificationException $e) {
                        $webhook->setVerified(false);
                        $webhook->setProcessed(true);
                        $webhook->setError($e->getMessage());
                        $this->payments->saveWebhook($webhook);
                    }
                } else {
                    $webhook->setVerified(false);
                    $webhook->setError('No signature header present');
                    $this->payments->saveWebhook($webhook);
                }
            }
        }
    }

    public function performRefund(Payment $payment, int $amountInPence): void
    {
        if (!$payment->getProviderId()) {
            throw new \RuntimeException('Payment provider id not found');
        }
        $output = $this->getClient()->refunds->create([
            'payment_intent' => $payment->getProviderId(),
            'amount' => $amountInPence,
        ]);
        $statusId = Payments::STATUS_PENDING;
        if ($output->status === 'succeeded') {
            $statusId = Payments::STATUS_COMPLETED;
        }
        if ($output->status === 'failed' || $output->status === 'cancelled') {
            $statusId = Payments::STATUS_FAILED;
        }
        $status = $this->payments->getPaymentStatusById($statusId);
        if (!$status) {
            throw new \RuntimeException('Payment status not found');
        }
        $payment->setProviderId($output->id);
        $payment->setStatus($status);
        $payment->setResponseData($output->toArray());
        $this->payments->savePayment($payment);
    }


    private function getClient(): StripeClient
    {
        if (!$this->client) {
            $logger = $this->logger->createDatabaseLogger('stripe');
            \Stripe\Stripe::setLogger($logger);
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
            if ($method->card) {
                return $method->card->display_brand . ' ending ' . $method->card->last4;
            }
            return $method->type;
        } catch (ApiErrorException $e) {

        }
        return 'Unknown';
    }
}
