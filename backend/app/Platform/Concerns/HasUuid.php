<?php

declare(strict_types=1);

namespace App\Platform\Concerns;

use Illuminate\Support\Str;

/**
 * Generates a public UUID on create and enables UUID-based route binding so
 * internal numeric ids are never exposed in URLs.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
