<?php

namespace Pantono\Payments\Repository;

use Pantono\Database\Repository\MysqlRepository;
use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentMandate;
use Pantono\Payments\Model\PaymentGateway;
use Pantono\Payments\Model\PaymentWebhook;
use Pantono\Payments\Filter\PaymentFilter;
use Pantono\Customers\Model\Customer;

class PaymentsRepository extends MysqlRepository
{
    public function getPaymentById(int $id): ?array
    {
        return $this->selectSingleRowLock('payment', 'id', $id);
    }


    public function getPaymentByProviderId(string $id): ?array
    {
        return $this->selectSingleRow('payment', 'provider_id', $id);
    }

    public function getPaymentByReference(mixed $reference): ?array
    {
        return $this->selectSingleRowLock('payment', 'reference', $reference);
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
        return $this->selectSingleRowLock('payment_mandate', 'id', $id);
    }

    public function saveMandate(PaymentMandate $mandate): void
    {
        $id = $this->insertOrUpdateCheck('payment_mandate', 'id', $mandate->getId(), $mandate->getAllData());
        if ($id) {
            $mandate->setId($id);
        }
    }

    public function getMandateStatusById(int $id): ?array
    {
        return $this->selectSingleRow('payment_mandate_status', 'id', $id);
    }

    public function saveGateway(PaymentGateway $gateway): void
    {
        $id = $this->insertOrUpdateCheck('payment_gateway', 'id', $gateway->getId(), $gateway->getAllData());
        if ($id) {
            $gateway->setId($id);
        }
    }

    public function getGatewaysByProvider(int $id): array
    {
        return $this->selectRowsByValues('payment_gateway', ['provider_id' => $id]);
    }

    public function getPaymentMandateByReference(string $reference): ?array
    {
        return $this->selectSingleRowLock('payment_mandate', 'reference', $reference);
    }

    public function saveWebhook(PaymentWebhook $webhook): void
    {
        $id = $this->insertOrUpdate('payment_webhook', 'id', $webhook->getId(), $webhook->getAllData());
        if ($id) {
            $webhook->setId($id);
        }
    }

    public function addHistoryToPayment(Payment $payment, string $entry, array $data = [], ?\DateTimeInterface $date = null): void
    {
        $this->getDb()->insert('payment_history', [
            'payment_id' => $payment->getId(),
            'date' => ($date ?: new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'entry' => $entry,
            'data' => json_encode($data)
        ]);
    }

    public function getPaymentsByFilter(PaymentFilter $filter): array
    {
        $select = $this->getDb()->select()->from('payment');

        if ($filter->getAuthCode() !== null) {
            $select->where('payment.auth_code=?', $filter->getAuthCode());
        }

        if ($filter->getProviderId() !== null) {
            $select->where('payment.provider_id=?', $filter->getProviderId());
        }

        if ($filter->getMinAmountInPence() !== null) {
            $select->where('payment.amount >= ?', $filter->getMinAmountInPence());
        }
        if ($filter->getMaxAmountInPence() !== null) {
            $select->where('payment.amount <= ?', $filter->getMaxAmountInPence());
        }

        if ($filter->getMandate() !== null) {
            $select->where('payment.mandate_id=?', $filter->getMandate()->getId());
        }

        $filter->setTotalResults($this->getCount($select));

        $select->limitPage($filter->getPage(), $filter->getPerPage());

        return $this->getDb()->fetchAll($select);
    }

    public function getMandatesForCustomer(Customer $customer): array
    {
        $select = $this->getDb()->select()->from('payment_mandate')
            ->where('payment_mandate.customer_id=?', $customer->getId());

        return $this->getDb()->fetchAll($select);
    }

    public function addHistoryToMandate(PaymentMandate $mandate, string $entry, array $data, ?\DateTimeInterface $date = null): void
    {
        $this->getDb()->insert('payment_mandate_history', [
            'mandate_id' => $mandate->getId(),
            'entry' => $entry,
            'date' => ($date ?: new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'data' => json_encode($data)
        ]);
    }
}
