<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    devices?: Array<{ name: string; device_uid: string }>;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    devices: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :devices="devices">
        <slot />
    </AppLayout>
</template>
