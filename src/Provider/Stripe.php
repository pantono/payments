<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentMandate;
use Stripe\StripeClient;
use Pantono\Utilities\ApplicationHelper;

class Stripe extends AbstractProvider
{
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
}
