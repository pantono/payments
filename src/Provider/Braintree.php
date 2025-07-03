<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Braintree\Gateway;
use Symfony\Component\HttpFoundation\Session\Session;
use function Crell\fp\method;
use Pantono\Payments\Payments;

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
        $paymentId = $data['payment_id'] ?? null;
        $paymentMethodNonce = $data['payment_method_nonce'] ?? null;
        $paymentDeviceData = $data['payment_device_data'] ?? null;
        if (!$paymentId || $paymentMethodNonce) {
            throw new \RuntimeException('Payment information not set');
        }
        $payment = $this->payments->getPaymentById($paymentId);
        if (!$payment) {
            throw new \RuntimeException('Payment not found');
        }
        $result = $this->createClient()->transaction()->sale([
            'amount' => $payment->getAmount() / 100,
            'paymentMethodNonce' => $paymentMethodNonce,
            'deviceData' => $paymentDeviceData,
            'options' => [
                'submitForSettlement' => True
            ]
        ]);

        if ($result->success) {
            $status = $this->payments->getPaymentStatusById(Payments::STATUS_COMPLETED);
            if ($status) {
                $payment->setStatus($status);
            }
            $payment->setResponseData($result->toArray());
            $this->payments->savePayment($payment);
        }
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
