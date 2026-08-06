<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import WalletNav from '@/components/WalletNav.vue';
import { useAuthToken } from '@/composables/useAuthToken';

const { get: getToken, authFetch } = useAuthToken();

const amount = ref('');
const currency = ref('MYR');
const bankId = ref('');
const holderName = ref('');
const accountNo = ref('');

const submitting = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

async function submit() {
    submitting.value = true;
    errorMessage.value = null;
    successMessage.value = null;

    const response = await authFetch('/api/withdrawals', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            amount: amount.value,
            currency: currency.value,
            bank_id: bankId.value,
            holder_name: holderName.value,
            account_no: accountNo.value,
        }),
    });

    const body = await response.json().catch(() => null);
    submitting.value = false;

    if (!response.ok) {
        errorMessage.value = body?.message ?? `Withdrawal failed (${response.status}). Backend endpoint may not exist yet.`;
        return;
    }

    successMessage.value = 'Withdrawal requested — funds are held and pending gateway confirmation.';
    amount.value = '';
    bankId.value = '';
    holderName.value = '';
    accountNo.value = '';
}

onMounted(() => {
    if (!getToken()) {
        router.visit('/api/login');
    }
});
</script>

<template>
    <Head title="Withdraw" />

    <div class="mx-auto min-h-screen max-w-2xl bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
        <WalletNav active="withdraw" />

        <Card>
            <CardHeader>
                <CardTitle>Withdraw</CardTitle>
                <CardDescription>POST /api/withdrawals &mdash; funds are held from your balance immediately.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label>Amount</Label>
                    <Input v-model="amount" type="number" step="0.01" min="0" placeholder="100.00" />
                </div>

                <div class="space-y-2">
                    <Label>Currency</Label>
                    <Input v-model="currency" maxlength="3" placeholder="MYR" />
                </div>

                <div class="space-y-2">
                    <Label>Bank ID</Label>
                    <Input v-model="bankId" placeholder="e.g. 12" />
                    <p class="text-xs text-muted-foreground">
                        Placeholder field &mdash; a real bank picker needs a bank-list endpoint, not yet built.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>Account holder name</Label>
                    <Input v-model="holderName" placeholder="John Doe" />
                </div>

                <div class="space-y-2">
                    <Label>Account number</Label>
                    <Input v-model="accountNo" placeholder="1234567890" />
                </div>

                <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
                <p v-if="successMessage" class="text-sm text-green-600">{{ successMessage }}</p>

                <Button class="w-full" :disabled="submitting" @click="submit">
                    {{ submitting ? 'Submitting…' : 'Withdraw' }}
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
