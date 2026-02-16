<?php

declare(strict_types=1);

namespace Infrastructure\Console\Commands;

use Domain\Idempotency\Repositories\CommandInboxReadRepositoryInterface;
use Domain\Idempotency\Repositories\CommandInboxWriteRepositoryInterface;
use Domain\Outbox\Entities\OutboxEvent;
use Domain\Outbox\Repositories\OutboxReadRepositoryInterface;
use Domain\Outbox\Repositories\OutboxWriteRepositoryInterface;
use Domain\Outbox\Services\OutboxEventMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Infrastructure\Queue\OutboxQueuePublisher;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class ProcessOutboxCommand extends Command
{
    protected $signature = 'outbox:process
                            {--batch-size=100 : Número de eventos a processar por execução}
                            {--max-retries=3 : Número máximo de tentativas antes de marcar como FAILED}';

    protected $description = 'Processa eventos PENDING da outbox e publica no RabbitMQ';

    public function __construct(
        private readonly OutboxReadRepositoryInterface $outboxReadRepository,
        private readonly OutboxWriteRepositoryInterface $outboxWriteRepository,
        private readonly CommandInboxReadRepositoryInterface $commandInboxReadRepository,
        private readonly CommandInboxWriteRepositoryInterface $commandInboxWriteRepository,
        private readonly OutboxEventMapper $eventMapper,
        private readonly OutboxQueuePublisher $queuePublisher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $batchSize = $this->option('batch-size') !== null 
            ? (int) $this->option('batch-size') 
            : config('outbox.batch_size', 100);
        
        $maxRetries = $this->option('max-retries') !== null 
            ? (int) $this->option('max-retries') 
            : config('outbox.max_retries', 3);

        $this->info("🔄 [OutboxProcessor] Starting processing (batch size: $batchSize)");

        try {
            $events = $this->outboxReadRepository->findPendingEvents($batchSize);

            if (empty($events)) {
                $this->info('✅ [OutboxProcessor] No pending events found');
                return CommandAlias::SUCCESS;
            }

            $this->info("📋 [OutboxProcessor] Found " . count($events) . " pending events");

            $processed = 0;
            $sent = 0;
            $failed = 0;

            foreach ($events as $event) {
                try {
                    $result = $this->processEvent($event, $maxRetries);

                    if ($result === 'sent') {
                        $sent++;
                    } elseif ($result === 'failed') {
                        $failed++;
                    }

                    $processed++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::error("❌ [OutboxProcessor] Error processing event", [
                        'outboxId' => $event->id()->toString(),
                        'eventType' => $event->eventType(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->info("✅ [OutboxProcessor] Processing completed: $processed processed, $sent sent, $failed failed");

            return CommandAlias::SUCCESS;
        } catch (Throwable $e) {
            $this->error("💀 [OutboxProcessor] Fatal error: " . $e->getMessage());
            Log::critical("💀 [OutboxProcessor] Fatal error in ProcessOutboxCommand", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return CommandAlias::FAILURE;
        }
    }

    private function processEvent(OutboxEvent $event, int $maxRetries): string
    {
        if (!$this->outboxWriteRepository->markAsProcessing($event->id()->toString())) {
            Log::info('⏭️ [OutboxProcessor] Event already being processed, skipping', [
                'outboxId' => $event->id()->toString(),
            ]);
            return 'skipped';
        }

        try {
            $command = $this->commandInboxReadRepository->findByCommandId($event->aggregateId());

            if ($command === null) {
                Log::warning('⚠️ [OutboxProcessor] Command not found in command_inbox', [
                    'outboxId' => $event->id()->toString(),
                    'aggregateId' => $event->aggregateId(),
                ]);

                $this->outboxWriteRepository->markAsFailed(
                    $event->id()->toString(),
                    "Command not found: {$event->aggregateId()}"
                );

                return 'failed';
            }

            if (!$this->eventMapper->isSupported($event->eventType())) {
                Log::warning('⚠️ [OutboxProcessor] Unsupported event type', [
                    'outboxId' => $event->id()->toString(),
                    'eventType' => $event->eventType(),
                ]);

                $this->outboxWriteRepository->markAsFailed(
                    $event->id()->toString(),
                    "Unsupported event type: {$event->eventType()}"
                );

                return 'failed';
            }

            $this->queuePublisher->publishEvent($event, $command);

            $this->commandInboxWriteRepository->markAsEnqueued($command->id()->toString());

            $this->outboxWriteRepository->markAsSent($event->id()->toString());

            Log::info('✅ [OutboxProcessor] Event published successfully', [
                'outboxId' => $event->id()->toString(),
                'eventType' => $event->eventType(),
                'commandId' => $command->id()->toString(),
            ]);

            return 'sent';
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();

            $isTemporaryFailure = $this->isTemporaryFailure($e);

            if ($isTemporaryFailure) {
                DB::table('outbox')
                    ->where('id', $event->id()->toString())
                    ->update([
                        'status' => 'PENDING',
                    ]);

                Log::warning('⚠️ [OutboxProcessor] Temporary failure, will retry', [
                    'outboxId' => $event->id()->toString(),
                    'error' => $errorMessage,
                ]);

                return 'skipped';
            }

            $this->outboxWriteRepository->markAsFailed($event->id()->toString(), $errorMessage);

            Log::error('❌ [OutboxProcessor] Permanent failure', [
                'outboxId' => $event->id()->toString(),
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString(),
            ]);

            return 'failed';
        }
    }

    private function isTemporaryFailure(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        $temporaryPatterns = [
            'connection',
            'timeout',
            'unavailable',
            'network',
            'refused',
            'rabbitmq',
            'amqp',
        ];

        foreach ($temporaryPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }
}

