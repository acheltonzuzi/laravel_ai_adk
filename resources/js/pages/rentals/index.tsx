import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DeleteRentalDialog } from '@/components/rentals/delete-rental-dialog';
import { RentalFormDialog } from '@/components/rentals/rental-form-dialog';
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
import type { EnumOption } from '@/types/car';
import { formatCurrency } from '@/lib/currency';
import { index as rentalsIndex } from '@/routes/rentals';
import type {
    ManagedRental,
    RentalCarOption,
    RentalFilters,
    RentalStatusValue,
    RentalUserOption,
} from '@/types/rental';
import type { Paginated } from '@/types/user';

type Props = {
    rentals: Paginated<ManagedRental>;
    filters: RentalFilters;
    statuses: EnumOption[];
    users: RentalUserOption[];
    cars: RentalCarOption[];
};

const ALL = 'todos';

const dateFormatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' });

function formatDate(value: string): string {
    return dateFormatter.format(new Date(`${value}T00:00:00`));
}

const statusVariant: Record<
    RentalStatusValue,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pendente: 'outline',
    confirmado: 'secondary',
    em_andamento: 'default',
    concluido: 'secondary',
    cancelado: 'destructive',
};

export default function RentalsIndex({
    rentals,
    filters,
    statuses,
    users,
    cars,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (next: Partial<RentalFilters>) => {
        router.get(
            rentalsIndex().url,
            {
                search: next.search ?? search,
                status: next.status ?? filters.status,
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
            <Head title="Aluguéis" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Aluguéis"
                        description="Reserve veículos para os clientes e acompanhe o status de cada aluguel"
                    />

                    <RentalFormDialog
                        users={users}
                        cars={cars}
                        statuses={statuses}
                        trigger={
                            <Button>
                                <Plus />
                                Novo aluguel
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
                            placeholder="Buscar por matrícula, código, cliente…"
                            className="pl-9"
                            aria-label="Buscar aluguéis"
                        />
                    </div>

                    <Select
                        value={filters.status === '' ? ALL : filters.status}
                        onValueChange={(value) =>
                            applyFilters({ status: value === ALL ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-48" aria-label="Status">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Todos os status</SelectItem>
                            {statuses.map((option) => (
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
                                        Utilizador
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Veículo
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Período
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Diária
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Total
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Ações
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {rentals.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground px-4 py-10 text-center"
                                        >
                                            Nenhum aluguel encontrado.
                                        </td>
                                    </tr>
                                ) : (
                                    rentals.data.map((rental) => (
                                        <tr
                                            key={rental.id}
                                            className="border-border/40 border-b last:border-0"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">
                                                        {rental.user.name}
                                                    </p>
                                                    <p className="text-muted-foreground truncate text-xs">
                                                        {rental.user.email}
                                                    </p>
                                                </div>
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">
                                                        {rental.car.brand}{' '}
                                                        {rental.car.model}
                                                    </p>
                                                    <p className="text-muted-foreground truncate text-xs">
                                                        {rental.car.code} ·{' '}
                                                        {
                                                            rental.car
                                                                .license_plate
                                                        }
                                                    </p>
                                                </div>
                                            </td>

                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                                {formatDate(rental.start_date)}{' '}
                                                – {formatDate(rental.end_date)}
                                                <p className="text-xs">
                                                    {rental.days}{' '}
                                                    {rental.days === 1
                                                        ? 'dia'
                                                        : 'dias'}
                                                </p>
                                            </td>

                                            <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                {formatCurrency(
                                                    rental.daily_rate,
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                {formatCurrency(rental.total)}
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        statusVariant[
                                                            rental.status
                                                        ]
                                                    }
                                                >
                                                    {rental.status_label}
                                                </Badge>
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <RentalFormDialog
                                                        rental={rental}
                                                        users={users}
                                                        cars={cars}
                                                        statuses={statuses}
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Editar aluguel #${rental.id}`}
                                                            >
                                                                <Pencil />
                                                            </Button>
                                                        }
                                                    />

                                                    <DeleteRentalDialog
                                                        rental={rental}
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

                {rentals.total > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-muted-foreground text-xs">
                            Mostrando {rentals.from ?? 0}–{rentals.to ?? 0} de{' '}
                            {rentals.total}
                        </p>

                        <div className="flex flex-wrap gap-1">
                            {rentals.links.map((link) =>
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

RentalsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Aluguéis',
            href: '/rentals',
        },
    ],
};
