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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAuthToken } from '@/composables/useAuthToken';

type Tenant = {
    id: string;
    name: string | null;
    is_active: boolean | null;
    domain: string | null;
    total_deposits: string;
    total_withdrawals: string;
    created_at: string;
};

const { get: getToken, authFetch, clear: clearToken } = useAuthToken(
    'central_admin_token',
    '/central-admin/login',
);

const tenants = ref<Tenant[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const errorMessage = ref<string | null>(null);
const search = ref('');
const statusFilter = ref('all');

// Create-tenant form state
const showCreate = ref(false);
const createLoading = ref(false);
const createError = ref<string | null>(null);
const form = ref({
    domain: '',
    name: '',
    user_name: 'Player',
    user_email: '',
    user_password: '',
    admin_name: 'Admin',
    admin_email: '',
    admin_password: '',
});

// Edit-name form state
const showEdit = ref(false);
const editTenant = ref<Tenant | null>(null);
const editName = ref('');
const editLoading = ref(false);
const editError = ref<string | null>(null);

async function load(page: number) {
    loading.value = true;
    errorMessage.value = null;

    const params = new URLSearchParams({ page: String(page) });
    if (search.value) {
        params.set('search', search.value);
    }

    if (statusFilter.value !== 'all') {
        params.set('status', statusFilter.value);
    }

    const response = await authFetch(`/central-admin/tenants?${params.toString()}`);

    if (!response.ok) {
        errorMessage.value = `Could not load tenants (${response.status}).`;
        loading.value = false;

        return;
    }

    const body = await response.json();
    tenants.value = body.data ?? [];
    currentPage.value = body.current_page ?? 1;
    lastPage.value = body.last_page ?? 1;
    loading.value = false;
}

function runSearch() {
    load(1);
}

function openCreate() {
    createError.value = null;
    form.value = {
        domain: '',
        name: '',
        user_name: 'Player',
        user_email: '',
        user_password: '',
        admin_name: 'Admin',
        admin_email: '',
        admin_password: '',
    };
    showCreate.value = true;
}

async function submitCreate() {
    createLoading.value = true;
    createError.value = null;

    const response = await authFetch('/central-admin/tenants', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form.value),
    });

    createLoading.value = false;

    if (!response.ok) {
        const body = await response.json().catch(() => null);
        createError.value = body?.message ?? `Could not create tenant (${response.status}).`;

        return;
    }

    showCreate.value = false;
    load(currentPage.value);
}

function openEdit(tenant: Tenant) {
    editTenant.value = tenant;
    editName.value = tenant.name ?? '';
    editError.value = null;
    showEdit.value = true;
}

