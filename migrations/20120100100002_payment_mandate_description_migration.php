<?php

declare(strict_types=1);

use Pantono\Database\Migration\Base\BasePantonoMigration;

final class PaymentMandateDescriptionMigration extends BasePantonoMigration
{
    public function change(): void
    {
        $this->tablePrefix('payment_mandate')
            ->addColumn('description', 'string', ['null' => true])
            ->addColumn('metadata', 'json')
            ->update();
    }
}
