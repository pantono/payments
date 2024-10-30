<?php

namespace Pantono\Payments\Model;

use Pantono\Contracts\Attributes\Filter;

class PaymentHistory
{
    private ?int $id = null;
    private int $paymentId;
    private \DateTimeImmutable $date;
    private PaymentStatus $status;
    private string $entry;
    #[Filter('json_decode')]
    private array $data;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getPaymentId(): int
    {
        return $this->paymentId;
    }

    public function setPaymentId(int $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): void
    {
        $this->status = $status;
    }

    public function getEntry(): string
    {
        return $this->entry;
    }

    public function setEntry(string $entry): void
    {
        $this->entry = $entry;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
