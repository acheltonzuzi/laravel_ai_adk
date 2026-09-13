import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import type { ManagedUser } from '@/types/user';

type Props = {
    user?: ManagedUser;
    trigger: ReactNode;
};

export function UserFormDialog({ user, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = user !== undefined;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent>
                <DialogTitle>
                    {isEditing ? 'Editar usuário' : 'Novo usuário'}
                </DialogTitle>
                <DialogDescription>
                    {isEditing
                        ? 'Deixe a senha em branco para mantê-la como está.'
                        : 'Preencha os dados do novo usuário.'}
                </DialogDescription>

                <Form
                    {...(isEditing
                        ? UserController.update.form(user.id)
                        : UserController.store.form())}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={!isEditing}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={user?.name}
                                    required
                                    autoComplete="name"
                                    placeholder="Nome completo"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={user?.email}
                                    required
                                    autoComplete="username"
                                    placeholder="email@exemplo.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Senha</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required={!isEditing}
                                    autoComplete="new-password"
                                    placeholder={
                                        isEditing
                                            ? 'Deixe em branco para não alterar'
                                            : 'Mínimo de 8 caracteres'
                                    }
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmar senha
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required={!isEditing}
                                    autoComplete="new-password"
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {isEditing ? 'Salvar' : 'Criar usuário'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
