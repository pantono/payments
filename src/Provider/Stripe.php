<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentMandate;

class Stripe extends AbstractProvider
{
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

    }
}
