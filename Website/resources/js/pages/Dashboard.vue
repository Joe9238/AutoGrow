<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SensorReadingsChart from '@/components/SensorReadingsChart.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const notifications = ref<Array<{ id: number; title: string; body?: string; created_at: string }>>([]);
const notificationCount = ref(0);
const showNotifications = ref(false);

// Rename device state
const renamingDevice = ref(false);
const renameInput = ref('');
const renameError = ref('');
const updatingRename = ref(false);
const renameForm = useForm({
    device_uid: '',
    name: '',
});

const editingPostcode = ref(false);
const postcodeInput = ref('');
const postcodeError = ref('');
const updatingPostcode = ref(false);
const postcodeForm = useForm({
    device_uid: '',
    postcode: '',
    latitude: '',
    longitude: '',
});

// Threshold slider state
const editingThresholds = ref(false);
const yellowThresholdInput = ref(0);
const redThresholdInput = ref(0);
const thresholdError = ref('');
const thresholdSuccess = ref('');
const updatingThresholds = ref(false);
const thresholdForm = useForm({
    device_uid: '',
    yellow_threshold: 0,
    red_threshold: 0,
});

// Device deletion state
const deletingDevice = ref(false);
const deleteError = ref('');
const deleteForm = useForm({
    device_uid: '',
});

// Props
const page = usePage();
const devices = (page.props.devices as Array<{
    name: string;
    device_uid: string;
    created_at: string;
    postcode?: string;
    latitude?: number;
    longitude?: number;
    yellow_threshold: number;
    red_threshold: number;
}>) || [];
const pairingCode = page.props.pairingCode as string | null;
const deviceReadings = (page.props.deviceReadings as Array<any>) || [];

function startEditThresholds() {
    if (!selectedDevice.value) return;
    yellowThresholdInput.value = (selectedDevice.value as any).yellow_threshold ?? 50;
    redThresholdInput.value = (selectedDevice.value as any).red_threshold ?? 30;
    thresholdError.value = '';
    thresholdSuccess.value = '';
    editingThresholds.value = true;
}

function cancelEditThresholds() {
    editingThresholds.value = false;
    thresholdError.value = '';
    thresholdSuccess.value = '';
}

async function saveThresholds() {
    thresholdError.value = '';
    thresholdSuccess.value = '';
    updatingThresholds.value = true;
    // Validation: red <= yellow
    if (redThresholdInput.value > yellowThresholdInput.value) {
        thresholdError.value = 'Red threshold cannot be above yellow threshold.';
        updatingThresholds.value = false;
        return;
    }
    if (!selectedDevice.value){ 
        return;
    }
    thresholdForm.device_uid = (selectedDevice.value as any).device_uid;
    thresholdForm.yellow_threshold = yellowThresholdInput.value;
    thresholdForm.red_threshold = redThresholdInput.value;
    thresholdForm.post('/devices/update-thresholds', { // send data to update route which will update the device
        preserveScroll: true,
        onSuccess: () => {
            thresholdSuccess.value = 'Thresholds updated!';
            editingThresholds.value = false;
            window.location.reload(); // reload to get updated thresholds and re-render graph with new thresholds
        },
        onError: (errors) => {
            thresholdError.value = errors.yellow_threshold || errors.red_threshold || 'Failed to update thresholds.';
        },
        onFinish: () => {
            updatingThresholds.value = false;
        },
    });
}

// Get selected device from query string in url
const selectedDeviceUid = computed(() => {
    const url = usePage().url as string;
    const params = new URLSearchParams(url.split('?')[1] || '');
    return params.get('device');
});

const selectedDevice = computed(() => {
    if (!selectedDeviceUid.value) return null;
    return devices.find((d: any) => d.device_uid === selectedDeviceUid.value) || null;
});

function startRenameDevice() {
    renameInput.value = selectedDevice.value?.name || '';
    renameError.value = '';
    renamingDevice.value = true;
}

function cancelRenameDevice() {
    renamingDevice.value = false;
    renameError.value = '';
}

function saveRenameDevice() {
    renameError.value = '';
    updatingRename.value = true;
    if (!selectedDevice.value) return;
    renameForm.device_uid = (selectedDevice.value as any).device_uid;
    renameForm.name = renameInput.value.trim();
    if (!renameForm.name) {
        renameError.value = 'Name cannot be empty.';
        updatingRename.value = false;
        return;
    }
    renameForm.post('/devices/rename', {
        preserveScroll: true,
        onSuccess: () => {
            renamingDevice.value = false;
            window.location.reload();
        },
        onError: (errors) => {
            renameError.value = errors.name || 'Failed to rename device.';
        },
        onFinish: () => {
            updatingRename.value = false;
        },
    });
}

