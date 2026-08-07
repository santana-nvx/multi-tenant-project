<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import WalletNav from '@/components/WalletNav.vue';
import { useAuthToken } from '@/composables/useAuthToken';

const { get: getToken, authFetch } = useAuthToken();

type Bank = { id: string; name: string; currency: string };

const amount = ref('');
const currency = ref('MYR');
const payMethod = ref('online_banking');
const bankId = ref('');
const phoneNumber = ref('');

const banks = ref<Bank[]>([]);
const banksLoading = ref(true);
const banksError = ref<string | null>(null);

const submitting = ref(false);
const errorMessage = ref<string | null>(null);
const paymentUrl = ref<string | null>(null);

async function loadBanks() {
    banksLoading.value = true;
    banksError.value = null;

    const response = await authFetch(`/api/deposits/banks?currency=${currency.value}`);
    const body = await response.json().catch(() => null);

    if (!response.ok) {
        banksError.value = 'Could not load bank list.';
        banksLoading.value = false;
        return;
    }

    banks.value = body.data ?? [];
    banksLoading.value = false;
}

async function submit() {
    submitting.value = true;
    errorMessage.value = null;
    paymentUrl.value = null;

    const response = await authFetch('/api/deposits', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            amount: amount.value,
            currency: currency.value,
            pay_method: payMethod.value,
            bank_id: bankId.value,
            phone_number: phoneNumber.value,
        }),
    });

    const body = await response.json().catch(() => null);
    submitting.value = false;

    if (!response.ok) {
        errorMessage.value = body?.message ?? `Deposit failed (${response.status}). Backend endpoint may not exist yet.`;
        return;
    }

    paymentUrl.value = body.payment_url;
}

onMounted(() => {
    if (!getToken()) {
        router.visit('/api/login');
        return;
    }

    loadBanks();
});
</script>

<template>
    <Head title="Deposit" />

    <div class="mx-auto min-h-screen max-w-2xl bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
        <WalletNav active="deposit" />

        <Card>
            <CardHeader>
                <CardTitle>Deposit</CardTitle>
                <CardDescription>POST /api/deposits &mdash; returns a payment URL to redirect to.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <template v-if="!paymentUrl">
                    <div class="space-y-2">
                        <Label>Amount</Label>
                        <Input v-model="amount" type="number" step="0.01" min="0" placeholder="150.00" />
                    </div>

                    <div class="space-y-2">
                        <Label>Currency</Label>
                        <Select v-model="currency">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a currency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="MYR">MYR</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Payment method</Label>
                        <Select v-model="payMethod">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a payment method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="online_banking">Online Banking</SelectItem>
                                <SelectItem value="duitnowqr">DuitNow QR</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Bank</Label>
                        <Select v-model="bankId" :disabled="banksLoading || !!banksError">
                            <SelectTrigger class="w-full">
                                <SelectValue :placeholder="banksLoading ? 'Loading banks…' : 'Select a bank'" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="bank in banks" :key="bank.id" :value="bank.id">
                                    {{ bank.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="banksError" class="text-xs text-destructive">{{ banksError }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Phone number</Label>
                        <Input v-model="phoneNumber" placeholder="0123456789" />
                    </div>

                    <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>

                    <Button class="w-full" :disabled="submitting" @click="submit">
                        {{ submitting ? 'Submitting…' : 'Deposit' }}
                    </Button>
                </template>

                <template v-else>
                    <p class="text-sm text-muted-foreground">Deposit order created. Continue to payment:</p>
                    <Button as="a" :href="paymentUrl" class="w-full" target="_blank">
                        Go to payment page
                    </Button>
                    <p class="break-all text-xs text-muted-foreground">{{ paymentUrl }}</p>
                </template>
            </CardContent>
        </Card>
    </div>
</template>
