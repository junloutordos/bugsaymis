<script setup>
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

const statusClass = (status) => {
    if (status === 'awarded') return 'bg-emerald-100 text-emerald-700'
    if (status === 'open') return 'bg-blue-100 text-blue-700'
    if (status === 'closed') return 'bg-slate-100 text-slate-600'
    if (status === 'cancelled') return 'bg-red-100 text-red-700'
    return 'bg-amber-100 text-amber-700'
}
</script>

<template>
    <Head title="Request for Quotation" />
    <AdminLayout title="Request for Quotation">

        <div v-if="flash?.success" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ flash.success }}
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1">
                    <h2 class="text-sm font-semibold text-slate-800">Request for Quotation</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ filtered.length }} RFQ{{ filtered.length !== 1 ? 's' : '' }}</p>
                </div>
                <div class="relative w-full sm:w-64">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input v-model="search" placeholder="Search RFQ…" @input="currentPage = 1"
                        class="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">RFQ No.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PR Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Purpose</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Suppliers</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Awarded</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="rfq in displayed" :key="rfq.id" class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-mono text-sm font-medium text-slate-800">{{ rfq.rfq_number }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600 font-mono">{{ rfq.pr_no || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ rfq.purpose || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600 text-center">{{ rfq.supplier_count }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium', statusClass(rfq.status)]">
                                    {{ rfq.status_label }}
                                </span>
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
                        <tr v-if="!displayed.length">
                            <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-400">
                                {{ search ? 'No RFQs match your search.' : 'No RFQs yet. Create one from an approved Purchase Request.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="px-5 py-3 border-t border-slate-100 flex items-center justify-between">
                <p class="text-xs text-slate-500">Page {{ currentPage }} of {{ totalPages }}</p>
                <div class="flex gap-1">
                    <button @click="currentPage--" :disabled="currentPage === 1"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-40">Prev</button>
                    <button @click="currentPage++" :disabled="currentPage === totalPages"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
