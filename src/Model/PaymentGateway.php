<?php

namespace Pantono\Payments\Model;

use Pantono\Contracts\Attributes\Filter;
use Pantono\Contracts\Attributes\Locator;
use Pantono\Payments\Payments;
use Pantono\Contracts\Attributes\FieldName;

class PaymentGateway
{
    private ?int $id = null;
    #[Locator(methodName: 'getProviderById', className: Payments::class), FieldName('provider_id')]
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

    public function getSetting(string $name): mixed
    {
        $settings = $this->getSettings();
        return $settings[$name] ?? null;
    }
}
