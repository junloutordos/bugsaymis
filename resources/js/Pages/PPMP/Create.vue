<script setup>
import { ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'

const props = defineProps({
    userDivision: Object,
    userOffice: Object,
    fiscalYears: Array,
    previousPpmps: Array,
    supplementablePpmps: { type: Array, default: () => [] },
})

const form = useForm({
    fiscal_year: props.fiscalYears[0] || new Date().getFullYear(),
    title: '',
    source_ppmp_id: null,
    is_supplemental: false,
    parent_ppmp_id: null,
})

watch(() => form.is_supplemental, (val) => {
    if (!val) form.parent_ppmp_id = null
})

const submit = () => {
    form.post(route('ppmp.store'), { preserveScroll: true })
}
</script>

<template>
    <Head title="Create PPMP" />
    <AdminLayout title="Create PPMP">
        <div class="max-w-2xl mx-auto space-y-5">
            <AppPageHeader title="New Project Procurement Management Plan" />

            <AppCard>
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Office / Unit (read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">End-User / Unit</label>
                        <input type="text"
                               :value="userOffice?.name || userDivision?.division_name || 'Not assigned'"
                               disabled
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" />
                        <p v-if="userOffice" class="mt-1 text-xs text-slate-500">Division: {{ userDivision?.division_name }}</p>
                    </div>

                    <!-- Fiscal Year -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Fiscal Year</label>
                        <select v-model="form.fiscal_year"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option v-for="y in fiscalYears" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <p v-if="form.errors.fiscal_year" class="mt-1 text-sm text-danger-600">{{ form.errors.fiscal_year }}</p>
                    </div>

                    <!-- Title -->
                    <AppInput v-model="form.title" label="Project / Program / Activity Title" placeholder="e.g., Office Supplies and Equipment" :error="form.errors.title" />

                    <!-- Supplemental toggle -->
                    <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input v-model="form.is_supplemental" type="checkbox"
                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
                            <div>
                                <span class="text-sm font-medium text-slate-700">Supplemental PPMP</span>
                                <p class="text-xs text-slate-500 mt-0.5">For additional items not included in the original PPMP for the same fiscal year.</p>
                            </div>
                        </label>

                        <div v-if="form.is_supplemental">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Parent PPMP</label>
                            <select v-model="form.parent_ppmp_id"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option :value="null">— Select parent PPMP —</option>
                                <option v-for="p in supplementablePpmps" :key="p.id" :value="p.id">
                                    {{ p.ppmp_number }} — {{ p.title }} (FY {{ p.fiscal_year }})
                                </option>
                            </select>
                            <p v-if="form.errors.parent_ppmp_id" class="mt-1 text-sm text-danger-600">{{ form.errors.parent_ppmp_id }}</p>
                            <p v-if="!supplementablePpmps.length" class="mt-1 text-xs text-warning-600">No approved PPMPs available to supplement for this year.</p>
                        </div>
                    </div>

                    <!-- Duplicate from previous (only for regular PPMPs) -->
                    <div v-if="!form.is_supplemental && previousPpmps.length">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Copy items from previous PPMP (optional)</label>
                        <select v-model="form.source_ppmp_id"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option :value="null">— Start fresh —</option>
                            <option v-for="p in previousPpmps" :key="p.id" :value="p.id">
                                {{ p.ppmp_number }} — {{ p.title }} (FY {{ p.fiscal_year }})
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Items will be copied with quantities intact but unit costs reset to zero.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <AppButton as="link" variant="secondary" :href="route('ppmp.index')">Cancel</AppButton>
                        <AppButton type="submit" :loading="form.processing">
                            Create PPMP
                        </AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>
