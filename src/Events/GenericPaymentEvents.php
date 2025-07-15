<?php

namespace Pantono\Payments\Events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pantono\Queue\QueueManager;
use Pantono\Payments\Event\PaymentWebhookEvent;
use Pantono\Payments\Event\PostMandateSaveSaveEvent;
use Pantono\Payments\Payments;

class GenericPaymentEvents implements EventSubscriberInterface
{
    private QueueManager $queueManager;
    private Payments $payments;

    public function __construct(QueueManager $queueManager, Payments $payments)
    {
        $this->queueManager = $queueManager;
        $this->payments = $payments;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentWebhookEvent::class => ['createQueueTask', 255],
            PostMandateSaveSaveEvent::class => ['addHistory', -255]
        ];
    }

    public function createQueueTask(PaymentWebhookEvent $event): void
    {
        $this->queueManager->createTask('payment_webhook', ['id' => $event->getWebhook()->getId(), 'data' => $event->getWebhook()->getData()]);
    }

    public function addHistory(PostMandateSaveSaveEvent $event): void
    {
        if ($event->getPrevious() === null) {
            $this->payments->addHistoryToMandate($event->getCurrent(), 'Created new mandate');
            return;
        }
        if ($event->getPrevious()->getStatus()->getId() === $event->getCurrent()->getStatus()->getId()) {
            $this->payments->addHistoryToMandate($event->getCurrent(), 'Changed status from ' . $event->getPrevious()->getStatus()->getName() . ' to ' . $event->getCurrent()->getStatus()->getName());
        }
    }
}
