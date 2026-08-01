<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentWebhook;

class ManualBankTransfer extends AbstractProvider
{
    public const int PROVIDER_ID = 4;

    public function supportsRecurring(): bool
    {
        return false;
    }

    public function initiatePayment(Payment $payment): void
    {
        $this->payments->savePayment($payment);
    }

    public function handleResponseData(array $data): ?Payment
    {
        return null;
    }

    public function updatePaymentDetails(Payment $payment): void
    {
        // no-op
    }

    public function performRefund(Payment $payment, int $amountInPence): void
    {
        // TODO: Implement performRefund() method.
    }

    public function ingestWebhook(PaymentWebhook $webhook): void
    {
        //no-op
    }
}
