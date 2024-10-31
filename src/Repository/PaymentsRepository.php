<?php

namespace Pantono\Payments\Repository;

use Pantono\Database\Repository\MysqlRepository;
use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentMandate;

class PaymentsRepository extends MysqlRepository
{
    public function getPaymentById(int $id): ?array
    {
        return $this->selectSingleRow('payment', 'id', $id);
    }

    public function getPaymentGatewayById(int $id): ?array
    {
        return $this->selectSingleRow('payment_gateway', 'id', $id);
    }

    public function getProviderById(int $id): ?array
    {
        return $this->selectSingleRow('payment_provider', 'id', $id);
    }

    public function getPaymentStatusById(int $id): ?array
    {
        return $this->selectSingleRow('payment_status', 'id', $id);
    }

    public function savePayment(Payment $payment): void
    {
        $id = $this->insertOrUpdateCheck('payment', 'id', $payment->getId(), $payment->getAllData());
        if ($id) {
            $payment->setId($id);
        }
    }

    public function getPaymentMandateById(int $id): ?array
    {
        return $this->selectSingleRow('payment_mandate', 'id', $id);
    }

    public function saveMandate(PaymentMandate $mandate): void
    {
        $id = $this->insertOrUpdateCheck('payment_mandate', 'id', $mandate->getId(), $mandate->getAllData());
        if ($id) {
            $mandate->setId($id);
        }
    }
}
