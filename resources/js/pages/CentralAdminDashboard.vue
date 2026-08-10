<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { useAuthToken } from '@/composables/useAuthToken';

type CentralAdmin = {
    name: string;
    email: string;
    roles: string[];
};

type RecentActivity = {
    tenant_domain: string | null;
    type: 'deposit' | 'withdrawal';
    status: string;
    amount: string;
    currency: string;
    created_at: string;
};

type PlatformStats = {
    tenant_count: number;
    total_deposits: string;
    total_withdrawals: string;
    recent_activity: RecentActivity[];
};

const { get: getToken, authFetch, clear: clearToken } = useAuthToken(
    'central_admin_token',
    '/central-admin/login',
);

const admin = ref<CentralAdmin | null>(null);
const stats = ref<PlatformStats | null>(null);
const loading = ref(true);
const errorMessage = ref<string | null>(null);

async function load() {
    loading.value = true;
    errorMessage.value = null;

    const meResponse = await authFetch('/central-admin/me');

    if (!meResponse.ok) {
        errorMessage.value = `Could not load profile (${meResponse.status}).`;
        loading.value = false;

        return;
    }

    admin.value = await meResponse.json();

    const statsResponse = await authFetch('/central-admin/dashboard/stats');

    if (statsResponse.ok) {
        stats.value = await statsResponse.json();
    }

    loading.value = false;
}

function logout() {
    clearToken();
    router.visit('/central-admin/login');
}

onMounted(() => {
    if (!getToken()) {
        router.visit('/central-admin/login');

        return;
    }

    load();
});
</script>

<template>
    <Head title="Central Admin Dashboard" />

    <div
        class="mx-auto min-h-screen max-w-2xl bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]"
    >
        <nav class="mb-6 flex items-center justify-between border-b pb-4">
            <h1 class="text-lg font-semibold">Central Admin</h1>
            <div class="flex gap-2">
                <Link href="/central-admin/tenant-management">
                    <Button variant="secondary" size="sm"
                        >Manage Tenants</Button
                    >
                </Link>
                <Button variant="outline" size="sm" @click="logout"
                    >Log out</Button
                >
            </div>
        </nav>

        <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>
        <p v-else-if="errorMessage" class="text-sm text-destructive">
            {{ errorMessage }}
        </p>

        <template v-else>
            <Card v-if="admin" class="mb-6">
                <CardHeader>
                    <CardTitle>{{ admin.name }}</CardTitle>
                    <CardDescription>{{ admin.email }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="mb-2 text-sm text-muted-foreground">
                        This account exists only on the central database
                        &mdash; it is not scoped to any tenant.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="role in admin.roles" :key="role">{{
                            role
                        }}</Badge>
                    </div>
                </CardContent>
            </Card>

            <div v-if="stats" class="mb-6 grid grid-cols-3 gap-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">Tenants</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-semibold">
                            {{ stats.tenant_count }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm"
                            >Total Deposits (all tenants)</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-semibold">
                            {{ stats.total_deposits }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm"
                            >Total Withdrawals (all tenants)</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-semibold">
                            {{ stats.total_withdrawals }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="stats">
                <CardHeader>
                    <CardTitle>Recent Activity</CardTitle>
                    <CardDescription
                        >Latest transactions across all tenants.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <p
                        v-if="stats.recent_activity.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No activity yet.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="(activity, index) in stats.recent_activity"
                            :key="index"
                            class="flex items-center justify-between py-2 text-sm"
                        >
                            <div>
                                <p class="font-medium capitalize">
                                    {{ activity.type }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ activity.tenant_domain ?? 'unknown tenant' }}
                                    &mdash; {{ activity.created_at }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    >{{ activity.currency }}
                                    {{ activity.amount }}</span
                                >
                                <Badge variant="secondary">{{
                                    activity.status
                                }}</Badge>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
