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
     * @var array<int>|null
     */
    private ?array $statusIds = null;
    private ?bool $statusActive = null;
    private ?bool $statusCancelled = null;
    private ?bool $statusExpired = null;

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

    public function getStatusActive(): ?bool
    {
        return $this->statusActive;
    }

    public function setStatusActive(?bool $statusActive): void
    {
        $this->statusActive = $statusActive;
    }

    public function getStatusCancelled(): ?bool
    {
        return $this->statusCancelled;
    }

    public function setStatusCancelled(?bool $statusCancelled): void
    {
        $this->statusCancelled = $statusCancelled;
    }

    public function getStatusExpired(): ?bool
    {
        return $this->statusExpired;
    }

    public function setStatusExpired(?bool $statusExpired): void
    {
        $this->statusExpired = $statusExpired;
    }
}
