<?php

declare(strict_types=1);

namespace JeromeJHipolito\AmqpToolkit\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            $model->uuid ??= Str::uuid()->toString();
        });
    }
}
