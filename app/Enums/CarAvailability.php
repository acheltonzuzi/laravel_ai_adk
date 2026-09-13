<?php

namespace App\Enums;

enum CarAvailability: string
{
    case Disponivel = 'disponivel';
    case Alugado = 'alugado';
    case Vendido = 'vendido';
    case Manutencao = 'manutencao';

    /**
     * Get the label shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Disponivel => 'Disponível',
            self::Alugado => 'Alugado',
            self::Vendido => 'Vendido',
            self::Manutencao => 'Manutenção',
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
            fn (self $availability): array => [
                'value' => $availability->value,
                'label' => $availability->label(),
            ],
            self::cases(),
        );
    }
}
