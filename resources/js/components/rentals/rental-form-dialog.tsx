import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import RentalController from '@/actions/App/Http/Controllers/RentalController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EnumOption } from '@/types/car';
import { formatCurrency } from '@/lib/currency';
import type {
    ManagedRental,
    RentalCarOption,
    RentalUserOption,
} from '@/types/rental';

type Props = {
    rental?: ManagedRental;
    users: RentalUserOption[];
    cars: RentalCarOption[];
    statuses: EnumOption[];
    trigger: ReactNode;
};

export function RentalFormDialog({
    rental,
    users,
    cars,
    statuses,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = rental !== undefined;

    const [selectedCarId, setSelectedCarId] = useState<string>(
        rental ? String(rental.car_id) : '',
    );
    const [dailyRate, setDailyRate] = useState<string>(
        rental ? String(rental.daily_rate) : '',
    );
    const [startDate, setStartDate] = useState(rental?.start_date ?? '');
    const [endDate, setEndDate] = useState(rental?.end_date ?? '');

    /** Display-only preview: the backend always recalculates the real total. */
    const totalPreview = useMemo(() => {
        const rate = Number(dailyRate);

        if (!startDate || !endDate || !Number.isFinite(rate) || rate <= 0) {
            return null;
        }

        const start = new Date(startDate);
        const end = new Date(endDate);
        const days =
            Math.round(
                (end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24),
            ) + 1;

        return days > 0 ? { days, total: days * rate } : null;
    }, [startDate, endDate, dailyRate]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>
                    {isEditing
                        ? `Editar aluguel #${rental.id}`
                        : 'Novo aluguel'}
                </DialogTitle>
                <DialogDescription>
                    {isEditing
                        ? 'O veículo do aluguel não pode ser alterado depois de criado.'
                        : 'Só veículos disponíveis podem ser alugados.'}
                </DialogDescription>

                <Form
                    {...(isEditing
                        ? RentalController.update.form(rental.id)
                        : RentalController.store.form())}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={!isEditing}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="user_id">Utilizador</Label>
                                <Select
                                    name="user_id"
                                    defaultValue={
                                        rental
                                            ? String(rental.user_id)
                                            : undefined
                                    }
                                >
                                    <SelectTrigger id="user_id">
                                        <SelectValue placeholder="Selecionar utilizador" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {users.map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={String(user.id)}
                                            >
                                                {user.name} · {user.email}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.user_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="car_id">Veículo</Label>

                                {isEditing ? (
                                    <p className="border-input bg-muted/40 text-muted-foreground rounded-md border px-3 py-2 text-sm">
                                        {rental.car.brand} {rental.car.model} ·{' '}
                                        {rental.car.code}
                                    </p>
                                ) : (
                                    <>
                                        <Select
                                            name="car_id"
                                            value={selectedCarId}
                                            onValueChange={(value) => {
                                                setSelectedCarId(value);

                                                const car = cars.find(
                                                    (car) =>
                                                        String(car.id) ===
                                                        value,
                                                );

                                                if (car?.price !== null) {
                                                    setDailyRate(
                                                        String(car?.price),
                                                    );
                                                }
                                            }}
                                        >
                                            <SelectTrigger id="car_id">
                                                <SelectValue placeholder="Selecionar veículo disponível" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {cars.map((car) => (
                                                    <SelectItem
                                                        key={car.id}
                                                        value={String(car.id)}
                                                    >
                                                        {car.brand} {car.model}{' '}
                                                        · {car.license_plate}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {cars.length === 0 && (
                                            <p className="text-muted-foreground text-xs">
                                                Nenhum veículo disponível no
                                                momento.
                                            </p>
                                        )}
                                    </>
                                )}
                                <InputError message={errors.car_id} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="start_date">
                                        Data de início
                                    </Label>
                                    <Input
                                        id="start_date"
                                        name="start_date"
                                        type="date"
                                        value={startDate}
                                        onChange={(event) =>
                                            setStartDate(event.target.value)
                                        }
                                        required
                                    />
                                    <InputError message={errors.start_date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="end_date">
                                        Data de fim
                                    </Label>
                                    <Input
                                        id="end_date"
                                        name="end_date"
                                        type="date"
                                        value={endDate}
                                        onChange={(event) =>
                                            setEndDate(event.target.value)
                                        }
                                        required
                                    />
                                    <InputError message={errors.end_date} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="daily_rate">Diária</Label>
                                <Input
                                    id="daily_rate"
                                    name="daily_rate"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={dailyRate}
                                    onChange={(event) =>
                                        setDailyRate(event.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.daily_rate} />
                            </div>

                            {totalPreview && (
                                <p className="text-muted-foreground text-xs">
                                    {totalPreview.days}{' '}
                                    {totalPreview.days === 1 ? 'dia' : 'dias'} ·
                                    Total estimado:{' '}
                                    <span className="font-medium">
                                        {formatCurrency(totalPreview.total)}
                                    </span>
                                </p>
                            )}

                            {isEditing && (
                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select
                                        name="status"
                                        defaultValue={rental.status}
                                    >
                                        <SelectTrigger id="status">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {statuses.map((status) => (
                                                <SelectItem
                                                    key={status.value}
                                                    value={status.value}
                                                >
                                                    {status.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                            )}

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {isEditing ? 'Salvar' : 'Alugar'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
