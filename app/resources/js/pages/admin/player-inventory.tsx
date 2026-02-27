import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

// Stub page - will be fully implemented in Phase 3
export default function PlayerInventory({ username }: { username: string }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Players', href: '/admin/players' },
        { title: `${username} Inventory`, href: `/admin/players/${username}/inventory` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${username} - Inventory`} />
            <div className="flex flex-1 flex-col gap-6 p-4 lg:p-6">
                <h1 className="text-2xl font-bold tracking-tight">Inventory: {username}</h1>
                <p className="text-muted-foreground">Loading...</p>
            </div>
        </AppLayout>
    );
}
