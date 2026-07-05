<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <h1 class="font-heading text-lg font-semibold text-slate-800 mb-1">Confirm your password</h1>
        <p class="mb-4 text-sm text-slate-500">
            This is a secure area of the application. Please confirm your
            password before continuing.
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <AppInput
                v-model="form.password"
                type="password"
                label="Password"
                required
                autocomplete="current-password"
                autofocus
                :error="form.errors.password"
            />

            <div class="flex justify-end">
                <AppButton type="submit" :loading="form.processing">
                    Confirm
                </AppButton>
            </div>
        </form>
    </GuestLayout>
</template>
