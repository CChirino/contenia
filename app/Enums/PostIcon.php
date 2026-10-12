<?php

namespace App\Enums;

enum PostIcon: string
{
    case Fingerprint = 'fingerprint';
    case Checklist = 'checklist';
    case Calendar = 'calendar';
    case Chart = 'chart';
    case Book = 'book';
    case Bell = 'bell';
    case Chat = 'chat';
    case Clock = 'clock';
    case Shield = 'shield';
    case Lightbulb = 'lightbulb';
    case Users = 'users';
    case Device = 'device';

    /**
     * Icono por defecto cuando el post no tiene uno válido.
     */
    public static function default(): self
    {
        return self::Lightbulb;
    }

    /**
     * Normaliza un valor libre (del LLM o del usuario): devuelve el caso o null.
     */
    public static function normalize(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        return self::tryFrom(mb_strtolower(trim($value)));
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $i) => $i->value, self::cases());
    }
}