function startEditPostcode() {
    postcodeInput.value = selectedDevice.value?.postcode || '';
    postcodeError.value = '';
    editingPostcode.value = true;
}

async function savePostcode() {
    postcodeError.value = '';
    updatingPostcode.value = true;
    try {
        const response = await fetch(`https://api.postcodes.io/postcodes/${postcodeInput.value.replace(/\s+/g, '')}`);
        const data = await response.json();
        if (data.status !== 200 || !data.result) {
            postcodeError.value = 'Invalid postcode.';
            updatingPostcode.value = false;
            return;
        }
        if (!selectedDevice.value) return;
        postcodeForm.device_uid = (selectedDevice.value as any).device_uid;
        postcodeForm.postcode = data.result.postcode;
        postcodeForm.latitude = data.result.latitude;
        postcodeForm.longitude = data.result.longitude;
        postcodeForm.post('/devices/update-postcode', {
            preserveScroll: true,
            onSuccess: () => {
                window.location.reload();
            },
            onError: (errors) => {
                postcodeError.value = errors.postcode || 'Failed to update postcode.';
            },
            onFinish: () => {
                updatingPostcode.value = false;
            },
        });
    } catch (e) {
        postcodeError.value = 'Failed to update postcode.';
        updatingPostcode.value = false;
    }
}

function startDeleteDevice() {
    deleteError.value = '';
    deletingDevice.value = true;
}

function cancelDeleteDevice() {
    deletingDevice.value = false;
    deleteError.value = '';
}

function confirmDeleteDevice() {
    deleteError.value = '';
    if (!selectedDevice.value) return;
    deleteForm.device_uid = (selectedDevice.value as any).device_uid;
    deleteForm.post('/devices/delete', {
        preserveScroll: true,
        onSuccess: () => {
            deletingDevice.value = false;
            window.location.href = '/dashboard';
        },
        onError: (errors) => {
            deleteError.value = errors.device_uid || 'Failed to delete device.';
        },
        onFinish: () => {
            deletingDevice.value = false;
        },
    });
}

async function fetchNotifications() {
    try {
        const res = await fetch('/notifications/list');
        if (!res.ok) return;
        const data = await res.json();
        notifications.value = data.notifications || [];
        notificationCount.value = notifications.value.length;
    } catch {}
}

