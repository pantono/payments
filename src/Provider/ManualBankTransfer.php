<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;

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
}
