<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Queue\FailureStrategy;

use InvalidArgumentException;
use RuntimeException;
use Yiisoft\Yii\Queue\Message\MessageInterface;
use Yiisoft\Yii\Queue\Payload\PayloadInterface;
use Yiisoft\Yii\Queue\PayloadFactory;
use Yiisoft\Yii\Queue\Queue;

final class ExponentialDelayStrategy implements FailureStrategyInterface
{
    public const META_KEY_ATTEMPTS = 'failure-strategy-exponential-delay-attempts';
    public const META_KEY_DELAY = 'failure-strategy-exponential-delay-delay';

    private int $maxAttempts;
    private float $delayInitial;
    private float $delayMaximum;
    private float $exponent;
    private Queue $queue;
    private PayloadFactory $factory;

    /**
     * ExponentialDelayStrategy constructor.
     *
     * @param int $maxAttempts Maximum attempts count before this strategy will give up
     * @param float $delayInitial
     * @param float $delayMaximum
     * @param float $exponent
     * @param PayloadFactory $factory
     * @param Queue $queue
     */
    public function __construct(
        int $maxAttempts,
        float $delayInitial,
        float $delayMaximum,
        float $exponent,
        PayloadFactory $factory,
        Queue $queue
    ) {
        if ($maxAttempts <= 0) {
            throw new InvalidArgumentException('maxAttempts parameter must be a positive integer');
        }

        $this->maxAttempts = $maxAttempts;
        $this->delayInitial = $delayInitial;
        $this->delayMaximum = $delayMaximum;
        $this->exponent = $exponent;
        $this->factory = $factory;
        $this->queue = $queue;
    }

    public function suites(MessageInterface $message): bool
    {
        return $this->maxAttempts < $this->getAttempts($message->getPayloadMeta());
    }

    public function handle(MessageInterface $message, $stack): void
    {
        if ($this->suites($message)) {
            $meta = $message->getPayloadMeta();
            $metaNew = $this->formNewMeta($meta);
            $payload = $this->factory->createPayload($message, $metaNew);
            $this->queue->push($payload);
        } else {
            $stack->continue($message);
        }
    }

    private function formNewMeta(array $meta): array
    {
        return [
            PayloadInterface::META_KEY_DELAY => $this->getDelay($meta),
            self::META_KEY_DELAY => $this->getDelay($meta),
            self::META_KEY_ATTEMPTS => $this->getAttempts($meta) + 1,
        ];
    }

    private function getAttempts(array $meta): int
    {
        return $meta[self::META_KEY_ATTEMPTS] ?? 0;
    }

    private function getDelay(array $meta): float
    {
        $delayOriginal = $meta[self::META_KEY_DELAY] ?? $this->delayInitial;
        $result = $delayOriginal * $this->exponent;

        return min($result, $this->delayMaximum);
    }
}