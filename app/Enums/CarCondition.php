<?php

namespace App\Enums;

enum CarCondition: string
{
    case Novo = 'novo';
    case Bom = 'bom';
    case Regular = 'regular';
    case Danificado = 'danificado';

    /**
     * Get the label shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Novo => 'Novo',
            self::Bom => 'Bom',
            self::Regular => 'Regular',
            self::Danificado => 'Danificado',
        };
    }

    /**
     * Get every case as a value/label pair for the front end selects.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $condition): array => [
                'value' => $condition->value,
                'label' => $condition->label(),
            ],
            self::cases(),
        );
    }
}
