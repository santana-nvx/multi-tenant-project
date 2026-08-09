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

const { set: setToken } = useAuthToken('admin_token', '/admin/login');

const email = ref('');
const password = ref('');
const token = ref<string | null>(null);
const loginError = ref<string | null>(null);
const loginLoading = ref(false);

const meResult = ref<{ guard: string; status: number; body: unknown } | null>(
    null,
);
const meLoading = ref(false);

async function login() {
    loginLoading.value = true;
    loginError.value = null;
    token.value = null;
    meResult.value = null;

    const response = await fetch('/admin/login', {
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

    token.value = body.access_token;
    setToken(body.access_token);
}

function goToDashboard() {
    router.visit('/admin/dashboard');
}

async function callMe(guard: 'api' | 'admin') {
    if (!token.value) {
        return;
    }

    meLoading.value = true;
    meResult.value = null;

    const response = await fetch(`/${guard}/me`, {
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token.value}`,
        },
    });

    const body = await response.json().catch(() => null);
    meLoading.value = false;
    meResult.value = { guard, status: response.status, body };
}

function logout() {
    token.value = null;
    meResult.value = null;
    email.value = '';
    password.value = '';
}
</script>

<template>
    <Head title="Admin Login" />

    <div
        class="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]"
    >
        <Card class="w-full max-w-sm">
            <CardHeader>
                <CardTitle>Admin Login</CardTitle>
                <CardDescription
                    >Posts to <code>/admin/login</code> &mdash; uses the
                    <code>admin</code> guard.</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <template v-if="!token">
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

                    <Button
                        class="w-full"
                        :disabled="loginLoading"
                        @click="login"
                    >
                        {{ loginLoading ? 'Logging in…' : 'Log in' }}
                    </Button>
                </template>

                <template v-else>
                    <p class="text-sm break-all text-muted-foreground">
                        Token:
                        <span class="font-mono">{{ token.slice(0, 24) }}…</span>
                    </p>

                    <div class="flex gap-2">
                        <Button
                            class="flex-1"
                            variant="secondary"
                            :disabled="meLoading"
                            @click="callMe('api')"
                        >
                            Call /api/me
                        </Button>
                        <Button
                            class="flex-1"
                            variant="secondary"
                            :disabled="meLoading"
                            @click="callMe('admin')"
                        >
                            Call /admin/me
                        </Button>
                    </div>

                    <Button class="w-full" @click="goToDashboard"
                        >Go to dashboard</Button
                    >

                    <div v-if="meResult" class="rounded-md border p-3 text-sm">
                        <p class="font-medium">
                            /{{ meResult.guard }}/me &mdash;
                            <span
                                :class="
                                    meResult.status === 200
                                        ? 'text-green-600'
                                        : 'text-destructive'
                                "
                            >
                                {{ meResult.status }}
                            </span>
                        </p>
                        <pre class="mt-2 overflow-x-auto text-xs">{{
                            JSON.stringify(meResult.body, null, 2)
                        }}</pre>
                    </div>

                    <Button class="w-full" variant="outline" @click="logout"
                        >Log out</Button
                    >
                </template>
            </CardContent>
        </Card>
    </div>
</template>
