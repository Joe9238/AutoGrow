<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SensorReadingsChart from '@/Components/SensorReadingsChart.vue';
import { Link } from '@inertiajs/vue3';

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

function startEditThresholds() {
    yellowThresholdInput.value = selectedDevice.value?.yellow_threshold ?? 50;
    redThresholdInput.value = selectedDevice.value?.red_threshold ?? 30;
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
    thresholdForm.device_uid = selectedDevice.value.device_uid;
    thresholdForm.yellow_threshold = yellowThresholdInput.value;
    thresholdForm.red_threshold = redThresholdInput.value;
    thresholdForm.post('/devices/update-thresholds', {
        preserveScroll: true,
        onSuccess: () => {
            thresholdSuccess.value = 'Thresholds updated!';
            editingThresholds.value = false;
            window.location.reload();
        },
        onError: (errors) => {
            thresholdError.value = errors.yellow_threshold || errors.red_threshold || 'Failed to update thresholds.';
        },
        onFinish: () => {
            updatingThresholds.value = false;
        },
    });
}

const page = usePage();
const devices = (page.props.devices as Array<{ name: string; device_uid: string; created_at: string; postcode?: string; latitude?: number; longitude?: number }>) || [];

const pairingCode = page.props.pairingCode as string | null;
const deviceReadings = (page.props.deviceReadings as Array<any>) || [];

// Get selected device from query string (reactive)
const selectedDeviceUid = computed(() => {
    const url = usePage().url as string;
    const params = new URLSearchParams(url.split('?')[1] || '');
    return params.get('device');
});

const selectedDevice = computed(() => {
    if (!selectedDeviceUid.value) return null;
    return devices.find((d: any) => d.device_uid === selectedDeviceUid.value) || null;
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
        postcodeForm.device_uid = selectedDevice.value.device_uid;
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
            <div v-if="selectedDevice" style="display: flex; flex-direction: row;">
                <div class="flex-1 rounded-lg border-2 border-dashed border-gray-300 p-4">
                    <h2 class="text-lg font-semibold">Device Information</h2>
                    <p><strong>Name:</strong> {{ selectedDevice.name }}</p>
                    <p><strong>Device UID:</strong> {{ selectedDevice.device_uid }}</p>
                    <p><strong>Created At:</strong> {{ selectedDevice.created_at }}</p>
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
            <SensorReadingsChart v-if="selectedDevice" :readings="deviceReadings" :yellow-threshold="selectedDevice.yellow_threshold" :red-threshold="selectedDevice.red_threshold" />
            <div v-else class="flex flex-col items-center justify-center h-96">
                <div class="rounded-lg border-2 border-dashed border-gray-300 p-8 text-center">
                    <h2 class="text-xl font-semibold mb-2">No Device Selected</h2>
                    <p>Please select a device from the device list to view its information and sensor readings.</p>
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
