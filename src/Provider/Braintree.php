<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Braintree\Gateway;
use Symfony\Component\HttpFoundation\Session\Session;
use Pantono\Payments\Payments;
use Braintree\Result\Successful;
use Pantono\Payments\Model\PaymentMandate;
use Braintree\CustomerSearch;
use Pantono\Customers\Customers;
use Braintree\Result\Error;
use Pantono\Payments\Model\PaymentWebhook;
use Symfony\Component\HttpFoundation\ParameterBag;
use Pantono\Payments\Exception\RefundFailedException;
use Braintree\Transaction;
use Braintree\Exception\NotFound;

class Braintree extends AbstractProvider
{
    public const int PROVIDER_ID = 2;
    private Session $session;
    private Customers $customers;
    private ?Gateway $gateway = null;

    public function __construct(Session $session, Customers $customers)
    {
        $this->session = $session;
        $this->customers = $customers;
    }

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function updatePaymentDetails(Payment $payment): void
    {
        if (!$payment->getProviderId()) {
            return;
        }
        $transaction = $this->createClient()->transaction()->find($payment->getProviderId());
        if ($transaction instanceof Transaction) {
            $this->updatePaymentSuccess($payment, $transaction);
        }
    }

    public function chargeMandate(PaymentMandate $mandate, int $amountInPence, string $description): Payment
    {
        if (!$mandate->getStatus() || $mandate->getStatus()->isActive() === false) {
            throw new \RuntimeException('Mandate is not available to charge');
        }
        if ($mandate->getPaymentGateway() === null) {
            throw new \RuntimeException('Mandate is not available to charge');
        }
        $status = $this->payments->getPaymentStatusById(Payments::STATUS_PENDING);
        if ($status === null) {
            throw new \RuntimeException('Payment status not set');
        }
        $customerId = $mandate->getCustomer()?->getExternalIdByType('braintree')?->getIdentifier();
        if (!$customerId) {
            throw new \RuntimeException('Customer cannot be created on braintree');
        }
        $saleParams = [
            'amount' => $amountInPence / 100,
            'paymentMethodToken' => $mandate->getReference(),
            'options' => [
                'submitForSettlement' => True
            ]
        ];
        if ($this->getGateway()->getSetting('merchantAccountId')) {
            $saleParams['merchantAccountId'] = $this->getGateway()->getSetting('merchantAccountId');
        }
        $result = $this->createClient()->transaction()->sale($saleParams);
        $payment = new Payment();
        $payment->setMandate($mandate);
        $payment->setDateCreated(new \DateTimeImmutable());
        $payment->setDateUpdated(new \DateTimeImmutable());
        $payment->setAmount($amountInPence);
        if ($mandate->getPaymentGateway()) {
            $payment->setGateway($mandate->getPaymentGateway());
        }
        $status = $this->payments->getPaymentStatusById(Payments::STATUS_PENDING);
        if ($status) {
            $payment->setStatus($status);
        }
        if ($result instanceof Successful) {
            $status = $this->payments->getPaymentStatusById(Payments::STATUS_COMPLETED);
            if ($status) {
                $payment->setStatus($status);
            }
            $payment->setProviderId($result->transaction->id);
            $payment->setReference($result->transaction->id);
            $payment->setResponseData($result->transaction->toArray());
            foreach ($result->transaction->statusHistory as $item) {
                $this->payments->addHistoryToPayment($payment, 'Braintree: ' . $item->status, $item->toArray(), $item->timestamp);
            }
            $payment->setCardData($result->transaction->creditCardDetails->toArray());
            $payment->setPaymentMethodName($result->transaction->creditCardDetails->maskedNumber);
            $payment->setAuthCode($result->transaction->paymentReceipt['processorAuthorizationCode']);;
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

    public function initiatePayment(Payment $payment): void
    {
        $params = [];
        if ($payment->getDataField('customer_id')) {
            $params['customerId'] = $payment->getDataField('customer_id');
        }
        $payment->setRequestData($params);
        $token = $this->createClient()->clientToken()->generate($params);
        $payment->setDataValue('client_token', $token);
        $this->session->set('payment_id', $payment->getId());
        $this->payments->savePayment($payment);
    }

    public function handleResponseData(array $data): ?Payment
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
            $this->updatePaymentSuccess($payment, $result->transaction);
        } else {
            $this->updatePaymentError($payment, $result);
        }
        return $payment;
    }

    public function initiateMandate(PaymentMandate $mandate): void
    {
        $customer = $mandate->getCustomer();
        if (!$customer) {
            throw new \RuntimeException('Customer not available on mandate record');
        }
        $braintreeId = $customer->getExternalIdByType('braintree');
        if (!$braintreeId) {
            $email = (string)$customer->getDetails()?->getEmail();
            $current = $this->findCustomerRecord($email);
            if (!$current) {
                $details = $customer->getDetails();
                if (!$details) {
                    throw new \RuntimeException('Customer details not set');
                }
                $result = $this->createClient()->customer()->create([
                    'firstName' => $details->getForename(),
                    'lastName' => $details->getSurname(),
                    'email' => $details->getEmail(),
                    'phone' => $details->getMobileNumber()
                ]);
                if (!$result->success) {
                    throw new \RuntimeException('Unable to create customer record');
                }
                $current = $result->customer->id;
            }
            $customer->updateExternalId('braintree', $current);
            $this->customers->saveCustomer($customer);
            $braintreeId = $customer->getExternalIdByType('braintree');
        }
        $params = [];
        if ($braintreeId && $braintreeId->getIdentifier()) {
            $params['customerId'] = $braintreeId->getIdentifier();
        }
        $token = $this->createClient()->clientToken()->generate($params);
        $mandate->setDataValue('token', $token);
        $this->payments->saveMandate($mandate);
    }

