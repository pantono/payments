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

    public function getMandateBySetupIntentId(string $setupIntentId): ?array
    {
        $sql = "SELECT * from payment_mandate where data->>'$.session_response.setup_intent' = :id";
        $row = $this->getDb()->fetchRow($sql, ['id' => $setupIntentId]);
        return !empty($row) ? $row : null;
    }
}
