# 📤 Worker de Publicação de Eventos (Outbox Publisher)

Worker assíncrono desenvolvido com **Laravel** para processar eventos da tabela `outbox` e publicá-los no **RabbitMQ**. Segue os mesmos princípios de **DDD (Domain-Driven Design)** e **Arquitetura Hexagonal** da API e do Worker-Occurrence.

## 🚀 Como Rodar

### Pré-requisitos
- Docker e Docker Compose instalados
- RabbitMQ e PostgreSQL rodando (geralmente iniciados pela API)
- Tabelas `outbox` e `command_inbox` criadas no banco de dados

### Executando com Docker Compose

```bash
cd docker
docker-compose up -d
```

Isso irá subir o **Worker-Outbox** na porta `8015`.

### Configuração Inicial

Após subir o container, execute:

```bash
# Entrar no container do Worker
docker exec -it worker-outbox bash

# Instalar dependências
composer install

# Configurar ambiente
cp .env.example .env

# O Worker já inicia automaticamente o processamento agendado
```

## 🔄 Como Funciona

### Processamento de Outbox

O Worker verifica periodicamente a tabela `outbox` e publica eventos `PENDING` no RabbitMQ:

1. **Busca eventos PENDING** → Worker consulta eventos com status `PENDING` ordenados por `created_at`
2. **Lock para concorrência** → Usa `FOR UPDATE SKIP LOCKED` para evitar processamento duplicado
3. **Marca como PROCESSING** → Atualiza status para evitar que outras instâncias processem o mesmo evento
4. **Busca comando** → Busca o comando completo no `command_inbox` usando `aggregate_id`
5. **Mapeia evento** → Mapeia `event_type` para `commandType` e classe de Job
6. **Publica no RabbitMQ** → Cria e publica o Job correspondente na fila
7. **Atualiza status** → Marca como `SENT` (sucesso) ou `FAILED` (falha definitiva)

### Estados da Outbox

- **PENDING**: Evento aguardando publicação
- **PROCESSING**: Evento sendo processado (lock ativo, evita concorrência)
- **SENT**: Publicado com sucesso no RabbitMQ
- **FAILED**: Falha definitiva após N tentativas

### Eventos Suportados

O Worker processa os seguintes tipos de eventos:

#### Mapeamento Domain (eventType → commandType)
- `OccurrenceCreateRequested` → `create_occurrence`
- `OccurrenceStartRequested` → `start_occurrence`
- `OccurrenceResolvedRequested` → `resolve_occurrence`
- `DispatchCreateRequested` → `create_dispatch`
- `DispatchCloseRequested` → `close_dispatch`
- `DispatchStatusUpdateRequested` → `update_dispatch_status`

#### Mapeamento Infrastructure (commandType → Job)
- `create_occurrence` → `ProcessCreateOccurrenceJob`
- `start_occurrence` → `ProcessStartOccurrenceJob`
- `resolve_occurrence` → `ProcessResolveOccurrenceJob`
- `create_dispatch` → `ProcessCreateDispatchJob`
- `close_dispatch` → `ProcessCloseDispatchJob`
- `update_dispatch_status` → `ProcessUpdateDispatchStatusJob`

> **Nota**: O mapeamento `eventType → commandType` é o reverso do `OutboxEventResolver` da API (`commandType → eventType`).

### Agendamento

O comando `outbox:process` é executado automaticamente a cada minuto através do Laravel Scheduler (`schedule:work`).

### Tratamento de Erros

- **Falha temporária** (ex: RabbitMQ indisponível):
  - Evento é marcado como `PENDING` novamente
  - Será reprocessado na próxima execução
  - Não conta como tentativa definitiva

- **Falha definitiva** (ex: comando não encontrado, event_type não suportado):
  - Evento é marcado como `FAILED`
  - Requer intervenção manual para análise

### Concorrência

- Múltiplas instâncias do worker podem rodar simultaneamente
- `FOR UPDATE SKIP LOCKED` garante que cada evento seja processado apenas uma vez
- Lock ativo durante o processamento evita duplicação

## ⚙️ Configuração

### Variáveis de Ambiente

```env
# Outbox Processor
OUTBOX_BATCH_SIZE=100          # Quantos eventos processar por execução
OUTBOX_MAX_RETRIES=3           # Número máximo de tentativas
OUTBOX_POLL_INTERVAL=60        # Intervalo de polling em segundos (não usado diretamente, o scheduler roda a cada minuto)

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=occurrence_user
RABBITMQ_PASSWORD=occurrence_pass
RABBITMQ_QUEUE=occurrences.jobs
RABBITMQ_EXCHANGE=occurrences

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=occurrence_db
DB_USERNAME=occurrence_user
DB_PASSWORD=occurrence_pass
```

### Executar Manualmente

```bash
# Processar eventos PENDING
php artisan outbox:process

# Com opções customizadas
php artisan outbox:process --batch-size=50 --max-retries=5
```

## 📊 Monitoramento

### Logs

Os logs são salvos em:
- `storage/logs/outbox-processor.log` - Logs do comando agendado
- `storage/logs/laravel.log` - Logs gerais da aplicação

### Estrutura de Logs

```
🔄 [OutboxProcessor] Starting processing (batch size: 100)
📋 [OutboxProcessor] Found 15 pending events
✅ [OutboxProcessor] Event published successfully
❌ [OutboxProcessor] Permanent failure
⚠️ [OutboxProcessor] Temporary failure, will retry
✅ [OutboxProcessor] Processing completed: 15 processed, 14 sent, 1 failed
```

## 🏗️ Arquitetura

