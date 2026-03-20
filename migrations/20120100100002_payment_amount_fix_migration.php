<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PaymentAmountFixMigration extends AbstractMigration
{
    public function up(): void
    {
        $this->table('payment')
            ->changeColumn('amount', 'float')
            ->update();
    }

    public function down(): void
    {

    }
}
