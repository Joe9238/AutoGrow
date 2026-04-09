<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
    <AppShell variant="sidebar">
        <AppSidebar :devices="devices" />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
