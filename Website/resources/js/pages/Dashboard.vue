<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const page = usePage();
const devices = (page.props.devices as Array<{ name: string; device_uid: string; created_at: string }>) || [];
const pairingCode = page.props.pairingCode as string | null;

// Get selected device from query string (reactive)
const selectedDeviceUid = computed(() => {
    const url = usePage().url as string;
    const params = new URLSearchParams(url.split('?')[1] || '');
    return params.get('device');
});

const selectedDevice = computed(() => {
    if (!selectedDeviceUid.value && devices.length > 0) return devices[0];
    return devices.find((d: any) => d.device_uid === selectedDeviceUid.value) || devices[0];
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs" :devices="devices">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div style="display: flex; flex-direction: row;">
                <div class="flex-1 rounded-lg border-2 border-dashed border-gray-300 p-4">
                    <h2 class="text-lg font-semibold">Device Information</h2>
                    <div v-if="selectedDevice">
                        <p><strong>Name:</strong> {{ selectedDevice.name }}</p>
                        <p><strong>Device UID:</strong> {{ selectedDevice.device_uid }}</p>
                        <p><strong>Created At:</strong> {{ selectedDevice.created_at }}</p>
                    </div>
                    <div v-else>
                        <p>No device selected.</p>
                    </div>
                </div>
            </div>
        </div>
        <div 
            v-if="pairingCode !== null"
            style="
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            "
        >
            <div 
                id="popup"
                style="
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    min-width: 300px;
                    z-index: 10000;
                "
            >
                <h2>Your Pairing Code</h2>
                <p style="font-size:24px; font-weight:bold;">
                    {{ pairingCode }}
                </p>
                <p>Expires in 10 minutes</p>

                <Link
                    :href="'/dashboard'"
                    class="inline-block rounded-sm border px-5 py-1.5 my-1 text-sm"
                >
                    Close
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
