<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Payments extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payment_provider')
            ->addColumn('name', 'string')
            ->addColumn('controller', 'string')
            ->create();
        if ($this->isMigratingUp()) {
            $this->table('payment_provider')
                ->insert([
                    ['id' => 1, 'name' => 'Stripe', 'controller' => 'Pantono\Payments\Provider\Stripe'],
                    ['id' => 2, 'name' => 'Braintree', 'controller' => 'Pantono\Payments\Provider\Braintree'],
                    ['id' => 3, 'name' => 'Go Cardless', 'controller' => 'Pantono\Payments\Provider\GoCardless'],
                ])->saveData();
        }

        $this->table('payment_gateway')
            ->addColumn('name', 'string')
            ->addColumn('provider_id', 'integer', ['signed' => false])
            ->addColumn('settings', 'json')
            ->addForeignKey('provider_id', 'payment_provider', 'id')
            ->create();

        $this->table('payment_status')
            ->addColumn('name', 'string')
            ->addColumn('completed', 'boolean')
            ->addColumn('pending', 'boolean')
            ->addColumn('failed', 'boolean')
            ->create();

        if ($this->isMigratingUp()) {
            $this->table('payment_status')
                ->insert([
                    ['id' => 1, 'name' => 'Pending', 'completed' => 0, 'pending' => 1, 'failed' => 0],
                    ['id' => 2, 'name' => 'Completed', 'completed' => 1, 'pending' => 0, 'failed' => 0],
                    ['id' => 3, 'name' => 'Failed', 'completed' => 0, 'pending' => 0, 'failed' => 1],
                    ['id' => 4, 'name' => 'Chargeback', 'completed' => 0, 'pending' => 0, 'failed' => 1],
                ])->saveData();
        }

        $this->table('payment_mandate_status')
            ->addColumn('name', 'string')
            ->addColumn('active', 'boolean')
            ->addColumn('cancelled', 'boolean')
            ->addColumn('expired', 'boolean')
            ->create();

        if ($this->isMigratingUp()) {
            $this->table('payment_mandate_status')
                ->insert([
                    ['id' => 1, 'name' => 'Pending', 'active' => 0, 'cancelled' => 0, 'expired' => 0],
                    ['id' => 2, 'name' => 'Active', 'active' => 1, 'cancelled' => 0, 'expired' => 0],
                    ['id' => 3, 'name' => 'Cancelled', 'active' => 0, 'cancelled' => 1, 'expired' => 0],
                    ['id' => 4, 'name' => 'Cancelled', 'active' => 0, 'cancelled' => 0, 'expired' => 1],
                ])->saveData();
        }

        $this->table('payment_mandate')
            ->addColumn('gateway_id', 'integer', ['signed' => false])
            ->addColumn('reference', 'string', ['null' => true])
            ->addColumn('status_id', 'integer', ['signed' => false])
            ->addColumn('start_date', 'date', ['null' => true])
            ->addColumn('end_date', 'date', ['null' => true])
            ->addColumn('currency', 'string')
            ->addColumn('setup_data', 'json')
            ->addColumn('response_data', 'json')
            ->addForeignKey('gateway_id', 'payment_gateway', 'id')
            ->addForeignKey('status_id', 'payment_mandate_status', 'id')
            ->create();

        $this->table('payment_mandate_history')
            ->addColumn('mandate_id', 'integer', ['signed' => false])
            ->addColumn('date', 'datetime')
            ->addColumn('entry', 'text')
            ->create();

        $this->table('payment')
            ->addColumn('request_data', 'json')
            ->addColumn('gateway_id', 'integer', ['signed' => false])
            ->addColumn('mandate_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('provider_id', 'string', ['null' => true])
            ->addColumn('reference', 'string', ['null' => true])
            ->addColumn('currency', 'string', ['null' => true])
            ->addColumn('payment_method_name', 'string', ['null' => true])
            ->addColumn('card_data', 'json', ['null' => true])
            ->addColumn('amount', 'integer')
            ->addColumn('status_id', 'integer', ['signed' => false])
            ->addColumn('response_data', 'json')
            ->addColumn('date_created', 'datetime')
            ->addColumn('date_updated', 'datetime')
            ->addColumn('data', 'json')
            ->addColumn('redirect_url', 'string', ['null' => true])
            ->addForeignKey('status_id', 'payment_status', 'id')
            ->addForeignKey('gateway_id', 'payment_gateway', 'id')
            ->addIndex('provider_id')
            ->create();

        $this->table('payment_history')
            ->addColumn('payment_id', 'integer', ['signed' => false])
            ->addColumn('date', 'datetime')
            ->addColumn('entry', 'text')
            ->addColumn('data', 'json')
            ->addForeignKey('payment_id', 'payment', 'id')
            ->create();

        $this->table('payment_webhook')
            ->addColumn('gateway_id', 'integer', ['signed' => false])
            ->addColumn('date', 'datetime')
            ->addColumn('type', 'string', ['null' => true])
            ->addColumn('data', 'json')
            ->addColumn('headers', 'json')
            ->addColumn('processed', 'boolean')
            ->addColumn('verified', 'boolean')
            ->addIndex('type')
            ->addForeignKey('gateway_id', 'payment_gateway', 'id')
            ->create();
    }
}
