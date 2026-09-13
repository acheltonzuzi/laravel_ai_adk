import { Form } from '@inertiajs/react';
import { ImagePlus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import CarController from '@/actions/App/Http/Controllers/CarController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { EnumOption, ManagedCar } from '@/types/car';

type Props = {
    car?: ManagedCar;
    conditions: EnumOption[];
    availabilities: EnumOption[];
    trigger: ReactNode;
};

export function CarFormDialog({
    car,
    conditions,
    availabilities,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const [preview, setPreview] = useState<string | null>(null);
    const isEditing = car !== undefined;

    useEffect(() => {
        if (preview === null) {
            return;
        }

        return () => URL.revokeObjectURL(preview);
    }, [preview]);

    const currentImage = preview ?? car?.image_url ?? null;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setPreview(null);
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>
                    {isEditing ? `Editar ${car.code}` : 'Novo veículo'}
                </DialogTitle>
                <DialogDescription>
                    {isEditing
                        ? 'O código é gerado pelo sistema e não muda.'
                        : 'O código é gerado automaticamente ao guardar.'}
                </DialogDescription>

                <Form
                    {...(isEditing
                        ? CarController.update.form(car.id)
                        : CarController.store.form())}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={!isEditing}
                    onSuccess={() => {
                        setOpen(false);
                        setPreview(null);
                    }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="license_plate">Matrícula</Label>
                                <Input
                                    id="license_plate"
                                    name="license_plate"
                                    defaultValue={car?.license_plate}
                                    required
                                    placeholder="LD-45-67-AB"
                                />
                                <InputError message={errors.license_plate} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="brand">Marca</Label>
                                    <Input
                                        id="brand"
                                        name="brand"
                                        defaultValue={car?.brand}
                                        required
                                        placeholder="Toyota"
                                    />
                                    <InputError message={errors.brand} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="model">Modelo</Label>
                                    <Input
                                        id="model"
                                        name="model"
                                        defaultValue={car?.model}
                                        required
                                        placeholder="Corolla"
                                    />
                                    <InputError message={errors.model} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="year">Ano</Label>
                                    <Input
                                        id="year"
                                        name="year"
                                        type="number"
                                        min={1900}
                                        max={new Date().getFullYear() + 1}
                                        defaultValue={car?.year}
                                        required
                                    />
                                    <InputError message={errors.year} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="price">Preço</Label>
                                    <Input
                                        id="price"
                                        name="price"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        defaultValue={car?.price ?? ''}
                                        placeholder="Opcional"
                                    />
                                    <InputError message={errors.price} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="location">Localização</Label>
                                <Input
                                    id="location"
                                    name="location"
                                    defaultValue={car?.location ?? ''}
                                    placeholder="Pátio Central"
                                />
                                <InputError message={errors.location} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="condition">Estado</Label>
                                    <Select
                                        name="condition"
                                        defaultValue={
                                            car?.condition ??
                                            conditions[0]?.value
                                        }
                                    >
                                        <SelectTrigger id="condition">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
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
                                    <InputError message={errors.condition} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="availability">
                                        Disponibilidade
                                    </Label>
                                    <Select
                                        name="availability"
                                        defaultValue={
                                            car?.availability ??
                                            availabilities[0]?.value
                                        }
                                    >
                                        <SelectTrigger id="availability">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
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
                                    <InputError message={errors.availability} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="image">Imagem</Label>

                                <div className="flex items-center gap-3">
                                    {currentImage === null ? (
                                        <div className="bg-muted text-muted-foreground flex size-16 shrink-0 items-center justify-center rounded-md">
                                            <ImagePlus className="size-5" />
                                        </div>
                                    ) : (
                                        <img
                                            src={currentImage}
                                            alt=""
                                            className="size-16 shrink-0 rounded-md object-cover"
                                        />
                                    )}

                                    <Input
                                        id="image"
                                        name="image"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={(event) => {
                                            const file =
                                                event.target.files?.[0];

                                            setPreview(
                                                file
                                                    ? URL.createObjectURL(file)
                                                    : null,
                                            );
                                        }}
                                    />
                                </div>

                                <InputError message={errors.image} />

                                {isEditing && car.image_url !== null && (
                                    <Label className="text-muted-foreground mt-1 flex items-center gap-2 text-xs font-normal">
                                        <Checkbox
                                            name="remove_image"
                                            value="1"
                                        />
                                        Remover a imagem atual
                                    </Label>
                                )}
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {isEditing ? 'Salvar' : 'Cadastrar'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
