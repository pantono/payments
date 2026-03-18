<?php

namespace Pantono\Payments\Repository;

use Pantono\Database\Repository\DefaultRepository;

class StripeRepository extends DefaultRepository
{
    public function getMandateBySetupIntentId(string $setupIntentId): ?array
    {
        $select = $this->getDb()->select('p')->from('payment', 'p')
            ->jsonWhere('data', ['session_response', 'setup_intent'], $setupIntentId);
        
        $row = $this->getDb()->fetchRow($select);
        return !empty($row) ? $row : null;
    }
}
