<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAuthToken } from '@/composables/useAuthToken';

const { set: setToken } = useAuthToken();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');

const submitting = ref(false);
const errorMessage = ref<string | null>(null);

async function register() {
    submitting.value = true;
    errorMessage.value = null;

    const response = await fetch('/api/register', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body: JSON.stringify({
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        }),
    });

    const body = await response.json().catch(() => null);
    submitting.value = false;

    if (!response.ok) {
        errorMessage.value =
            body?.errors?.email?.[0] ??
            body?.errors?.password?.[0] ??
            body?.message ??
            'Registration failed.';

        return;
    }

    setToken(body.access_token);
    router.visit('/wallet');
}
</script>

<template>
    <Head title="Register" />

    <div
        class="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]"
    >
        <Card class="w-full max-w-sm">
            <CardHeader>
                <CardTitle>Create Account</CardTitle>
                <CardDescription
                    >Posts to <code>/api/register</code> &mdash; logs you in
                    immediately.</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Input
                        v-model="name"
                        placeholder="Name"
                        autocomplete="name"
                    />
                    <Input
                        v-model="email"
                        type="email"
                        placeholder="Email"
                        autocomplete="username"
                    />
                    <Input
                        v-model="password"
                        type="password"
                        placeholder="Password"
                        autocomplete="new-password"
                    />
                    <Input
                        v-model="passwordConfirmation"
                        type="password"
                        placeholder="Confirm password"
                        autocomplete="new-password"
                        @keyup.enter="register"
                    />
                </div>

                <p v-if="errorMessage" class="text-sm text-destructive">
                    {{ errorMessage }}
                </p>

                <Button class="w-full" :disabled="submitting" @click="register">
                    {{ submitting ? 'Creating account…' : 'Create account' }}
                </Button>

                <p class="text-center text-sm text-muted-foreground">
                    Already have an account?
                    <Link href="/api/login" class="underline">Log in</Link>
                </p>
            </CardContent>
        </Card>
    </div>
</template>
