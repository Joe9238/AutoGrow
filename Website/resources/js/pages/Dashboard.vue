<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div style="display: flex; flex-direction: row;">
                <div class="flex-1 rounded-lg border-2 border-dashed border-gray-300 p-4">
                    <h2 class="text-lg font-semibold">Welcome to your dashboard!</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        This is your dashboard where you can manage your account and view your activity.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
