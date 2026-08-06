<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useAuthToken } from '@/composables/useAuthToken';

defineProps<{
    active: 'wallet' | 'deposit' | 'withdraw' | 'transactions';
}>();

const { clear: clearToken } = useAuthToken();

function logout() {
    clearToken();
    window.location.href = '/api/login';
}
</script>

<template>
    <nav class="mb-6 flex flex-wrap items-center gap-2 border-b pb-4">
        <Link href="/wallet">
            <Button :variant="active === 'wallet' ? 'default' : 'ghost'" size="sm">Balance</Button>
        </Link>
        <Link href="/wallet/deposit">
            <Button :variant="active === 'deposit' ? 'default' : 'ghost'" size="sm">Deposit</Button>
        </Link>
        <Link href="/wallet/withdraw">
            <Button :variant="active === 'withdraw' ? 'default' : 'ghost'" size="sm">Withdraw</Button>
        </Link>
        <Link href="/wallet/transactions">
            <Button :variant="active === 'transactions' ? 'default' : 'ghost'" size="sm">Transactions</Button>
        </Link>
        <Button variant="outline" size="sm" class="ml-auto" @click="logout">Log out</Button>
    </nav>
</template>
