import { Check, ShieldAlert, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { ApprovalAction, PendingApproval } from '@/types/chat';

type Props = {
    approvals: PendingApproval[];
    processing: boolean;
    resolved: ApprovalAction | null;
    onDecide: (action: ApprovalAction) => void;
};

/**
 * Turn a tool name such as "add_user" into "add user".
 */
function readableTool(tool: string): string {
    return tool.replaceAll('_', ' ');
}

function formatValue(value: unknown): string {
    return typeof value === 'string' ? value : JSON.stringify(value);
}

export function ApprovalRequest({
    approvals,
    processing,
    resolved,
    onDecide,
}: Props) {
    return (
        <div className="border-border bg-card rounded-2xl border p-4">
            <div className="flex items-start gap-3">
                <ShieldAlert className="text-muted-foreground mt-0.5 size-5 shrink-0" />

                <div className="min-w-0 flex-1 space-y-3">
                    <div className="space-y-1">
                        <p className="text-sm font-semibold">
                            Precisa da sua aprovação
                        </p>
                        <p className="text-muted-foreground text-sm">
                            O assistente quer executar{' '}
                            {approvals.length === 1
                                ? 'uma ação'
                                : `${approvals.length} ações`}{' '}
                            antes de continuar.
                        </p>
                    </div>

                    {approvals.map((approval) => (
                        <div
                            key={approval.id}
                            className="border-border/60 bg-muted/40 space-y-2 rounded-xl border p-3"
                        >
                            <p className="font-mono text-xs font-medium">
                                {readableTool(approval.tool)}
                            </p>

                            {approval.reason && (
                                <p className="text-muted-foreground text-xs">
                                    {approval.reason}
                                </p>
                            )}

                            {Object.keys(approval.arguments).length > 0 && (
                                <dl className="grid gap-1 text-xs">
                                    {Object.entries(approval.arguments).map(
                                        ([key, value]) => (
                                            <div
                                                key={key}
                                                className="flex gap-2"
                                            >
                                                <dt className="text-muted-foreground shrink-0 font-mono">
                                                    {key}
                                                </dt>
                                                <dd className="min-w-0 font-mono wrap-break-word">
                                                    {formatValue(value)}
                                                </dd>
                                            </div>
                                        ),
                                    )}
                                </dl>
                            )}
                        </div>
                    ))}

                    {resolved === null ? (
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                disabled={processing}
                                onClick={() => onDecide('approve')}
                            >
                                <Check />
                                Aprovar
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={processing}
                                onClick={() => onDecide('reject')}
                            >
                                <X />
                                Rejeitar
                            </Button>
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-xs font-medium">
                            {resolved === 'approve'
                                ? 'Aprovado por você.'
                                : 'Rejeitado por você.'}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
