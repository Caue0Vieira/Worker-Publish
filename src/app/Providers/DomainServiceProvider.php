<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Idempotency\Repositories\CommandInboxReadRepositoryInterface;
use Domain\Outbox\Repositories\OutboxReadRepositoryInterface;
use Domain\Outbox\Repositories\OutboxWriteRepositoryInterface;
use Domain\Outbox\Services\OutboxEventMapper;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Console\Commands\ProcessOutboxCommand;
use Infrastructure\Persistence\Repositories\CommandInboxReadRepository;
use Infrastructure\Persistence\Repositories\OutboxReadRepository;
use Infrastructure\Persistence\Repositories\OutboxWriteRepository;
use Infrastructure\Queue\OutboxQueuePublisher;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OutboxReadRepositoryInterface::class,
            OutboxReadRepository::class
        );

        $this->app->bind(
            OutboxWriteRepositoryInterface::class,
            OutboxWriteRepository::class
        );

        $this->app->bind(
            CommandInboxReadRepositoryInterface::class,
            CommandInboxReadRepository::class
        );

        $this->app->singleton(OutboxEventMapper::class);
        $this->app->singleton(OutboxQueuePublisher::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessOutboxCommand::class,
            ]);
        }
    }
}

