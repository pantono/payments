<?php

namespace Pantono\Payments\Repository;

use Pantono\Database\Repository\MysqlRepository;
use Pantono\Payments\Model\StripeWebhook;

class StripeRepository extends MysqlRepository
{
    public function saveWebhook(StripeWebhook $webhook): void
    {
        $id = $this->insertOrUpdateCheck('payments_stripe_webhook', 'id', $webhook->getId(), $webhook->getAllData());
        if ($id) {
            $webhook->setId($id);
        }
    }
}
