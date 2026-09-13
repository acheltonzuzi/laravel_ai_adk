<?php

namespace App\Enums;

enum RentalStatus: string
{
    case Pendente = 'pendente';
    case Confirmado = 'confirmado';
    case EmAndamento = 'em_andamento';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    /**
     * Get the label shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Confirmado => 'Confirmado',
            self::EmAndamento => 'Em andamento',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }

    /**
     * Determine whether this status still reserves the car (blocks the dates
     * and keeps the car marked as rented).
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Pendente, self::Confirmado, self::EmAndamento => true,
            self::Concluido, self::Cancelado => false,
        };
    }

    /**
     * Get the values of every status that still reserves the car.
     *
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->isActive()),
        ));
    }

    /**
     * Get every case as a value/label pair for the front end selects.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
