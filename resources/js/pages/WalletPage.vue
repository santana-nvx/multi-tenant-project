<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import WalletNav from '@/components/WalletNav.vue';
import { useAuthToken } from '@/composables/useAuthToken';

type Transaction = {
    id: string;
    order_id: string;
    type: 'deposit' | 'withdrawal';
    status: 'pending' | 'processing' | 'success' | 'failed' | 'cancelled';
    amount: string;
    currency: string;
    created_at: string;
};

const { get: getToken, authFetch } = useAuthToken();

const balance = ref<string | null>(null);
const currency = ref('MYR');
const balanceLoading = ref(true);
const balanceError = ref<string | null>(null);

const recent = ref<Transaction[]>([]);
const recentLoading = ref(true);
const recentError = ref<string | null>(null);

function statusVariant(status: Transaction['status']) {
    if (status === 'success') {
        return 'default';
    }

    if (status === 'failed' || status === 'cancelled') {
        return 'destructive';
    }

    return 'secondary';
}

async function loadBalance() {
    balanceLoading.value = true;
    balanceError.value = null;

    const response = await authFetch('/api/wallet');

    if (!response.ok) {
        balanceError.value = `Could not load balance (${response.status}). Backend endpoint may not exist yet.`;
        balanceLoading.value = false;

        return;
    }

    const body = await response.json();
    balance.value = body.balance;
    currency.value = body.currency ?? 'MYR';
    balanceLoading.value = false;
}

async function loadRecent() {
    recentLoading.value = true;
    recentError.value = null;

    const response = await authFetch('/api/transactions?per_page=5');

    if (!response.ok) {
        recentError.value = `Could not load transactions (${response.status}). Backend endpoint may not exist yet.`;
        recentLoading.value = false;

        return;
    }

    const body = await response.json();
    recent.value = body.data ?? [];
    recentLoading.value = false;
}

onMounted(() => {
    if (!getToken()) {
        router.visit('/api/login');

        return;
    }

    loadBalance();
    loadRecent();
});
</script>

<template>
    <Head title="Wallet" />

    <div
        class="mx-auto min-h-screen max-w-2xl bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]"
    >
        <WalletNav active="wallet" />

        <Card class="mb-6">
            <CardHeader>
                <CardTitle>Current Balance</CardTitle>
                <CardDescription>GET /api/wallet</CardDescription>
            </CardHeader>
            <CardContent>
                <p v-if="balanceLoading" class="text-sm text-muted-foreground">
                    Loading…
                </p>
                <p v-else-if="balanceError" class="text-sm text-destructive">
                    {{ balanceError }}
                </p>
                <p v-else class="text-3xl font-semibold">
                    {{ currency }} {{ balance }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Recent Transactions</CardTitle>
                <CardDescription>GET /api/transactions</CardDescription>
            </CardHeader>
            <CardContent>
                <p v-if="recentLoading" class="text-sm text-muted-foreground">
                    Loading…
                </p>
                <p v-else-if="recentError" class="text-sm text-destructive">
                    {{ recentError }}
                </p>
                <p
                    v-else-if="recent.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No transactions yet.
                </p>
                <ul v-else class="divide-y">
                    <li
                        v-for="tx in recent"
                        :key="tx.id"
                        class="flex items-center justify-between py-2 text-sm"
                    >
                        <div>
                            <p class="font-medium capitalize">
                                {{ tx.type }} &mdash; {{ tx.order_id }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ tx.created_at }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span>{{ tx.currency }} {{ tx.amount }}</span>
                            <Badge :variant="statusVariant(tx.status)">{{
                                tx.status
                            }}</Badge>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
