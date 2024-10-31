<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentMandate;
use Stripe\StripeClient;
use Pantono\Utilities\ApplicationHelper;
use Pantono\Payments\Repository\StripeRepository;
use Pantono\Payments\Payments;
use Pantono\Payments\Model\StripeWebhook;
use Pantono\Core\Application\WebApplication;

class Stripe extends AbstractProvider
{
    public const PROVIDER_ID = 1;
    private StripeRepository $repository;

    public function __construct(StripeRepository $repository)
    {
        $this->repository = $repository;
    }

    private ?StripeClient $client = null;

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function initiate(Payment $payment): void
    {
        // TODO: Implement initiate() method.
    }

    public function handleResponse(array $data): void
    {
        // TODO: Implement handleResponse() method.
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
        $response = $this->getClient()->checkout->sessions->create([
            'currency' => $mandate->getCurrency(),
            'mode' => 'setup',
            'ui_mode' => 'embedded',
            'return_url' => $returnUrl
        ]);
        $mandate->setDataValue('session_response', $response);
        $this->getPayments()->saveMandate($mandate);
    }

    public function getClient(): StripeClient
    {
        if (!$this->client) {
            $this->client = new StripeClient([
                'api_key' => $this->getGateway()->getSetting('key')
            ]);
        }
        return $this->client;
    }

    public function ingestWebhook(string $type, array $data): StripeWebhook
    {
        $webhook = new StripeWebhook();
        $webhook->setGateway($this->getGateway());
        $webhook->setData($data);
        $webhook->setType($type);
        $webhook->setDate(new \DateTimeImmutable());
        $this->repository->saveWebhook($webhook);
        return $webhook;
    }
}
