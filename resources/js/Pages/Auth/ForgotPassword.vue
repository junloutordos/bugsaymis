<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <h1 class="font-heading text-lg font-semibold text-slate-800 mb-1">Forgot your password?</h1>
        <p class="mb-4 text-sm text-slate-500">
            No problem. Just let us know your email address and we will email
            you a password reset link that will allow you to choose a new one.
        </p>

        <div
            v-if="status"
            class="mb-4 rounded-lg bg-success-50 px-3 py-2 text-sm font-medium text-success-700"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <AppInput
                v-model="form.email"
                type="email"
                label="Email"
                required
                autofocus
                autocomplete="username"
                :error="form.errors.email"
            />

            <div class="flex items-center justify-end">
                <AppButton type="submit" :loading="form.processing">
                    Email Password Reset Link
                </AppButton>
            </div>
        </form>
    </GuestLayout>
</template>
