<?php

namespace Pantono\Payments\Model;

use Pantono\Contracts\Attributes\Filter;

class PaymentGateway
{
    private ?int $id = null;
    private PaymentProvider $provider;
    private string $name;
    #[Filter('json_decode')]
    private array $settings;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getProvider(): PaymentProvider
    {
        return $this->provider;
    }

    public function setProvider(PaymentProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }
}
