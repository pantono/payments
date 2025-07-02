<?php

namespace Pantono\Payments\Events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pantono\Queue\QueueManager;
use Pantono\Payments\Event\PaymentWebhookEvent;

class GenericPaymentEvents implements EventSubscriberInterface
{
    private QueueManager $queueManager;

    public function __construct(QueueManager $queueManager)
    {
        $this->queueManager = $queueManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentWebhookEvent::class => ['createQueueTask', 255]
        ];
    }

    public function createQueueTask(PaymentWebhookEvent $event): void
    {
        $this->queueManager->createTask('payment_webhook', ['id' => $event->getWebhook()->getId(), 'data' => $event->getWebhook()->getData()]);
    }
}
