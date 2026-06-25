<?php

declare(strict_types=1);

return [
    // In-app notifications are always stored. Email is an optional channel that
    // is sent best-effort when enabled and the recipient has an email address.
    'channels' => [
        'mail' => (bool) env('NOTIFY_MAIL_ENABLED', false),
    ],
];
