<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useAuthToken } from '@/composables/useAuthToken';

type MerchantBalance = {
    status: boolean;
    balance: string | null;
    currency: string | null;
    message: string | null;
} | null;

type Stats = {
    total_deposits: string;
    total_withdrawals: string;
    pending_count: number;
};

const { get: getToken, authFetch, clear: clearToken } = useAuthToken(
    'admin_token',
    '/admin/login',
);

const merchantBalance = ref<MerchantBalance>(null);
const stats = ref<Stats | null>(null);
const loading = ref(true);
const errorMessage = ref<string | null>(null);

async function load() {
    loading.value = true;
    errorMessage.value = null;

    const response = await authFetch('/admin/dashboard/stats');

    if (!response.ok) {
        errorMessage.value = `Could not load dashboard (${response.status}). Backend endpoint may not exist yet.`;
        loading.value = false;

        return;
    }

    const body = await response.json();
    merchantBalance.value = body.merchant_balance ?? null;
    stats.value = body.stats ?? null;
    loading.value = false;
}

function logout() {
    clearToken();
    router.visit('/admin/login');
}

onMounted(() => {
    if (!getToken()) {
        router.visit('/admin/login');

        return;
    }

    load();
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <div
        class="mx-auto min-h-screen max-w-2xl bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]"
    >
        <nav class="mb-6 flex items-center justify-between border-b pb-4">
            <h1 class="text-lg font-semibold">Admin Dashboard</h1>
            <Button variant="outline" size="sm" @click="logout"
                >Log out</Button
            >
        </nav>

        <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>
        <p v-else-if="errorMessage" class="text-sm text-destructive">
            {{ errorMessage }}
        </p>

        <template v-else>
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Merchant Float Balance</CardTitle>
                    <CardDescription
                        >Live balance held at the payment gateway.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!merchantBalance || !merchantBalance.status"
                        class="text-sm text-destructive"
                    >
                        {{
                            merchantBalance?.message ??
                            'Could not fetch balance from the gateway.'
                        }}
                    </p>
                    <p v-else class="text-3xl font-semibold">
                        {{ merchantBalance.currency }}
                        {{ merchantBalance.balance }}
                    </p>
                </CardContent>
            </Card>

            <div class="grid grid-cols-3 gap-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">Total Deposits</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-semibold">
                            {{ stats?.total_deposits ?? '0.00' }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">Total Withdrawals</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-semibold">
                            {{ stats?.total_withdrawals ?? '0.00' }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">Pending</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-semibold">
                            {{ stats?.pending_count ?? 0 }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </template>
    </div>
</template>
