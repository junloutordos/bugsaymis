<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useCountUp } from '@/Composables/useCountUp.js'

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

const statusBadgeColor = (status) => {
    const map = {
        draft: 'slate',
        submitted: 'blue',
        returned: 'amber',
        approved: 'green',
        consolidated: 'indigo',
    }
    return map[status] ?? 'slate'
}

const totalPpmps = computed(() => {
    const c = props.metrics.status_counts || {}
    return Object.values(c).reduce((s, v) => s + v, 0)
})

const approvedCount = computed(() =>
    (props.metrics.status_counts?.approved || 0) + (props.metrics.status_counts?.consolidated || 0))

const { values: kpiValues } = useCountUp(() => [totalPpmps.value, approvedCount.value])
</script>

<template>
    <Head title="PPMP Dashboard" />
    <AdminLayout title="PPMP Dashboard">
        <div class="space-y-5">
            <AppPageHeader hero class="dash-section" style="--stagger: 0" title="Procurement Planning Overview">
                <template #actions>
                    <select v-model="selectedYear" @change="changeYear"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
                    </select>
                </template>
            </AppPageHeader>

            <!-- Summary cards -->
            <div class="dash-section grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" style="--stagger: 1">
                <AppCard>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total PPMPs</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ kpiValues[0] ?? totalPpmps }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Budget (Approved)</p>
                    <p class="text-2xl font-bold text-indigo-700 mt-1">₱{{ formatPeso(metrics.budget_by_category?.grand_total) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Submission Rate</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">{{ metrics.submission_rate?.rate_percent || 0 }}%</p>
                    <p class="text-xs text-slate-500">{{ metrics.submission_rate?.submitted_units || 0 }} / {{ metrics.submission_rate?.total_units || 0 }} units</p>
                </AppCard>
                <AppCard>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Approved</p>
                    <p class="text-2xl font-bold text-success-700 mt-1 tabular-nums">{{ kpiValues[1] ?? approvedCount }}</p>
                </AppCard>
            </div>

            <!-- Status breakdown -->
            <div class="dash-section grid grid-cols-1 lg:grid-cols-2 gap-4" style="--stagger: 2">
                <AppCard title="Status Breakdown">
                    <div class="space-y-2">
                        <div v-for="(count, status) in metrics.status_counts" :key="status" class="flex items-center justify-between">
                            <AppBadge :color="statusBadgeColor(status)" class="capitalize">{{ status }}</AppBadge>
                            <span class="text-sm font-medium text-slate-700">{{ count }}</span>
                        </div>
                    </div>
                </AppCard>
                <AppCard title="Budget by Category">
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
                </AppCard>
            </div>

            <!-- Unit compliance table -->
            <div class="dash-section" style="--stagger: 3">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Unit Compliance</h3>
            <AppTable :is-empty="!metrics.unit_compliance?.length">
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Division / Office</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">PPMP Status</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Budget</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Submitted</th>
                    </tr>
                </template>

                <tr v-for="u in metrics.unit_compliance" :key="u.division_id" class="hover:bg-indigo-50/40">
                    <td class="px-4 py-3 text-slate-700">{{ u.division_name }}</td>
                    <td class="px-4 py-3 text-center">
                        <AppBadge v-if="u.ppmp_status" :color="statusBadgeColor(u.ppmp_status)" class="capitalize">{{ u.ppmp_status }}</AppBadge>
                        <AppBadge v-else color="red">Not Started</AppBadge>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-700">{{ u.total_budget > 0 ? '₱' + formatPeso(u.total_budget) : '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ u.submitted_at || '—' }}</td>
                </tr>

                <template #empty>
                    <EmptyState title="No unit compliance data" subtitle="Nothing to show for this fiscal year yet." />
                </template>
            </AppTable>
            </div>
        </div>
    </AdminLayout>
</template>
