<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
// import UpdateProfileModal from './Partials/UpdateProfileModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { FingerPrintIcon } from '@heroicons/vue/24/outline';
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

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-slate-800">
                    Profile
                </h2>
                <button @click="openProfileModal" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                    Update Profile
                </button>
            </div>
        </template>

        <!-- <UpdateProfileModal :show="showProfileModal" @close="closeProfileModal" /> -->

        <div class="py-10">
            <div class="mx-auto max-w-3xl space-y-5 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-6 py-5">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-6 py-5">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-6 py-5">
                    <DeleteUserForm class="max-w-xl" />
                </div>

                <!-- Digital Signature -->
                <Link :href="route('profile.signature')"
                      class="block bg-white rounded-xl border border-slate-100 shadow-sm px-6 py-5 hover:border-indigo-300 hover:shadow-md transition-all group">
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
    </AuthenticatedLayout>
</template>
