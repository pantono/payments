<?php

namespace Pantono\Payments\Decorator;

use League\Fractal\TransformerAbstract;
use Pantono\Payments\Model\Payment;
use League\Fractal\Resource\ResourceAbstract;

class StripePaymentDecorator extends TransformerAbstract
{
    protected array $defaultIncludes = ['status'];

    public function transform(Payment $payment): array
    {
        return [
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'date_created' => $payment->getDateCreated()->format('Y-m-d H:i:s'),
            'date_updated' => $payment->getDateUpdated()->format('Y-m-d H:i:s'),
            'client_secret' => $payment->getDataField('client_secret'),
            'intent_id' => $payment->getDataField('payment_intent_id')
        ];
    }

    public function includeStatus(Payment $payment): ResourceAbstract
    {
        return $this->item($payment->getStatus(), new PaymentStatusDecorator());
    }
}
