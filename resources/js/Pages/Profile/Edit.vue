<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppButton from '@/Components/AppButton.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
// import UpdateProfileModal from './Partials/UpdateProfileModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { FingerPrintIcon, IdentificationIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const showProfileModal = ref(false);
function openProfileModal() {
    showProfileModal.value = true;
}
function closeProfileModal() {
    showProfileModal.value = false;
}

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AdminLayout title="Profile">
        <AppPageHeader title="Profile">
            <template #actions>
                <AppButton @click="openProfileModal">Update Profile</AppButton>
            </template>
        </AppPageHeader>

        <!-- <UpdateProfileModal :show="showProfileModal" @close="closeProfileModal" /> -->

        <div class="py-6">
            <div class="mx-auto max-w-3xl space-y-5">
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 px-6 py-5">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 px-6 py-5">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 px-6 py-5">
                    <DeleteUserForm class="max-w-xl" />
                </div>

                <!-- Employee Digital ID -->
                <Link :href="route('profile.digital-id')"
                      class="block bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 px-6 py-5 hover:border-indigo-300 hover:shadow-md transition-all group">
                    <div class="flex items-center justify-between max-w-xl">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center group-hover:bg-indigo-100 transition-colors">
                                <IdentificationIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Employee Digital ID</p>
                                <p class="text-xs text-slate-500">View your ID card and flip to see the back</p>
                            </div>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-500 text-sm transition-colors">&rarr;</span>
                    </div>
                </Link>

                <!-- Digital Signature -->
                <Link :href="route('profile.signature')"
                      class="block bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 px-6 py-5 hover:border-indigo-300 hover:shadow-md transition-all group">
                    <div class="flex items-center justify-between max-w-xl">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center group-hover:bg-indigo-100 transition-colors">
                                <FingerPrintIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Digital Signature</p>
                                <p class="text-xs text-slate-500">Set up your signature image and signing PIN</p>
                            </div>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-500 text-sm transition-colors">&rarr;</span>
                    </div>
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
