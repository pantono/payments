<?php

namespace Pantono\Payments\Decorator;

use League\Fractal\TransformerAbstract;
use Pantono\Payments\Model\PaymentStatus;

class PaymentStatusDecorator extends TransformerAbstract
{
    public function transform(PaymentStatus $status): array
    {
        return [
            'id' => $status->getId(),
            'name' => $status->getName(),
            'completed' => $status->isCompleted(),
            'pending' => $status->isPending(),
            'failed' => $status->isFailed()
        ];
    }
}