### Domain Layer

#### Entities
- **OutboxEvent**: Entity que representa um evento da outbox
  - Propriedades: `id`, `aggregateType`, `aggregateId`, `eventType`, `status`, `createdAt`
  - Métodos: `fromArray()`, `toArray()`, getters

- **Command**: Entity que representa um comando do command_inbox
  - Propriedades: `id`, `idempotencyKey`, `source`, `type`, `scopeKey`, `payloadHash`, `payload`, `status`, `result`, `errorMessage`, `processedAt`, `expiresAt`, `createdAt`, `updatedAt`
  - Métodos: `fromArray()`, `toArray()`, getters

#### Services
- **OutboxEventMapper**: Mapeia `event_type` → `commandType` (reverso do `OutboxEventResolver` da API)
  - Responsabilidade: Apenas mapeamento de domínio (eventType → commandType)
  - Não conhece detalhes de infraestrutura (Jobs)

#### Repositories (Interfaces)
- **OutboxReadRepositoryInterface**: Interface para leitura de eventos (`array<OutboxEvent>`)
- **OutboxWriteRepositoryInterface**: Interface para escrita/atualização de eventos
- **CommandInboxReadRepositoryInterface**: Interface para leitura de comandos (`?Command`)

### Infrastructure Layer

#### Repositories (Implementações)
- **OutboxReadRepository**: Implementação de leitura com `FOR UPDATE SKIP LOCKED`
  - Converte dados do banco para `OutboxEvent` entities

- **OutboxWriteRepository**: Implementação de escrita/atualização
  - Gerencia estados: `PENDING`, `PROCESSING`, `SENT`, `FAILED`

- **CommandInboxReadRepository**: Implementação de leitura de comandos
  - Converte dados do banco para `Command` entities

#### Queue
- **OutboxQueuePublisher**: Publica jobs no RabbitMQ
  - Mapeia `commandType` → `jobClass` (detalhe de infraestrutura)
  - Cria instâncias de Jobs e publica na fila

#### Console Commands
- **ProcessOutboxCommand**: Comando principal de processamento
  - Orquestra o fluxo completo de processamento
  - Gerencia tratamento de erros e retries

### Fluxo de Dados

```
1. ProcessOutboxCommand.handle()
   ↓
2. OutboxReadRepository.findPendingEvents()
   → Retorna: array<OutboxEvent>
   ↓
3. Para cada OutboxEvent:
   ↓
4. OutboxWriteRepository.markAsProcessing()
   → Lock ativo (evita concorrência)
   ↓
5. CommandInboxReadRepository.findByCommandId()
   → Retorna: ?Command
   ↓
6. OutboxEventMapper.resolve(eventType)
   → Retorna: commandType (string)
   ↓
7. OutboxQueuePublisher.publishEvent(OutboxEvent, Command)
   → resolveJobClass(commandType) → jobClass
   → createJobInstance() → Job
   → dispatch(Job) → RabbitMQ
   ↓
8. OutboxWriteRepository.markAsSent()
   → Status: SENT
```

### Separação de Responsabilidades

- **Domain**: Conhece apenas `eventType` e `commandType` (regras de negócio)
- **Infrastructure**: Conhece Jobs, RabbitMQ e detalhes de execução
- **Entities**: Tipagem forte substituindo `array` e `object`

## 🔧 Dependências

- Laravel 11.x
- php-amqplib/php-amqplib ^3.5
- vladimir-yuldashev/laravel-queue-rabbitmq ^14.0
- PostgreSQL 16+
- RabbitMQ 3.x

## 📝 Notas Importantes

1. **Jobs do Worker-Occurrence**: Os Jobs referenciados pelo `OutboxQueuePublisher` devem estar disponíveis no Worker-Occurrence. O Worker-Outbox apenas publica os jobs, não os processa.

2. **Banco de Dados Compartilhado**: O Worker-Outbox precisa acessar o mesmo banco de dados da API para ler as tabelas `outbox` e `command_inbox`.

3. **RabbitMQ Compartilhado**: O Worker-Outbox publica na mesma fila que o Worker-Occurrence consome (`occurrences.jobs`).

4. **Idempotência**: A idempotência é garantida pelo `command_inbox` e pelos Jobs do Worker-Occurrence, não pelo Worker-Outbox.

5. **Entities e Type Safety**: O projeto utiliza entities (`OutboxEvent` e `Command`) em vez de `array` e `object`, garantindo type safety e consistência com os outros projetos do sistema.

6. **Separação Domain/Infrastructure**: 
   - **Domain** (`OutboxEventMapper`): Mapeia apenas `eventType → commandType` (regras de negócio)
   - **Infrastructure** (`OutboxQueuePublisher`): Mapeia `commandType → jobClass` (detalhes de execução)

## 🐛 Troubleshooting

### Eventos não estão sendo processados

1. Verifique se o scheduler está rodando: `php artisan schedule:list`
2. Verifique os logs: `tail -f storage/logs/outbox-processor.log`
3. Verifique se há eventos PENDING: `SELECT COUNT(*) FROM outbox WHERE status = 'PENDING'`

### Erro de conexão com RabbitMQ

1. Verifique se o RabbitMQ está rodando
2. Verifique as credenciais no `.env`
3. Verifique a conectividade de rede entre containers

### Eventos ficam em PROCESSING

Isso pode acontecer se o worker foi interrompido durante o processamento. Você pode resetar manualmente:

```sql
UPDATE outbox SET status = 'PENDING' WHERE status = 'PROCESSING' AND updated_at < NOW() - INTERVAL '5 minutes';
```

---
