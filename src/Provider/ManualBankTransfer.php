<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentWebhook;

class ManualBankTransfer extends AbstractProvider
{
    public function supportsRecurring(): bool
    {
        return false;
    }

    public function initiate(Payment $payment): void
    {
        $this->payments->savePayment($payment);
    }

    public function handleResponse(array $data): ?Payment
    {
        return null;
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
