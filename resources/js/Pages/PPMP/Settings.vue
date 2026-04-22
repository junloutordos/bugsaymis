<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

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
        <div class="max-w-2xl mx-auto">
            <form @submit.prevent="save" class="space-y-6">
                <!-- General Settings -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-4">General Settings</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Agency Name</label>
                            <input v-model="form.agency_name" type="text"
                                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Active Fiscal Year</label>
                                <input v-model.number="form.active_fiscal_year" type="number" min="2020"
                                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Submission Deadline</label>
                                <input v-model="form.submission_deadline" type="date"
                                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Threshold Settings -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-sm font-semibold text-slate-700 mb-4">Procurement Method Thresholds</h3>
                    <p class="text-xs text-slate-500 mb-3">Set maximum ABC amounts for alternative procurement methods. Leave blank for no limit.</p>
                    <div class="space-y-3">
                        <div v-for="(value, key) in form.thresholds" :key="key" class="flex items-center gap-3">
                            <label class="text-sm text-slate-700 w-56">{{ formatThresholdLabel(key) }}</label>
                            <input v-model.number="form.thresholds[key]" type="number" min="0" step="1000" placeholder="No limit"
                                   class="w-40 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
