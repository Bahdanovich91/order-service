<?php

declare(strict_types=1);

namespace App\Service;

use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Interop\Queue\Context;
use Interop\Queue\Producer;
use Psr\Log\LoggerInterface;

class KafkaService
{
    private Context $context;

    private Producer $producer;

    public function __construct(
        string $kafkaBroker,
        private readonly LoggerInterface $logger
    ) {
        $factory = new RdKafkaConnectionFactory([
            'global' => [
                'group.id'             => 'order-service',
                'metadata.broker.list' => $kafkaBroker,
                'enable.auto.commit'   => 'true',
            ],
        ]);

        $this->context  = $factory->createContext();
        $this->producer = $this->context->createProducer();
    }

    public function sendCommand(string $topic, array $data, ?string $correlationId = null): void
    {
        try {
            $kafkaTopic = $this->context->createTopic($topic);
            $message    = $this->context->createMessage(
                json_encode($data, JSON_THROW_ON_ERROR)
            );

            if ($correlationId !== null) {
                $message->setCorrelationId($correlationId);
            }

            $this->producer->send($kafkaTopic, $message);

            $this->logger->info('Kafka command sent', [
                'topic'          => $topic,
                'correlation_id' => $correlationId,
                'data'           => $data,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Kafka command', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function sendEvent(string $topic, array $data): void
    {
        try {
            $kafkaTopic = $this->context->createTopic($topic);
            $message    = $this->context->createMessage(
                json_encode($data, JSON_THROW_ON_ERROR)
            );

            $this->producer->send($kafkaTopic, $message);

            $this->logger->info('Kafka event sent', [
                'topic' => $topic,
                'data'  => $data,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Kafka event', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
