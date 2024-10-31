<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class StripeWebhooks extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payments_stripe_webhook')
            ->addColumn('date', 'datetime')
            ->addColumn('type', 'string')
            ->addColumn('data', 'json')
            ->addColumn('processed', 'boolean')
            ->addIndex('type')
            ->create();
    }
}
