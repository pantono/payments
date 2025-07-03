<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Braintree\Gateway;

class Braintree extends AbstractProvider
{
    public function supportsRecurring(): bool
    {
        return true;
    }

    public function initiate(Payment $payment): void
    {
        $params = [];
        if ($payment->getDataField('customer_id')) {
            $params['customerId'] = $payment->getDataField('customer_id');
        }
        $token = $this->createClient()->clientToken()->generate($params);
        $payment->setDataValue('client_token', $token);
    }

    public function handleResponse(array $data): void
    {
        // TODO: Implement handleResponse() method.
    }

    public function createClient(): Gateway
    {
        return new Gateway([
            'environment' => $this->getGateway()->getSetting('environment'),
            'merchantId' => $this->getGateway()->getSetting('merchantId'),
            'publicKey' => $this->getGateway()->getSetting('publicKey'),
            'privateKey' => $this->getGateway()->getSetting('privateKey'),
        ]);
    }
}
