import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import CarController from '@/actions/App/Http/Controllers/CarController';
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
import type { ManagedCar } from '@/types/car';

export function DeleteCarDialog({ car }: { car: ManagedCar }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Excluir ${car.code}`}
                >
                    <Trash2 />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogTitle>
                    Excluir {car.brand} {car.model}?
                </DialogTitle>
                <DialogDescription>
                    Esta ação não pode ser desfeita. O veículo {car.code} (
                    {car.license_plate}) e a sua imagem serão removidos
                    permanentemente.
                </DialogDescription>

                <Form
                    {...CarController.destroy.form(car.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="outline">
                                    Cancelar
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Excluir
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