    public function processMandate(PaymentMandate $mandate, array $data): void
    {
        $nonce = $data['payment_method_nonce'] ?? null;
        $deviceData = $data['payment_device_data'] ?? null;

        $braintreeId = $mandate->getCustomer()?->getExternalIdByType('braintree')?->getIdentifier();
        if (!$braintreeId) {
            throw new \RuntimeException('Customer cannot be created on braintree');
        }
        $result = $this->createClient()->paymentMethod()->create([
            'customerId' => $braintreeId,
            'paymentMethodNonce' => $nonce,
            'deviceData' => $deviceData,
            'options' => [
                'makeDefault' => true
            ]
        ]);
        if ($result instanceof Error) {
            $mandate->setDataValue('setup_error', $result->message);
            $status = $this->payments->getMandateStatusById(Payments::MANDATE_STATUS_ERROR);
            if ($status) {
                $mandate->setStatus($status);
            }
        }
        if ($result instanceof Successful) {
            $mandate->setDataValue('payment_method_nonce', $nonce);
            $mandate->setDataValue('payment_device_data', $deviceData);
            $status = $this->payments->getMandateStatusById(Payments::MANDATE_STATUS_ACTIVE);
            if ($status) {
                $mandate->setStatus($status);
            }
            $mandate->setReference($result->paymentMethod->token);
            $mandate->setStartDate(new \DateTimeImmutable());
            $mandate->setResponseData($result->paymentMethod->toArray());
        }
        $this->payments->saveMandate($mandate);
    }

    public function ingestWebhook(PaymentWebhook $webhook): void
    {
        $data = new ParameterBag($webhook->getData());
        if (!$data->has('bt_signature') || !$data->has('bt_payload')) {
            $webhook->setVerified(false);
            $webhook->setProcessed(false);
            return;
        }
        $output = $this->createClient()->webhookNotification()->parse($data->get('bt_signature'), $data->get('bt_payload'));
        $webhook->setDecodedData($output->toArray());
        $this->payments->saveWebhook($webhook);
    }

    public function performRefund(Payment $payment, int $amountInPence): void
    {
        $parent = $payment->getParentPayment();
        if ($parent && $parent->getProviderId()) {
            $current = $this->createClient()->transaction()->find($parent->getProviderId());
            if ($current instanceof NotFound) {
                throw new RefundFailedException('Parent transaction does not exist');
            }
            $transData = $current->toArray();
            $status = $transData['status'] ?? null;
            if ($status === 'submitted_for_settlement') {
                throw new RefundFailedException('Transaction cannot be refunded until it has been settled');
            }
            if ($amountInPence !== 0) {
                $result = $this->createClient()->transaction()->refund(
                    $parent->getProviderId(),
                    number_format($amountInPence / 100, 2, '.', '')
                );
            } else {
                $result = $this->createClient()->transaction()->refund(
                    $parent->getProviderId()
                );
            }
            if ($result instanceof Successful) {
                $this->updatePaymentSuccess($payment, $result->transaction);
            } else {
                $failedStatus = $this->payments->getPaymentStatusById(Payments::STATUS_FAILED);
                if ($failedStatus) {
                    $payment->setStatus($failedStatus);
                }
                if ($result instanceof Error) {
                    $resultArray = $result->toArray();
                    $message = $resultArray['message'];
                } else {
                    $message = 'Payment does not exist';
                }
                if (!$message) {
                    $message = 'Unknown Error';
                }
                $payment->setResponseData(['error' => $message]);
                $this->payments->savePayment($payment);
                throw new RefundFailedException($message);
            }
        }
    }

    private function updatePaymentSuccess(Payment $payment, Transaction $transaction): void
    {
        $status = $this->payments->getPaymentStatusById(Payments::STATUS_COMPLETED);
        if ($status) {
            $payment->setStatus($status);
        }
        $payment->setProviderId($transaction->id);
        $payment->setResponseData($transaction->toArray());
        foreach ($transaction->statusHistory as $item) {
            $this->payments->addHistoryToPayment($payment, 'Braintree: ' . $item->status, $item->toArray(), $item->timestamp);
        }
        $payment->setCardData($transaction->creditCardDetails->toArray());
        $payment->setPaymentMethodName($transaction->creditCardDetails->maskedNumber);
        $payment->setAuthCode($transaction->processorAuthorizationCode);;
        $payment->setCurrency($transaction->currencyIsoCode);
        $this->payments->savePayment($payment);
    }

    private function updatePaymentError(Payment $payment, Error $error): void
    {
        $status = $this->payments->getPaymentStatusById(Payments::STATUS_FAILED);
        if ($status) {
            $payment->setStatus($status);
        }
        $payment->setResponseData($error->toArray());
        $this->payments->savePayment($payment);
    }

    private function findCustomerRecord(string $email): ?string
    {
        $customers = $this->createClient()->customer()->search([
            CustomerSearch::email()->is($email)
        ]);
        if ($customers->firstItem()) {
            return $customers->firstItem()->id;
        }
        return null;
    }

    public function createClient(): Gateway
    {
        if (!$this->gateway) {
            $this->gateway = new Gateway([
                'environment' => $this->getGateway()->getSetting('environment'),
                'merchantId' => $this->getGateway()->getSetting('merchantId'),
                'publicKey' => $this->getGateway()->getSetting('publicKey'),
                'privateKey' => $this->getGateway()->getSetting('privateKey'),
            ]);
        }
        return $this->gateway;
    }
}
