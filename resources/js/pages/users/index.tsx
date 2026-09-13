import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Search, UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DeleteUserDialog } from '@/components/users/delete-user-dialog';
import { UserFormDialog } from '@/components/users/user-form-dialog';
import { index as usersIndex } from '@/routes/users';
import type { Auth } from '@/types';
import type { ManagedUser, Paginated } from '@/types/user';

type Props = {
    users: Paginated<ManagedUser>;
    filters: { search: string };
};

const dateFormatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' });

function formatDate(value: string | null): string {
    return value === null ? '—' : dateFormatter.format(new Date(value));
}

export default function UsersIndex({ users, filters }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [search, setSearch] = useState(filters.search);

    useEffect(() => {
        if (search === filters.search) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                usersIndex().url,
                { search },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, filters.search]);

    return (
        <>
            <Head title="Usuários" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Usuários"
                        description="Crie, edite e remova as contas do sistema"
                    />

                    <UserFormDialog
                        trigger={
                            <Button>
                                <UserPlus />
                                Novo usuário
                            </Button>
                        }
                    />
                </div>

                <div className="relative max-w-sm">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar por nome ou e-mail"
                        className="pl-9"
                        aria-label="Buscar usuários"
                    />
                </div>

                <div className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-muted-foreground border-border/70 border-b text-xs">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Usuário
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Verificado
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Criado em
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Ações
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="text-muted-foreground px-4 py-10 text-center"
                                        >
                                            Nenhum usuário encontrado.
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="border-border/40 border-b last:border-0"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-8">
                                                        <AvatarFallback className="text-xs">
                                                            {user.name
                                                                .charAt(0)
                                                                .toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {user.name}
                                                            {auth.user.id ===
                                                                user.id && (
                                                                <span className="text-muted-foreground ml-2 text-xs font-normal">
                                                                    (você)
                                                                </span>
                                                            )}
                                                        </p>
                                                        <p className="text-muted-foreground truncate text-xs">
                                                            {user.email}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-4 py-3">
                                                {user.email_verified_at ? (
                                                    <Badge variant="secondary">
                                                        Verificado
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Pendente
                                                    </Badge>
                                                )}
                                            </td>

                                            <td className="text-muted-foreground px-4 py-3">
                                                {formatDate(user.created_at)}
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <UserFormDialog
                                                        user={user}
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Editar ${user.name}`}
                                                            >
                                                                <Pencil />
                                                            </Button>
                                                        }
                                                    />

                                                    {auth.user.id !==
                                                        user.id && (
                                                        <DeleteUserDialog
                                                            user={user}
                                                        />
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {users.total > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-muted-foreground text-xs">
                            Mostrando {users.from ?? 0}–{users.to ?? 0} de{' '}
                            {users.total}
                        </p>

                        <div className="flex flex-wrap gap-1">
                            {users.links.map((link) =>
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

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Usuários',
            href: '/users',
        },
    ],
};
