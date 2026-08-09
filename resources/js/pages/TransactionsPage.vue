<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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

const transactions = ref<Transaction[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const errorMessage = ref<string | null>(null);

const filterType = ref('all');
const filterStatus = ref('all');
const filterDateFrom = ref('');
const filterDateTo = ref('');

function statusVariant(status: Transaction['status']) {
    if (status === 'success') {
        return 'default';
    }

    if (status === 'failed' || status === 'cancelled') {
        return 'destructive';
    }

    return 'secondary';
}

async function load(page: number) {
    loading.value = true;
    errorMessage.value = null;

    const params = new URLSearchParams({ page: String(page) });

    if (filterType.value !== 'all') {
        params.set('type', filterType.value);
    }

    if (filterStatus.value !== 'all') {
        params.set('status', filterStatus.value);
    }

    if (filterDateFrom.value) {
        params.set('date_from', filterDateFrom.value);
    }

    if (filterDateTo.value) {
        params.set('date_to', filterDateTo.value);
    }

    const response = await authFetch(`/api/transactions?${params.toString()}`);

    if (!response.ok) {
        errorMessage.value = `Could not load transactions (${response.status}). Backend endpoint may not exist yet.`;
        loading.value = false;

        return;
    }

    const body = await response.json();
    transactions.value = body.data ?? [];
    currentPage.value = body.current_page ?? 1;
    lastPage.value = body.last_page ?? 1;
    loading.value = false;
}

function applyFilters() {
    load(1);
}

function clearFilters() {
    filterType.value = 'all';
    filterStatus.value = 'all';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    load(1);
}

onMounted(() => {
    if (!getToken()) {
        router.visit('/api/login');

        return;
    }

    load(1);
});
</script>

<template>
    <Head title="Transactions" />

    <div
        class="mx-auto min-h-screen max-w-3xl bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]"
    >
        <WalletNav active="transactions" />

        <Card>
            <CardHeader>
                <CardTitle>Transactions</CardTitle>
                <CardDescription
                    >GET /api/transactions &mdash; paginated.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="space-y-1">
                        <Label class="text-xs">Type</Label>
                        <Select v-model="filterType">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="deposit">Deposit</SelectItem>
                                <SelectItem value="withdrawal">Withdrawal</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-xs">Status</Label>
                        <Select v-model="filterStatus">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="processing">Processing</SelectItem>
                                <SelectItem value="success">Success</SelectItem>
                                <SelectItem value="failed">Failed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-xs">From</Label>
                        <Input
                            v-model="filterDateFrom"
                            type="date"
                            @click="($event.target as HTMLInputElement).showPicker?.()"
                        />
                    </div>

                    <div class="space-y-1">
                        <Label class="text-xs">To</Label>
                        <Input
                            v-model="filterDateTo"
                            type="date"
                            @click="($event.target as HTMLInputElement).showPicker?.()"
                        />
                    </div>
                </div>

                <div class="mb-4 flex gap-2">
                    <Button size="sm" @click="applyFilters">Apply filters</Button>
                    <Button size="sm" variant="outline" @click="clearFilters">Clear</Button>
                </div>

                <p v-if="loading" class="text-sm text-muted-foreground">
                    Loading…
                </p>
                <p v-else-if="errorMessage" class="text-sm text-destructive">
                    {{ errorMessage }}
                </p>
                <p
                    v-else-if="transactions.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No transactions yet.
                </p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-muted-foreground"
                            >
                                <th class="py-2 pr-4 font-medium">Order ID</th>
                                <th class="py-2 pr-4 font-medium">Type</th>
                                <th class="py-2 pr-4 font-medium">Amount</th>
                                <th class="py-2 pr-4 font-medium">Status</th>
                                <th class="py-2 pr-4 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="tx in transactions"
                                :key="tx.id"
                                class="border-b last:border-0"
                            >
                                <td class="py-2 pr-4 font-mono text-xs">
                                    {{ tx.order_id }}
                                </td>
                                <td class="py-2 pr-4 capitalize">
                                    {{ tx.type }}
                                </td>
                                <td class="py-2 pr-4">
                                    {{ tx.currency }} {{ tx.amount }}
                                </td>
                                <td class="py-2 pr-4">
                                    <Badge
                                        :variant="statusVariant(tx.status)"
                                        >{{ tx.status }}</Badge
                                    >
                                </td>
                                <td class="py-2 pr-4 text-muted-foreground">
                                    {{ tx.created_at }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="!loading && !errorMessage && lastPage > 1"
                    class="mt-4 flex items-center justify-between"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage <= 1"
                        @click="currentPage > 1 && load(currentPage - 1)"
                    >
                        Previous
                    </Button>
                    <span class="text-sm text-muted-foreground"
                        >Page {{ currentPage }} of {{ lastPage }}</span
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage >= lastPage"
                        @click="currentPage < lastPage && load(currentPage + 1)"
                    >
                        Next
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
