import { Head, Link, router } from '@inertiajs/react';
import { CarFront, Pencil, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CarFormDialog } from '@/components/cars/car-form-dialog';
import { DeleteCarDialog } from '@/components/cars/delete-car-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatCurrency } from '@/lib/currency';
import { index as carsIndex } from '@/routes/cars';
import type {
    CarAvailabilityValue,
    CarConditionValue,
    CarFilters,
    EnumOption,
    ManagedCar,
} from '@/types/car';
import type { Paginated } from '@/types/user';

type Props = {
    cars: Paginated<ManagedCar>;
    filters: CarFilters;
    conditions: EnumOption[];
    availabilities: EnumOption[];
};

const ALL = 'todos';

const conditionVariant: Record<
    CarConditionValue,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    novo: 'default',
    bom: 'secondary',
    regular: 'outline',
    danificado: 'destructive',
};

const availabilityVariant: Record<
    CarAvailabilityValue,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    disponivel: 'default',
    alugado: 'secondary',
    vendido: 'outline',
    manutencao: 'destructive',
};

export default function CarsIndex({
    cars,
    filters,
    conditions,
    availabilities,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (next: Partial<CarFilters>) => {
        router.get(
            carsIndex().url,
            {
                search: next.search ?? search,
                condition: next.condition ?? filters.condition,
                availability: next.availability ?? filters.availability,
            },
            { preserveState: true, replace: true },
        );
    };

    useEffect(() => {
        if (search === filters.search) {
            return;
        }

        const timeout = setTimeout(() => applyFilters({ search }), 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, filters.search]);

    return (
        <>
            <Head title="Veículos" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Veículos"
                        description="Inventário da frota: cadastre, edite e acompanhe a disponibilidade"
                    />

                    <CarFormDialog
                        conditions={conditions}
                        availabilities={availabilities}
                        trigger={
                            <Button>
                                <Plus />
                                Novo veículo
                            </Button>
                        }
                    />
                </div>

                <div className="flex flex-wrap gap-3">
                    <div className="relative min-w-56 flex-1 sm:max-w-sm">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar por código, matrícula, marca…"
                            className="pl-9"
                            aria-label="Buscar veículos"
                        />
                    </div>

                    <Select
                        value={
                            filters.condition === '' ? ALL : filters.condition
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                condition: value === ALL ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-44" aria-label="Estado">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                Todos os estados
                            </SelectItem>
                            {conditions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.availability === ''
                                ? ALL
                                : filters.availability
                        }
                        onValueChange={(value) =>
                            applyFilters({
                                availability: value === ALL ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label="Disponibilidade"
                        >
                            <SelectValue placeholder="Disponibilidade" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                Todas as disponibilidades
                            </SelectItem>
                            {availabilities.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-muted-foreground border-border/70 border-b text-xs">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Veículo
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Ano
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Preço
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Localização
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Disponibilidade
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Ações
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {cars.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground px-4 py-10 text-center"
                                        >
                                            Nenhum veículo encontrado.
                                        </td>
                                    </tr>
                                ) : (
                                    cars.data.map((car) => (
                                        <tr
                                            key={car.id}
                                            className="border-border/40 border-b last:border-0"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    {car.image_url === null ? (
                                                        <div className="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-md">
                                                            <CarFront className="size-4" />
                                                        </div>
                                                    ) : (
                                                        <img
                                                            src={car.image_url}
                                                            alt=""
                                                            loading="lazy"
                                                            className="size-10 shrink-0 rounded-md object-cover"
                                                        />
                                                    )}

                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {car.brand}{' '}
                                                            {car.model}
                                                        </p>
                                                        <p className="text-muted-foreground truncate text-xs">
                                                            {car.code} ·{' '}
                                                            {car.license_plate}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="text-muted-foreground px-4 py-3">
                                                {car.year}
                                            </td>

                                            <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                {formatCurrency(car.price)}
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        conditionVariant[
                                                            car.condition
                                                        ]
                                                    }
                                                >
                                                    {car.condition_label}
                                                </Badge>
                                            </td>

                                            <td className="text-muted-foreground px-4 py-3">
                                                {car.location ?? '—'}
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        availabilityVariant[
                                                            car.availability
                                                        ]
                                                    }
                                                >
                                                    {car.availability_label}
                                                </Badge>
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <CarFormDialog
                                                        car={car}
                                                        conditions={conditions}
                                                        availabilities={
                                                            availabilities
                                                        }
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Editar ${car.code}`}
                                                            >
                                                                <Pencil />
                                                            </Button>
                                                        }
                                                    />

                                                    <DeleteCarDialog
                                                        car={car}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {cars.total > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-muted-foreground text-xs">
                            Mostrando {cars.from ?? 0}–{cars.to ?? 0} de{' '}
                            {cars.total}
                        </p>

                        <div className="flex flex-wrap gap-1">
                            {cars.links.map((link) =>
                                link.url === null ? (
                                    <span
                                        key={link.label}
                                        className="text-muted-foreground px-3 py-1.5 text-xs"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <Button
                                        key={link.label}
                                        asChild
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                    >
                                        <Link
                                            href={link.url}
                                            preserveState
                                            preserveScroll
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Button>
                                ),
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

CarsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Veículos',
            href: '/cars',
        },
    ],
};
