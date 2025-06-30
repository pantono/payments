<?php

namespace Pantono\Payments\Repository;

use Pantono\Database\Repository\MysqlRepository;
use Pantono\Payments\Model\PaymentWebhook;

class StripeRepository extends MysqlRepository
{
    public function getMandateBySetupIntentId(string $setupIntentId): ?array
    {
        $sql = "SELECT * from payment_mandate where data->>'$.session_response.setup_intent' = :id";
        $row = $this->getDb()->fetchRow($sql, ['id' => $setupIntentId]);
        return !empty($row) ? $row : null;
    }
}