async function submitEdit() {
    if (!editTenant.value) {
        return;
    }

    editLoading.value = true;
    editError.value = null;

    const response = await authFetch(`/central-admin/tenants/${editTenant.value.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: editName.value }),
    });

    editLoading.value = false;

    if (!response.ok) {
        const body = await response.json().catch(() => null);
        editError.value = body?.message ?? `Could not update tenant (${response.status}).`;

        return;
    }

    showEdit.value = false;
    load(currentPage.value);
}

async function toggleActive(tenant: Tenant) {
    await authFetch(`/central-admin/tenants/${tenant.id}/deactivate`, {
        method: 'PATCH',
    });

    load(currentPage.value);
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

    load(1);
});
</script>

<template>
    <Head title="Tenant Management" />

    <div
        class="mx-auto min-h-screen max-w-4xl bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]"
    >
        <nav class="mb-6 flex items-center justify-between border-b pb-4">
            <h1 class="text-lg font-semibold">Tenant Management</h1>
            <div class="flex gap-2">
                <Link href="/central-admin/dashboard">
                    <Button variant="secondary" size="sm">Dashboard</Button>
                </Link>
                <Button variant="outline" size="sm" @click="logout"
                    >Log out</Button
                >
            </div>
        </nav>

        <Card>
            <CardHeader>
                <CardTitle>Tenants</CardTitle>
                <CardDescription
                    >GET /central-admin/tenants &mdash; search, paginated.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <div class="mb-4 flex gap-2">
                    <Input
                        v-model="search"
                        placeholder="Search by domain…"
                        @keyup.enter="runSearch"
                    />
                    <Select v-model="statusFilter" @update:model-value="runSearch">
                        <SelectTrigger class="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button size="sm" @click="runSearch">Search</Button>
                    <Button size="sm" class="ml-auto" @click="openCreate"
                        >Create Tenant</Button
                    >
                </div>

                <p v-if="loading" class="text-sm text-muted-foreground">
                    Loading…
                </p>
                <p v-else-if="errorMessage" class="text-sm text-destructive">
                    {{ errorMessage }}
                </p>
                <p
                    v-else-if="tenants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No tenants found.
                </p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-muted-foreground"
                            >
                                <th class="py-2 pr-4 font-medium">Domain</th>
                                <th class="py-2 pr-4 font-medium">Name</th>
                                <th class="py-2 pr-4 font-medium">Status</th>
                                <th class="py-2 pr-4 font-medium">Deposits</th>
                                <th class="py-2 pr-4 font-medium"
                                    >Withdrawals</th
                                >
                                <th class="py-2 pr-4 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="tenant in tenants"
                                :key="tenant.id"
                                class="border-b last:border-0"
                            >
                                <td class="py-2 pr-4 font-mono text-xs">
                                    {{ tenant.domain ?? '—' }}
                                </td>
                                <td class="py-2 pr-4">
                                    {{ tenant.name ?? '—' }}
                                </td>
                                <td class="py-2 pr-4">
                                    <Badge
                                        :variant="
                                            tenant.is_active === false
                                                ? 'destructive'
                                                : 'default'
                                        "
                                    >
                                        {{
                                            tenant.is_active === false
                                                ? 'inactive'
                                                : 'active'
                                        }}
                                    </Badge>
                                </td>
                                <td class="py-2 pr-4">
                                    {{ tenant.total_deposits }}
                                </td>
                                <td class="py-2 pr-4">
                                    {{ tenant.total_withdrawals }}
                                </td>
                                <td class="py-2 pr-4">
                                    <div class="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="openEdit(tenant)"
                                            >Edit</Button
                                        >
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="toggleActive(tenant)"
                                        >
                                            {{
                                                tenant.is_active === false
                                                    ? 'Activate'
                                                    : 'Deactivate'
                                            }}
                                        </Button>
                                    </div>
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

        <Dialog v-model:open="showCreate">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Create Tenant</DialogTitle>
                </DialogHeader>

                <div class="space-y-3">
                    <div class="space-y-1">
                        <Label>Domain</Label>
                        <Input
                            v-model="form.domain"
                            placeholder="acme.multi-tenant-project.test"
                        />
                    </div>
                    <div class="space-y-1">
                        <Label>Tenant Name</Label>
                        <Input v-model="form.name" placeholder="Acme Inc." />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label>Player Name</Label>
                            <Input v-model="form.user_name" />
                        </div>
                        <div class="space-y-1">
                            <Label>Player Email</Label>
                            <Input v-model="form.user_email" type="email" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <Label>Player Password</Label>
                        <Input v-model="form.user_password" type="password" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label>Admin Name</Label>
                            <Input v-model="form.admin_name" />
                        </div>
                        <div class="space-y-1">
                            <Label>Admin Email</Label>
                            <Input v-model="form.admin_email" type="email" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <Label>Admin Password</Label>
                        <Input
                            v-model="form.admin_password"
                            type="password"
                        />
                    </div>

                    <p v-if="createError" class="text-sm text-destructive">
                        {{ createError }}
                    </p>
                </div>

                <DialogFooter>
                    <Button :disabled="createLoading" @click="submitCreate">
                        {{ createLoading ? 'Creating…' : 'Create' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showEdit">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Tenant Name</DialogTitle>
                </DialogHeader>

                <div class="space-y-3">
                    <div class="space-y-1">
                        <Label>Name</Label>
                        <Input v-model="editName" />
                    </div>

                    <p v-if="editError" class="text-sm text-destructive">
                        {{ editError }}
                    </p>
                </div>

                <DialogFooter>
                    <Button :disabled="editLoading" @click="submitEdit">
                        {{ editLoading ? 'Saving…' : 'Save' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
