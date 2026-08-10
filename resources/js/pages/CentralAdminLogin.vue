<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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

const { set: setToken } = useAuthToken('central_admin_token', '/central-admin/login');

const email = ref('');
const password = ref('');
const loginError = ref<string | null>(null);
const loginLoading = ref(false);

async function login() {
    loginLoading.value = true;
    loginError.value = null;

    const response = await fetch('/central-admin/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body: JSON.stringify({ email: email.value, password: password.value }),
    });

    const body = await response.json();
    loginLoading.value = false;

    if (!response.ok) {
        loginError.value = body.message ?? 'Login failed.';

        return;
    }

    setToken(body.access_token);
    router.visit('/central-admin/dashboard');
}
</script>

<template>
    <Head title="Central Admin Login" />

    <div
        class="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]"
    >
        <Card class="w-full max-w-sm">
            <CardHeader>
                <CardTitle>Central Admin Login</CardTitle>
                <CardDescription
                    >Posts to <code>/central-admin/login</code> &mdash; uses
                    the <code>central-admin</code> guard. This login is not
                    tied to any tenant.</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
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
                        autocomplete="current-password"
                        @keyup.enter="login"
                    />
                </div>

                <p v-if="loginError" class="text-sm text-destructive">
                    {{ loginError }}
                </p>

                <Button class="w-full" :disabled="loginLoading" @click="login">
                    {{ loginLoading ? 'Logging in…' : 'Log in' }}
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
