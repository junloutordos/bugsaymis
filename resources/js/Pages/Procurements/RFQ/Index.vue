<script setup>
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { MagnifyingGlassIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    rfqs: { type: Array, default: () => [] },
    currentUser: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash)

const search = ref('')
const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
    const q = search.value.toLowerCase()
    if (!q) return props.rfqs
    return props.rfqs.filter(r =>
        r.rfq_number?.toLowerCase().includes(q) ||
        r.pr_no?.toLowerCase().includes(q) ||
        r.purpose?.toLowerCase().includes(q)
    )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
    const start = (currentPage.value - 1) * PER_PAGE
    return filtered.value.slice(start, start + PER_PAGE)
})

const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

const statusColor = (status) => {
    if (status === 'awarded') return 'green'
    if (status === 'open') return 'blue'
    if (status === 'closed') return 'slate'
    if (status === 'cancelled') return 'red'
    return 'amber'
}
</script>

<template>
    <Head title="Request for Quotation" />
    <AdminLayout title="Request for Quotation">
        <div class="space-y-5">

            <AppPageHeader title="Request for Quotation" />

            <div v-if="flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
                {{ flash.success }}
            </div>

            <AppFilterBar :result-label="`${filtered.length} RFQ${filtered.length !== 1 ? 's' : ''}`">
                <div class="relative w-full sm:w-64">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                    <input v-model="search" placeholder="Search RFQ…" @input="currentPage = 1"
                        class="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
            </AppFilterBar>

            <AppTable :is-empty="!displayed.length" :skeleton-cols="8">
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">RFQ No.</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">PR Reference</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Purpose</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Suppliers</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Awarded</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </template>

                <tr v-for="rfq in displayed" :key="rfq.id" class="hover:bg-indigo-50/40">
                    <td class="px-4 py-3 font-mono text-sm font-medium text-slate-800">{{ rfq.rfq_number }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 font-mono">{{ rfq.pr_no || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ rfq.purpose || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 text-center">{{ rfq.supplier_count }}</td>
                    <td class="px-4 py-3">
                        <AppBadge :color="statusColor(rfq.status)">{{ rfq.status_label }}</AppBadge>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ formatDate(rfq.rfq_date) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ rfq.awarded_at ? formatDate(rfq.awarded_at) : '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a :href="route('rfq.show', rfq.id)"
                            class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                            <DocumentTextIcon class="w-4 h-4" />
                            View
                        </a>
                    </td>
                </tr>

                <template #mobileCard>
                    <div v-for="rfq in displayed" :key="rfq.id" class="p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-mono text-sm font-medium text-slate-800">{{ rfq.rfq_number }}</p>
                                <p class="text-xs text-slate-500">PR {{ rfq.pr_no || '—' }}</p>
                            </div>
                            <AppBadge :color="statusColor(rfq.status)">{{ rfq.status_label }}</AppBadge>
                        </div>
                        <p class="text-xs text-slate-500 truncate">{{ rfq.purpose || '—' }}</p>
                        <div class="flex justify-between text-xs text-slate-500">
                            <span>{{ rfq.supplier_count }} supplier(s)</span>
                            <span>{{ formatDate(rfq.rfq_date) }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-xs text-slate-400">{{ rfq.awarded_at ? `Awarded ${formatDate(rfq.awarded_at)}` : 'Not awarded' }}</span>
                            <a :href="route('rfq.show', rfq.id)"
                                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                                <DocumentTextIcon class="w-4 h-4" />
                                View
                            </a>
                        </div>
                    </div>
                </template>

                <template #empty>
                    <EmptyState
                        :title="search ? 'No RFQs match your search.' : 'No RFQs yet.'"
                        :subtitle="search ? null : 'Create one from an approved Purchase Request.'"
                    />
                </template>

                <template #footer>
                    <PaginationControl
                        :current-page="currentPage"
                        :total-pages="totalPages"
                        @prev="currentPage--"
                        @next="currentPage++"
                        @page="currentPage = $event"
                    />
                </template>
            </AppTable>

        </div>
    </AdminLayout>
</template>
