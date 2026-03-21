<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
// import UpdateProfileModal from './Partials/UpdateProfileModal.vue';
import { Head } from '@inertiajs/vue3';
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>
