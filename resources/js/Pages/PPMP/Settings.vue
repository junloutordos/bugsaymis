<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'

const props = defineProps({
    settings: Object,
    thresholds: Object,
})

const form = useForm({
    agency_name: props.settings.agency_name || '',
    active_fiscal_year: props.settings.active_fiscal_year || new Date().getFullYear(),
    submission_deadline: props.settings.submission_deadline || '',
    thresholds: { ...props.thresholds },
})

const save = () => {
    form.put(route('ppmp.settings.update'), { preserveScroll: true })
}

const formatThresholdLabel = (key) => {
    return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<template>
    <Head title="PPMP Settings" />
    <AdminLayout title="PPMP Settings">
        <div class="max-w-2xl mx-auto space-y-5">
            <AppPageHeader title="PPMP Settings" subtitle="Configure agency defaults and procurement method thresholds." />

            <form @submit.prevent="save" class="space-y-6">
                <!-- General Settings -->
                <AppCard title="General Settings">
                    <div class="space-y-4">
                        <AppInput v-model="form.agency_name" label="Agency Name" />
                        <div class="grid grid-cols-2 gap-4">
                            <AppInput v-model="form.active_fiscal_year" type="number" min="2020" label="Active Fiscal Year" />
                            <AppInput v-model="form.submission_deadline" type="date" label="Submission Deadline" />
                        </div>
                    </div>
                </AppCard>

                <!-- Threshold Settings -->
                <AppCard title="Procurement Method Thresholds" subtitle="Set maximum ABC amounts for alternative procurement methods. Leave blank for no limit.">
                    <div class="space-y-3">
                        <div v-for="(value, key) in form.thresholds" :key="key" class="flex items-center gap-3">
                            <label class="text-sm text-slate-700 w-56">{{ formatThresholdLabel(key) }}</label>
                            <input v-model.number="form.thresholds[key]" type="number" min="0" step="1000" placeholder="No limit"
                                   class="w-40 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                </AppCard>

                <div class="flex justify-end">
                    <AppButton type="submit" :loading="form.processing">
                        Save Settings
                    </AppButton>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
