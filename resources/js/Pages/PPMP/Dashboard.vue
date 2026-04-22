<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    fiscalYear: Number,
    fiscalYears: Array,
    metrics: Object,
})

const selectedYear = ref(props.fiscalYear)

const changeYear = () => {
    router.get(route('ppmp.dashboard'), { fiscal_year: selectedYear.value }, { preserveState: true, replace: true })
}

const formatPeso = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const statusColors = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-blue-100 text-blue-700',
    returned: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    consolidated: 'bg-indigo-100 text-indigo-700',
}

const totalPpmps = computed(() => {
    const c = props.metrics.status_counts || {}
    return Object.values(c).reduce((s, v) => s + v, 0)
})
</script>

<template>
    <Head title="PPMP Dashboard" />
    <AdminLayout title="PPMP Dashboard">
        <!-- Year selector -->
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-800">Procurement Planning Overview</h2>
            <select v-model="selectedYear" @change="changeYear"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
            </select>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total PPMPs</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ totalPpmps }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Budget (Approved)</p>
                <p class="text-2xl font-bold text-indigo-700 mt-1">₱{{ formatPeso(metrics.budget_by_category?.grand_total) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Submission Rate</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ metrics.submission_rate?.rate_percent || 0 }}%</p>
                <p class="text-xs text-slate-500">{{ metrics.submission_rate?.submitted_units || 0 }} / {{ metrics.submission_rate?.total_units || 0 }} units</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Approved</p>
                <p class="text-2xl font-bold text-green-700 mt-1">{{ (metrics.status_counts?.approved || 0) + (metrics.status_counts?.consolidated || 0) }}</p>
            </div>
        </div>

        <!-- Status breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">Status Breakdown</h3>
                <div class="space-y-2">
                    <div v-for="(count, status) in metrics.status_counts" :key="status" class="flex items-center justify-between">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                              :class="statusColors[status]">{{ status }}</span>
                        <span class="text-sm font-medium text-slate-700">{{ count }}</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">Budget by Category</h3>
                <div class="space-y-2">
                    <div v-for="cat in (metrics.budget_by_category?.categories || [])" :key="cat.category" class="flex items-center justify-between">
                        <span class="text-sm text-slate-600">{{ cat.label }}</span>
                        <span class="text-sm font-medium text-slate-700">₱{{ formatPeso(cat.subtotal) }}</span>
                    </div>
                    <div class="border-t border-slate-100 pt-2 flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-700">Grand Total</span>
                        <span class="text-sm font-bold text-indigo-700">₱{{ formatPeso(metrics.budget_by_category?.grand_total) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unit compliance table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-700">Unit Compliance</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Division / Office</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">PPMP Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Budget</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="u in metrics.unit_compliance" :key="u.division_id" class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 text-slate-700">{{ u.division_name }}</td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="u.ppmp_status" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                      :class="statusColors[u.ppmp_status]">{{ u.ppmp_status }}</span>
                                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Not Started</span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ u.total_budget > 0 ? '₱' + formatPeso(u.total_budget) : '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ u.submitted_at || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
