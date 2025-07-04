<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Braintree\Gateway;
use Symfony\Component\HttpFoundation\Session\Session;
use function Crell\fp\method;
use Pantono\Payments\Payments;
use Braintree\Result\Successful;
use Pantono\Utilities\DateTimeParser;

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
        $payment->setRequestData($params);
        $token = $this->createClient()->clientToken()->generate($params);
        $payment->setDataValue('client_token', $token);
        $this->session->set('payment_id', $payment->getId());
    }

    public function handleResponse(array $data): ?Payment
    {
        $paymentId = $data['payment_id'] ?? null;
        $paymentMethodNonce = $data['payment_method_nonce'] ?? null;
        $paymentDeviceData = $data['payment_device_data'] ?? null;
        if (!$paymentId || !$paymentMethodNonce) {
            throw new \RuntimeException('Payment information not set');
        }
        $payment = $this->payments->getPaymentById($paymentId);
        if (!$payment) {
            throw new \RuntimeException('Payment not found');
        }
        $saleParams = [
            'amount' => $payment->getAmount() / 100,
            'paymentMethodNonce' => $paymentMethodNonce,
            'deviceData' => $paymentDeviceData,
            'options' => [
                'submitForSettlement' => True
            ]
        ];
        if ($this->getGateway()->getSetting('merchantAccountId')) {
            $saleParams['merchantAccountId'] = $this->getGateway()->getSetting('merchantAccountId');
        }
        $result = $this->createClient()->transaction()->sale($saleParams);
        if ($result instanceof Successful) {
            $status = $this->payments->getPaymentStatusById(Payments::STATUS_COMPLETED);
            if ($status) {
                $payment->setStatus($status);
            }
            $payment->setProviderId($result->transaction->id);
            $payment->setResponseData($result->transaction->toArray());
            foreach ($result->transaction->statusHistory as $item) {
                $this->payments->addHistoryToPayment($payment, 'Braintree: ' . $item->status, $item->toArray(), $item->timestamp);
            }
            $payment->setCardData($result->transaction->creditCardDetails->toArray());
            $payment->setPaymentMethodName($result->transaction->creditCardDetails->maskedNumber);
            $payment->setAuthCode($result->paymentRecord->processorAuthorizationCode);;
            $payment->setCurrency($result->transaction->currencyIsoCode);
            $this->payments->savePayment($payment);
        } else {
            if ($payment->getStatus()->getId() !== Payments::STATUS_COMPLETED) {
                $status = $this->payments->getPaymentStatusById(Payments::STATUS_FAILED);
                if ($status) {
                    $payment->setStatus($status);
                }
                $payment->setResponseData($result->toArray());
                $this->payments->savePayment($payment);
            }
        }
        return $payment;
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
