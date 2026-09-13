import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import RentalController from '@/actions/App/Http/Controllers/RentalController';
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
import type { ManagedRental } from '@/types/rental';

export function DeleteRentalDialog({ rental }: { rental: ManagedRental }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Excluir aluguel #${rental.id}`}
                >
                    <Trash2 />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogTitle>Excluir este aluguel?</DialogTitle>
                <DialogDescription>
                    Esta ação não pode ser desfeita. O aluguel de{' '}
                    {rental.car.brand} {rental.car.model} para{' '}
                    {rental.user.name} será removido permanentemente
                    {rental.status !== 'concluido' &&
                        rental.status !== 'cancelado' &&
                        ' e o veículo voltará a ficar disponível'}
                    .
                </DialogDescription>

                <Form
                    {...RentalController.destroy.form(rental.id)}
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
