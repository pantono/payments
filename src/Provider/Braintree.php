<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Braintree\Gateway;
use Symfony\Component\HttpFoundation\Session\Session;

class Braintree extends AbstractProvider
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

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
        $this->session->set('payment_id', $payment->getId());
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
