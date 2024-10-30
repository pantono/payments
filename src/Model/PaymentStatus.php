<?php

namespace Pantono\Payments\Model;

class PaymentStatus
{
    private ?int $id = null;
    private string $name;
    private bool $completed;
    private bool $pending;
    private bool $failed;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): void
    {
        $this->completed = $completed;
    }

    public function isPending(): bool
    {
        return $this->pending;
    }

    public function setPending(bool $pending): void
    {
        $this->pending = $pending;
    }

    public function isFailed(): bool
    {
        return $this->failed;
    }

    public function setFailed(bool $failed): void
    {
        $this->failed = $failed;
    }
}
