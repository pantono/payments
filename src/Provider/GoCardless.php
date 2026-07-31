<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Exception\GatewayDoesNotSupportOneOffPayments;
use Pantono\Payments\Model\PaymentMandate;
use GoCardlessPro\Client;
use Pantono\Payments\Model\PaymentWebhook;

class GoCardless extends AbstractProvider
{
    private ?Client $client = null;

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function initiate(Payment $payment): void
    {
        throw new GatewayDoesNotSupportOneOffPayments('Cannot support single payments');
    }

    public function handleResponse(array $data): ?Payment
    {
        throw new GatewayDoesNotSupportOneOffPayments('Cannot support single payments');
    }

    public function initiateMandate(PaymentMandate $mandate): void
    {
        $customerId = $mandate->getCustomer()?->getExternalIdByType('gocardless')?->getIdentifier();
        $params = ['params' => [
            'mandate_request' => [
                'scheme' => 'bacs'
            ]
        ]];
        if (!$customerId) {
            $params['mandate_request']['links']['customer'] = $customerId;
        }
        $response = $this->getClient()->billingRequests()->create($params);
        $mandate->setReference($response->id);
        $mandate->setResponseData($response->api_response->toArray());;
        $this->getPayments()->saveMandate($mandate);
    }

    public function processMandate(PaymentMandate $mandate, array $data): void
    {
        throw new \RuntimeException('Not yet implemented');
    }

    private function getClient(): Client
    {
        if (!$this->client) {
            $this->client = new Client([
                'access_token' => $this->getGateway()->getSetting('access_token'),
                'environment' => $this->getGateway()->getSetting('environment')
            ]);
        }
        return $this->client;
    }

    public function performRefund(Payment $payment, int $amountInPence): void
    {
        throw new \RuntimeException('Not yet implemented');
    }

    public function ingestWebhook(PaymentWebhook $webhook): void
    {
        throw new \RuntimeException('Not yet implemented');
    }
}