async function deleteNotification(id: number) {
    await fetch(`/notifications/delete/${id}`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' } });
    notifications.value = notifications.value.filter(n => n.id !== id);
    notificationCount.value = notifications.value.length;
}

onMounted(() => {
    fetchNotifications();
});

setInterval(fetchNotifications, 60000);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <!-- Notifications Icon and Dropdown -->
    <div style="position: fixed; top: 0; right: 0; width: 100vw; z-index: 12000; pointer-events: none;">
        <div style="position: absolute; top: 18px; right: 36px; pointer-events: auto;">
            <button @click="showNotifications = !showNotifications" class="relative p-2 rounded-full hover:bg-gray-200 focus:outline-none" style="background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.07);">
                <!-- AI generated SVG icon for notifications -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7 text-gray-700">
                    <path d="M12 2C8.13 2 5 5.13 5 9v4.28c0 .41-.16.8-.44 1.09l-1.32 1.32A1 1 0 004 17h16a1 1 0 00.76-1.66l-1.32-1.32a1.5 1.5 0 01-.44-1.09V9c0-3.87-3.13-7-7-7zm0 20a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0012 22z"/>
                </svg>
                <span v-if="notificationCount > 0" class="absolute -top-1 -right-1 bg-red-600 text-white rounded-full text-xs px-1.5 py-0.5 min-w-[20px] text-center border border-white">{{ notificationCount }}</span>
            </button>
            <div v-if="showNotifications" class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-lg z-50" style="top: 44px;">
                <div class="flex items-center justify-between px-4 py-2 border-b">
                    <span class="font-semibold">Notifications</span>
                    <button class="text-xs text-gray-500 hover:text-red-600" @click="showNotifications = false">Close</button>
                </div>
                <ul class="max-h-80 overflow-y-auto">
                    <li v-for="n in notifications" :key="n.id" class="flex items-start justify-between px-4 py-3 border-b last:border-b-0 hover:bg-gray-50">
                        <div>
                            <div class="font-semibold">{{ n.title }}</div>
                            <div class="text-xs text-gray-600 whitespace-pre-line" v-if="n.body">{{ n.body }}</div>
                            <div class="text-xs text-gray-400 mt-1">{{ n.created_at }}</div>
                        </div>
                        <button class="ml-2 text-red-500 hover:text-red-700 text-xs" @click="deleteNotification(n.id)">Delete</button>
                    </li>
                    <li v-if="notifications.length === 0" class="px-4 py-6 text-center text-gray-400">No notifications.</li>
                </ul>
            </div>
        </div>
    </div>

    <AppLayout :breadcrumbs="breadcrumbs" :devices="devices">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div v-if="selectedDevice" style="display: flex; flex-direction: row;">
                <div class="flex-1 rounded-lg border-2 border-dashed border-gray-300 p-4">
                    <h2 class="text-lg font-semibold">Device Information</h2>
                    <div class="flex items-center mb-2">
                        <p class="mr-2"><strong>Name:</strong> {{ selectedDevice.name }}</p>
                        <button class="px-2 py-1 text-xs border rounded bg-blue-500 text-white ml-2" @click="startRenameDevice">Rename</button>
                    </div>
                    <!-- Rename Device Modal -->
                    <div v-if="renamingDevice" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;">
                        <div id="rename-popup" style="background: white; padding: 24px; border-radius: 8px; min-width: 340px; z-index: 10000; box-shadow: 0 2px 16px rgba(0,0,0,0.15);">
                            <h2 class="text-lg font-semibold mb-2">Rename Device</h2>
                            <input v-model="renameInput" class="border rounded px-2 py-1 text-sm w-full mb-3" placeholder="Enter new device name" :disabled="updatingRename || renameForm.processing" />
                            <div class="flex gap-2 justify-end">
                                <button class="px-4 py-1.5 text-sm border rounded" @click="cancelRenameDevice">Cancel</button>
                                <button class="px-4 py-1.5 text-sm border rounded bg-blue-500 text-white hover:bg-blue-600 transition" :disabled="updatingRename || renameForm.processing" @click="saveRenameDevice">Save</button>
                            </div>
                            <span v-if="renameError" class="mt-2 text-red-500 text-xs block">{{ renameError }}</span>
                        </div>
                    </div>
                    <!-- Other device information -->
                    <p><strong>Device UID:</strong> {{ selectedDevice.device_uid }}</p>
                    <p><strong>Created At:</strong> {{ selectedDevice.created_at }}</p>
                    <!-- Postcode and coordinates -->
                    <div class="mt-2">
                        <label class="font-semibold">Postcode: </label>
                        <span v-if="!editingPostcode">
                            <span :class="[!selectedDevice.postcode ? 'text-red-600 font-bold' : '']">
                                {{ selectedDevice.postcode || 'N/A' }}
                            </span>
                            <button class="ml-2 px-2 py-1 text-xs border rounded" @click="startEditPostcode">Edit</button>
                        </span>
                        <span v-else>
                            <input v-model="postcodeInput" class="border rounded px-2 py-1 text-sm" placeholder="Enter postcode" />
                            <button class="ml-2 px-2 py-1 text-xs border rounded bg-blue-500 text-white" :disabled="updatingPostcode || postcodeForm.processing" @click="savePostcode">Save</button>
                            <button class="ml-2 px-2 py-1 text-xs border rounded" :disabled="updatingPostcode || postcodeForm.processing" @click="editingPostcode=false">Cancel</button>
                            <span v-if="postcodeError" class="ml-2 text-red-500 text-xs">{{ postcodeError }}</span>
                            <span v-if="postcodeForm.recentlySuccessful" class="ml-2 text-green-600 text-xs">Saved.</span>
                        </span>
                        <div v-if="!selectedDevice.postcode" class="mt-2 text-red-600 text-sm font-semibold">
                            You need to set the postcode to schedule watering based on weather data.
                        </div>
                    </div>
                    <div v-if="selectedDevice.latitude && selectedDevice.longitude" class="mt-1 text-sm text-gray-600">
                        <span>Lat: {{ selectedDevice.latitude }}, Lng: {{ selectedDevice.longitude }}</span>
                    </div>
                </div>
                <!-- Threshold Sliders Component -->
                <div class="flex flex-col w-96 ml-6 rounded-lg border-2 border-dashed border-gray-300 p-4 bg-gray-50">
                    <h2 class="text-lg font-semibold mb-2">Soil Moisture Thresholds</h2>
                    <div v-if="!editingThresholds">
                        <div class="mb-2">
                            <span class="font-semibold">Yellow Threshold: </span>
                            <span>{{ selectedDevice.yellow_threshold }}%</span>
                            <div v-if="selectedDevice.yellow_threshold === 0" class="mt-1 text-yellow-700 text-xs font-semibold">
                                Warning: At 0%, the device will not dispense water automatically.
                            </div>
                            <div v-else-if="selectedDevice.yellow_threshold === 100" class="mt-1 text-yellow-700 text-xs font-semibold">
                                Warning: At 100%, the device may continuously dispense water.
                            </div>
                        </div>
                        <div class="mb-2">
                            <span class="font-semibold">Red Threshold: </span>
                            <span>{{ selectedDevice.red_threshold }}%</span>
                        </div>
                        <button class="px-3 py-1 text-xs border rounded bg-blue-500 text-white" @click="startEditThresholds">Edit Thresholds</button>
                    </div>
                    <div v-else>
                        <div class="mb-4">
                            <label class="block font-semibold mb-1">Yellow Threshold: <span class="text-gray-600">{{ yellowThresholdInput }}%</span></label>
                            <input type="range" min="0" max="100" v-model.number="yellowThresholdInput" class="w-full" />
                            <div v-if="yellowThresholdInput === 0" class="mt-1 text-yellow-700 text-xs font-semibold">
                                Warning: At 0%, the device will not dispense water automatically.
                            </div>
                            <div v-else-if="yellowThresholdInput === 100" class="mt-1 text-yellow-700 text-xs font-semibold">
                                Warning: At 100%, the device may continuously dispense water.
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block font-semibold mb-1">Red Threshold: <span class="text-gray-600">{{ redThresholdInput }}%</span></label>
                            <input type="range" min="0" max="100" v-model.number="redThresholdInput" class="w-full" />
                        </div>
                        <div class="flex gap-2">
                            <button class="px-3 py-1 text-xs border rounded bg-blue-500 text-white" :disabled="updatingThresholds || thresholdForm.processing" @click="saveThresholds">Save</button>
                            <button class="px-3 py-1 text-xs border rounded" :disabled="updatingThresholds || thresholdForm.processing" @click="cancelEditThresholds">Cancel</button>
                        </div>
                        <span v-if="thresholdError" class="mt-2 text-red-500 text-xs">{{ thresholdError }}</span>
                        <span v-if="thresholdSuccess" class="mt-2 text-green-600 text-xs">{{ thresholdSuccess }}</span>
                    </div>
                </div>
            </div>

            <!-- Graph component -->
            <SensorReadingsChart v-if="selectedDevice" :readings="deviceReadings" :yellow-threshold="selectedDevice.yellow_threshold" :red-threshold="selectedDevice.red_threshold" />

            <!-- Delete Device Button (bottom right) -->
            <div v-if="selectedDevice" class="flex justify-end mt-8">
                <button class="px-4 py-2 text-sm border rounded bg-red-600 text-white hover:bg-red-700 transition" @click="startDeleteDevice">
                    Delete Device
                </button>
            </div>
            <div v-else class="flex flex-col items-center justify-center h-96">
                <div class="rounded-lg border-2 border-dashed border-gray-300 p-8 text-center">
                    <h2 class="text-xl font-semibold mb-2">No Device Selected</h2>
                    <p>Please select a device from the device list to view its information and sensor readings.</p>
                </div>
            </div>
        </div>
        <!-- Delete Device Confirmation Modal -->
        <div v-if="deletingDevice" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;">
            <div id="delete-popup" style="background: white; padding: 24px; border-radius: 8px; min-width: 340px; z-index: 10000; box-shadow: 0 2px 16px rgba(0,0,0,0.15);">
                <h2 class="text-lg font-semibold mb-2 text-red-700">Delete Device</h2>
                <p class="mb-4">Are you sure you want to delete this device?<br><span class="font-semibold">This action cannot be undone.</span></p>
                <div class="flex gap-2 justify-end">
                    <button class="px-4 py-1.5 text-sm border rounded" @click="cancelDeleteDevice">Cancel</button>
                    <button class="px-4 py-1.5 text-sm border rounded bg-red-600 text-white hover:bg-red-700 transition" :disabled="deleteForm.processing" @click="confirmDeleteDevice">Delete</button>
                </div>
                <span v-if="deleteError" class="mt-2 text-red-500 text-xs block">{{ deleteError }}</span>
            </div>
        </div>
        <!-- Pairing Code Modal, appears under /pairing-code route -->
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
