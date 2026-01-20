<?php

declare(strict_types=1);

namespace JeromeJHipolito\AmqpToolkit\Enums;

enum FailedAmqpMessageStatus: string
{
    case FAILED      = 'failed';
    case RETRYING    = 'retrying';
    case DEAD_LETTER = 'dead_letter';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::FAILED      => 'Failed',
            self::RETRYING    => 'Retrying',
            self::DEAD_LETTER => 'Dead Letter',
        };
    }

    public function canRetry(): bool
    {
        return $this !== self::DEAD_LETTER;
    }
}
