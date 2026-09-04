<?php

namespace Pantono\Payments\Filter;

use Pantono\Contracts\Filter\PageableInterface;
use Pantono\Database\Traits\Pageable;
use Pantono\Customers\Model\Customer;
use Pantono\Payments\Model\PaymentMandateStatus;

class PaymentMandateFilter implements PageableInterface
{
    use Pageable;

    private ?Customer $customer = null;
    private ?PaymentMandateStatus $status = null;
    private ?\DateTimeInterface $dateCreatedStart = null;
    private ?\DateTimeInterface $dateCreatedEnd = null;
    /**
     * @var array<int>
     */
    private ?array $statusIds = [];

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getStatus(): ?PaymentMandateStatus
    {
        return $this->status;
    }

    public function setStatus(?PaymentMandateStatus $status): void
    {
        $this->status = $status;
    }

    public function getDateCreatedStart(): ?\DateTimeInterface
    {
        return $this->dateCreatedStart;
    }

    public function setDateCreatedStart(?\DateTimeInterface $dateCreatedStart): void
    {
        $this->dateCreatedStart = $dateCreatedStart;
    }

    public function getDateCreatedEnd(): ?\DateTimeInterface
    {
        return $this->dateCreatedEnd;
    }

    public function setDateCreatedEnd(?\DateTimeInterface $dateCreatedEnd): void
    {
        $this->dateCreatedEnd = $dateCreatedEnd;
    }

    public function getStatusIds(): ?array
    {
        return $this->statusIds;
    }

    public function setStatusIds(?array $statusIds): void
    {
        $this->statusIds = $statusIds;
    }
}
