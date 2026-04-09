<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Profile information"
                    description="Update your personal information"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <!-- Personal Details -->
                    <fieldset class="border p-4 rounded">
                        <legend class="font-semibold mb-2">Personal Details</legend>
                        <div class="grid grid-cols-1 gap-4">
                            <div class="grid gap-2">
                                <Label for="title">Title</Label>
                                <select
                                    id="title"
                                    name="title"
                                    :default-value="user.title"
                                    required
                                    class="outline-none border-input flex h-9 w-full text-[14px] rounded-md border bg-transparent px-2.5 py-1 text-base shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                >
                                    <option value="" disabled hidden>Select title</option>
                                    <option value="Mr">Mr</option>
                                    <option value="Mrs">Mrs</option>
                                    <option value="Miss">Miss</option>
                                    <option value="Ms">Ms</option>
                                    <option value="Mx">Mx</option>
                                    <option value="Other">Other</option>
                                </select>
                                <InputError class="mt-2" :message="errors.title" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    class="mt-1 block w-full"
                                    name="name"
                                    :default-value="user.name"
                                    required
                                    autocomplete="name"
                                    placeholder="Full name"
                                />
                                <InputError class="mt-2" :message="errors.name" />
                            </div>
                        </div>
                    </fieldset>

                    <!-- Address Details -->
                    <fieldset class="border p-4 rounded">
                        <legend class="font-semibold mb-2">Address Details</legend>
                        <div class="grid grid-cols-1 gap-4">
                            <div class="grid gap-2">
                                <Label for="house_name_number">House name/number</Label>
                                <Input
                                    id="house_name_number"
                                    name="house_name_number"
                                    :default-value="user.house_name_number"
                                    required
                                    placeholder="e.g. 12 or Rose Cottage"
                                />
                                <InputError class="mt-2" :message="errors.house_name_number" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="street_name">Street name</Label>
                                <Input
                                    id="street_name"
                                    name="street_name"
                                    :default-value="user.street_name"
                                    required
                                    placeholder="e.g. High Street"
                                />
                                <InputError class="mt-2" :message="errors.street_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="locality_name">Locality name</Label>
                                <Input
                                    id="locality_name"
                                    name="locality_name"
                                    :default-value="user.locality_name"
                                    placeholder="Locality name"
                                />
                                <InputError class="mt-2" :message="errors.locality_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="city">City</Label>
                                <Input
                                    id="city"
                                    name="city"
                                    :default-value="user.city"
                                    required
                                    placeholder="e.g. London"
                                />
                                <InputError class="mt-2" :message="errors.city" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="post_code">Post code</Label>
                                <Input
                                    id="post_code"
                                    name="post_code"
                                    :default-value="user.post_code"
                                    required
                                    placeholder="e.g. SW1A 1AA"
                                />
                                <InputError class="mt-2" :message="errors.post_code" />
                            </div>
                        </div>
                    </fieldset>

                    <!-- Contact Details -->
                    <fieldset class="border p-4 rounded">
                        <legend class="font-semibold mb-2">Contact Details</legend>
                        <div class="grid grid-cols-1 gap-4">
                            <div class="grid gap-2">
                                <Label for="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    name="email"
                                    :default-value="user.email"
                                    required
                                    autocomplete="username"
                                    placeholder="Email address"
                                />
                                <InputError class="mt-2" :message="errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    :default-value="user.phone"
                                    placeholder="Phone number"
                                />
                                <InputError class="mt-2" :message="errors.phone" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="mobile">Mobile</Label>
                                <Input
                                    id="mobile"
                                    name="mobile"
                                    :default-value="user.mobile"
                                    placeholder="Mobile number"
                                />
                                <InputError class="mt-2" :message="errors.mobile" />
                            </div>
                        </div>
                    </fieldset>

                    <!-- Email Verification Notice -->
                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>
                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email address.
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                        >Save</Button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
