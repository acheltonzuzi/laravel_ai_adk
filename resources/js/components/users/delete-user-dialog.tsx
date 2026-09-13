import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
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
import type { ManagedUser } from '@/types/user';

export function DeleteUserDialog({ user }: { user: ManagedUser }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Excluir ${user.name}`}
                >
                    <Trash2 />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogTitle>Excluir {user.name}?</DialogTitle>
                <DialogDescription>
                    Esta ação não pode ser desfeita. A conta de {user.email} e
                    todos os seus dados serão removidos permanentemente.
                </DialogDescription>

                <Form
                    {...UserController.destroy.form(user.id)}
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
