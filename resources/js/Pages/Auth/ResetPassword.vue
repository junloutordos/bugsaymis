<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <h1 class="font-heading text-lg font-semibold text-slate-800 mb-4">Reset your password</h1>

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

            <AppInput
                v-model="form.password"
                type="password"
                label="Password"
                required
                autocomplete="new-password"
                :error="form.errors.password"
            />

            <AppInput
                v-model="form.password_confirmation"
                type="password"
                label="Confirm Password"
                required
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />

            <div class="flex items-center justify-end">
                <AppButton type="submit" :loading="form.processing">
                    Reset Password
                </AppButton>
            </div>
        </form>
    </GuestLayout>
</template>
