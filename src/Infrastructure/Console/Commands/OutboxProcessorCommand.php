<?php

declare(strict_types=1);

namespace Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class OutboxProcessorCommand extends Command
{
    protected $signature = 'outbox:processor
                            {--interval= : Intervalo em segundos (sobrescreve OUTBOX_POLL_INTERVAL)}';

    protected $description = 'Processa eventos da outbox em loop contínuo com intervalo configurável';

    public function handle(): int
    {
        $pollInterval = (int) ($this->option('interval') ?: config('outbox.poll_interval', 60));

        if ($pollInterval < 1) {
            $this->error('Intervalo deve ser maior ou igual a 1 segundo');
            return self::FAILURE;
        }

        $this->info("🔄 [OutboxProcessor] Iniciando processamento contínuo (intervalo: {$pollInterval}s)");

        while (true) {
            try {
                $exitCode = Artisan::call('outbox:process');
                
                if ($exitCode !== 0) {
                    $this->warn("⚠️ [OutboxProcessor] Processamento retornou código: {$exitCode}");
                }
                
                sleep($pollInterval);
            } catch (\Throwable $e) {
                $this->error("❌ [OutboxProcessor] Erro: " . $e->getMessage());
                sleep($pollInterval);
            }
        }
    }
}

