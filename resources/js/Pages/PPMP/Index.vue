<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { PlusIcon, FunnelIcon, DocumentArrowDownIcon, BuildingStorefrontIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    ppmps:              Array,
    filters:            Object,
    fiscalYears:        Array,
    divisions:          Array,
    deadline:           String,
    canCreate:          Boolean,
    canReview:          Boolean,
    canDivisionReview:  Boolean,
    canManageCatalogue: Boolean,
})

const page = usePage()
const user = computed(() => page.props.auth.user)

const fiscalYear = ref(props.filters.fiscal_year || new Date().getFullYear())
const statusFilter = ref(props.filters.status || '')
const divisionFilter = ref(props.filters.division_id || '')

const search = ref('')

const filtered = computed(() => {
    if (!search.value) return props.ppmps
    const q = search.value.toLowerCase()
    return props.ppmps.filter(p =>
        (p.ppmp_number || '').toLowerCase().includes(q) ||
        (p.title || '').toLowerCase().includes(q) ||
        (p.division?.division_name || '').toLowerCase().includes(q)
    )
})

const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
    const start = (currentPage.value - 1) * PER_PAGE
    return filtered.value.slice(start, start + PER_PAGE)
})

watch([fiscalYear, statusFilter, divisionFilter], () => {
    currentPage.value = 1
    router.get(route('ppmp.index'), {
        fiscal_year: fiscalYear.value,
        status: statusFilter.value || undefined,
        division_id: divisionFilter.value || undefined,
    }, { preserveState: true, replace: true })
})

const statusColors = {
    draft:            'bg-slate-100 text-slate-700',
    pending_division: 'bg-orange-100 text-orange-700',
    pending_bac:      'bg-purple-100 text-purple-700',
    submitted:        'bg-blue-100 text-blue-700',
    returned:         'bg-amber-100 text-amber-700',
    approved:         'bg-green-100 text-green-700',
    consolidated:     'bg-indigo-100 text-indigo-700',
}

const statusLabel = (s) => ({
    draft:            'Draft',
    pending_division: 'Pending Division',
    pending_bac:      'Pending BAC',
    submitted:        'Submitted',
    returned:         'Returned',
    approved:         'Approved',
    consolidated:     'Consolidated',
}[s] ?? s)

const formatPeso = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const deadlinePassed = computed(() => {
    if (!props.deadline) return false
    return new Date(props.deadline) < new Date()
})
</script>

<template>
    <Head title="PPMP" />
    <AdminLayout title="Project Procurement Management Plan">
        <!-- Deadline banner -->
        <div v-if="deadline" class="mb-4 rounded-lg border px-4 py-3 text-sm"
             :class="deadlinePassed ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-blue-50 border-blue-200 text-blue-800'">
            <template v-if="deadlinePassed">
                ⚠ Submission deadline for FY {{ fiscalYear }} has passed. Contact the Procurement Office for late submissions.
            </template>
            <template v-else>
                📅 Submission deadline for FY {{ fiscalYear }}: {{ new Date(deadline).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}
            </template>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2">
                <select v-model="fiscalYear" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
                </select>
                <select v-model="statusFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="pending_division">Pending Division</option>
                    <option value="pending_bac">Pending BAC</option>
                    <option value="submitted">Submitted</option>
                    <option value="returned">Returned</option>
                    <option value="approved">Approved</option>
                    <option value="consolidated">Consolidated</option>
                </select>
                <select v-if="divisions.length" v-model="divisionFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Divisions</option>
                    <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.acronym || d.division_name }}</option>
                </select>
                <input v-model="search" type="text" placeholder="Search..."
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-48" />
            </div>
            <div class="flex items-center gap-2">
                <a v-if="canManageCatalogue" :href="route('ppmp.catalogue.index')"
                   class="inline-flex items-center gap-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">
                    <BuildingStorefrontIcon class="w-4 h-4" /> PS-DBM Catalogue
                </a>
                <a v-if="canCreate" :href="route('ppmp.create')"
                   class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <PlusIcon class="w-4 h-4" /> Create PPMP
                </a>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PPMP No.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Division</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Items</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Budget</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Prepared By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in displayed" :key="p.id" class="hover:bg-slate-50/60 cursor-pointer"
                            @click="router.visit(route('ppmp.show', p.id))">
                            <td class="px-4 py-3 font-medium text-indigo-600">{{ p.ppmp_number }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ p.title }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.division?.acronym || p.division?.division_name }}</td>
                            <td class="px-4 py-3 text-center text-slate-600">{{ p.item_count }}</td>
                            <td class="px-4 py-3 text-right text-slate-700 font-medium">₱{{ formatPeso(p.grand_total) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                      :class="statusColors[p.status] || 'bg-slate-100 text-slate-700'">
                                    {{ statusLabel(p.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ p.preparer?.name }}</td>
                        </tr>
                        <tr v-if="!displayed.length">
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">No PPMPs found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
                <span class="text-sm text-slate-500">{{ filtered.length }} PPMP(s)</span>
                <div class="flex gap-1">
                    <button v-for="pg in totalPages" :key="pg" @click="currentPage = pg"
                            class="px-3 py-1 rounded text-sm"
                            :class="pg === currentPage ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        {{ pg }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
