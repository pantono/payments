<?php

namespace Pantono\Payments\Model;

use Pantono\Contracts\Attributes\Filter;
use Pantono\Database\Traits\SavableModel;

class StripeWebhook
{
    use SavableModel;

    private ?int $id = null;
    private \DateTimeImmutable $date;
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

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): void
    {
        $this->date = $date;
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
