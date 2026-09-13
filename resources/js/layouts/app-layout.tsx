import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    fullHeight = false,
    children,
}: AppLayoutProps) {
    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs} fullHeight={fullHeight}>
            {children}
        </AppLayoutTemplate>
    );
}
