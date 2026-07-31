<?php

declare(strict_types=1);

use Pantono\Database\Migration\Base\BasePantonoMigration;

final class PaymentsMigration extends BasePantonoMigration
{
    public function change(): void
    {
        $this->tablePrefix('payment_provider')
            ->addColumn('name', 'string')
            ->addColumn('controller', 'string')
            ->create();

        $this->insertOnCreate($this->addTablePrefix('payment_provider'), [
            ['id' => 1, 'name' => 'Stripe', 'controller' => 'Pantono\Payments\Provider\Stripe'],
            ['id' => 2, 'name' => 'Braintree', 'controller' => 'Pantono\Payments\Provider\Braintree'],
            ['id' => 3, 'name' => 'Go Cardless', 'controller' => 'Pantono\Payments\Provider\GoCardless'],
            ['id' => 4, 'name' => 'Manual Bank Transfer', 'controller' => 'Pantono\Payments\Provider\ManualBankTransfer'],
        ]);

        $this->tablePrefix('payment_gateway')
            ->addColumn('name', 'string')
            ->addLinkedColumn('provider_id', 'payment_provider', 'id')
            ->addColumn('settings', 'json')
            ->create();

        $this->tablePrefix('payment_status')
            ->addColumn('name', 'string')
            ->addColumn('completed', 'boolean')
            ->addColumn('refund', 'boolean')
            ->addColumn('pending', 'boolean')
            ->addColumn('failed', 'boolean')
            ->create();

        $this->insertOnCreate($this->addTablePrefix('payment_status'), [
            ['id' => 1, 'name' => 'Pending', 'completed' => 0, 'pending' => 1, 'failed' => 0, 'refund' => 0],
            ['id' => 2, 'name' => 'Completed', 'completed' => 1, 'pending' => 0, 'failed' => 0, 'refund' => 0],
            ['id' => 3, 'name' => 'Failed', 'completed' => 0, 'pending' => 0, 'failed' => 1, 'refund' => 0],
            ['id' => 4, 'name' => 'Chargeback', 'completed' => 0, 'pending' => 0, 'failed' => 1, 'refund' => 0],
            ['id' => 5, 'name' => 'Refunded', 'completed' => 0, 'pending' => 0, 'failed' => 0, 'refund' => 1],
            ['id' => 6, 'name' => 'Part Refunded', 'completed' => 0, 'pending' => 0, 'failed' => 0, 'refund' => 1],
        ]);

        $this->tablePrefix('payment_mandate_status')
            ->addColumn('name', 'string')
            ->addColumn('active', 'boolean')
            ->addColumn('cancelled', 'boolean')
            ->addColumn('expired', 'boolean')
            ->create();

        $this->insertOnCreate($this->addTablePrefix('payment_mandate_status'), [
            ['id' => 1, 'name' => 'Pending', 'active' => 0, 'cancelled' => 0, 'expired' => 0],
            ['id' => 2, 'name' => 'Active', 'active' => 1, 'cancelled' => 0, 'expired' => 0],
            ['id' => 3, 'name' => 'Cancelled', 'active' => 0, 'cancelled' => 1, 'expired' => 0],
            ['id' => 4, 'name' => 'Expired', 'active' => 0, 'cancelled' => 0, 'expired' => 1],
            ['id' => 5, 'name' => 'Error', 'active' => 0, 'cancelled' => 0, 'expired' => 0],
        ]);

        $this->tablePrefix('payment_mandate')
            ->addLinkedColumn('gateway_id', $this->addTablePrefix('payment_gateway'), 'id')
            ->addLinkedColumn('customer_id', $this->addTablePrefix('customer'), 'id', ['null' => true])
            ->addLinkedColumn('status_id', $this->addTablePrefix('payment_mandate_status'), 'id')
            ->addColumn('reference', 'string', ['null' => true])
            ->addColumn('start_date', 'date', ['null' => true])
            ->addColumn('end_date', 'date', ['null' => true])
            ->addColumn('currency', 'string')
            ->addColumn('setup_data', 'json')
            ->addColumn('response_data', 'json')
            ->create();

        $this->tablePrefix('payment_mandate_history')
            ->addLinkedColumn('mandate_id', $this->addTablePrefix('payment_mandate'), 'id')
            ->addColumn('date', 'datetime')
            ->addColumn('entry', 'text')
            ->addColumn('data', 'json')
            ->create();

        $this->tablePrefix('payment')
            ->addColumn('request_data', 'json')
            ->addLinkedColumn('gateway_id', $this->addTablePrefix('payment_gateway'), 'id')
            ->addLinkedColumn('mandate_id', $this->addTablePrefix('payment_mandate'), 'id', ['null' => true])
            ->addColumn('provider_id', 'string', ['null' => true])
            ->addLinkedColumn('status_id', $this->addTablePrefix('payment_status'), 'id')
            ->addColumn('reference', 'string', ['null' => true])
            ->addColumn('currency', 'string', ['null' => true])
            ->addColumn('payment_method_name', 'string', ['null' => true])
            ->addColumn('auth_code', 'string', ['null' => true])
            ->addColumn('card_data', 'json', ['null' => true])
            ->addColumn('amount', 'integer')
            ->addColumn('response_data', 'json')
            ->addColumn('date_created', 'datetime')
            ->addColumn('date_updated', 'datetime')
            ->addColumn('data', 'json')
            ->addColumn('redirect_url', 'string', ['null' => true])
            ->addIndex('reference', ['unique' => true])
            ->addIndex('provider_id')
            ->addLinkedColumn('parent_payment_id', $this->addTablePrefix('payment'), 'id', ['null' => true])
            ->create();

        $this->tablePrefix('payment_history')
            ->addLinkedColumn('payment_id', $this->addTablePrefix('payment'), 'id')
            ->addColumn('date', 'datetime')
            ->addColumn('entry', 'text')
            ->addColumn('data', 'json')
            ->create();

        $this->tablePrefix('payment_webhook')
            ->addLinkedColumn('gateway_id', $this->addTablePrefix('payment_gateway'), 'id')
            ->addColumn('date', 'datetime')
            ->addColumn('type', 'string', ['null' => true])
            ->addColumn('data', 'json')
            ->addColumn('headers', 'json')
            ->addColumn('processed', 'boolean')
            ->addColumn('verified', 'boolean')
            ->addColumn('decoded_data', 'json', ['null' => true])
            ->addColumn('error', 'text', ['null' => true])
            ->addIndex('type')
            ->create();
    }
}
