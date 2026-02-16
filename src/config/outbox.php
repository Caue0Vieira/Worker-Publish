<?php

return [
    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),
    'max_retries' => (int) env('OUTBOX_MAX_RETRIES', 3),
    'poll_interval' => (int) env('OUTBOX_POLL_INTERVAL', 60),
];

