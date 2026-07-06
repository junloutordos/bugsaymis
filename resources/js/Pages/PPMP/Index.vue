<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { PlusIcon, BuildingStorefrontIcon, Square2StackIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    ppmps:                       Array,
    filters:                     Object,
    fiscalYears:                 Array,
    divisions:                   Array,
    deadline:                    String,
    canCreate:                   Boolean,
    canReview:                   Boolean,
    canDivisionReview:           Boolean,
    canPropertyOfficerReview:    Boolean,
    canManageCatalogue:          Boolean,
    approvableUnitPpmps:         Array,
    pendingDivisionPpmps:        Array,
    approvableDivisionPpmps:     Array,
    pendingPropertyOfficerPpmps: Array,
})

const page = usePage()
const user = computed(() => page.props.auth.user)

const fiscalYear     = ref(props.filters.fiscal_year || new Date().getFullYear())
const statusFilter   = ref(props.filters.status || '')
const divisionFilter = ref(props.filters.division_id || '')
const search         = ref('')

const filtered = computed(() => {
    if (!search.value) return props.ppmps
    const q = search.value.toLowerCase()
    return props.ppmps.filter(p =>
        (p.ppmp_number || '').toLowerCase().includes(q) ||
        (p.title || '').toLowerCase().includes(q) ||
        (p.office?.name || '').toLowerCase().includes(q) ||
        (p.division?.division_name || '').toLowerCase().includes(q)
    )
})

const PER_PAGE = 15
const currentPage  = ref(1)
const totalPages   = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed    = computed(() => {
    const start = (currentPage.value - 1) * PER_PAGE
    return filtered.value.slice(start, start + PER_PAGE)
})

watch([fiscalYear, statusFilter, divisionFilter], () => {
    currentPage.value = 1
    router.get(route('ppmp.index'), {
        fiscal_year: fiscalYear.value,
        status:      statusFilter.value || undefined,
        division_id: divisionFilter.value || undefined,
    }, { preserveState: true, replace: true })
})

// ── Division Consolidate Modal ────────────────────────────────────────────
const showDivisionConsolidateModal = ref(false)
const divisionConsolidateForm = ref({ fiscal_year: new Date().getFullYear(), title: '' })

function openConsolidateModal() {
    divisionConsolidateForm.value.fiscal_year = fiscalYear.value
    divisionConsolidateForm.value.title = ''
    showDivisionConsolidateModal.value = true
}

function submitDivisionConsolidate() {
    router.post(route('ppmp.division_consolidate'), divisionConsolidateForm.value, {
        onSuccess: () => { showDivisionConsolidateModal.value = false },
    })
}

// ── Property Consolidate Modal ────────────────────────────────────────────
const showPropertyConsolidateModal = ref(false)
const propertyConsolidateForm = ref({ fiscal_year: new Date().getFullYear(), title: '' })

function openPropertyConsolidateModal() {
    propertyConsolidateForm.value.fiscal_year = fiscalYear.value
    propertyConsolidateForm.value.title = ''
    showPropertyConsolidateModal.value = true
}

function submitPropertyConsolidate() {
    router.post(route('ppmp.property_consolidate'), propertyConsolidateForm.value, {
        onSuccess: () => { showPropertyConsolidateModal.value = false },
    })
}

const formatPeso = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ── Status helpers ────────────────────────────────────────────────────────
const statusLabel = (s) => ({
    draft:                      'Draft',
    pending_division:           'Pending Division',
    division_approved:          'Division Approved',
    pending_property_officer:   'Pending Property Officer',
    property_officer_approved:  'PO Approved',
    pending_budget_officer:     'Pending Budget Officer',
    pending_ocd:                'Pending OCD',
    pending_head:               'Pending Head of Agency',
    pending_bac:                'Pending BAC',
    submitted:                  'Submitted',
    returned:                   'Returned',
    approved:                   'OCD Approved',
    submitted_to_dbm:           'Submitted to DBM',
    consolidated:               'Consolidated',
}[s] ?? s)

// AppBadge only ships slate|indigo|blue|green|amber|orange|red|purple — map the
// full PPMP status set onto that palette (mirrors PPMP/Show.vue's statusBadgeColor).
function statusBadgeColor(status) {
    const map = {
        draft:                     'slate',
        pending_division:          'orange',
        division_approved:         'green',
        pending_property_officer:  'amber',
        property_officer_approved: 'green',
        pending_budget_officer:    'blue',
        pending_ocd:               'purple',
        pending_head:              'red',
        pending_bac:               'purple',
        submitted:                 'blue',
        returned:                  'amber',
        approved:                  'green',
        submitted_to_dbm:          'green',
        consolidated:              'indigo',
    }
    return map[status] ?? 'slate'
}

const deadlinePassed = computed(() => {
    if (!props.deadline) return false
    return new Date(props.deadline) < new Date()
})
</script>

<template>
    <Head title="PPMP" />
    <AdminLayout title="Project Procurement Management Plan">
      <div class="space-y-5">

        <AppPageHeader title="Project Procurement Management Plan"
                        subtitle="Unit → Division → Property Officer → Budget Officer → OCD approval workflow.">
            <template #actions>
                <AppButton v-if="canManageCatalogue" as="a" variant="secondary" :href="route('ppmp.catalogue.index')">
                    <BuildingStorefrontIcon class="w-4 h-4" /> PS-DBM Catalogue
                </AppButton>
                <AppButton v-if="canDivisionReview && approvableUnitPpmps?.length" variant="secondary" @click="openConsolidateModal">
                    <Square2StackIcon class="w-4 h-4" /> Create Division PPMP
                    <AppBadge color="indigo">{{ approvableUnitPpmps.length }}</AppBadge>
                </AppButton>
                <AppButton v-if="canPropertyOfficerReview && approvableDivisionPpmps?.length" variant="secondary" @click="openPropertyConsolidateModal">
                    <Square2StackIcon class="w-4 h-4" /> Create Property PPMP
                    <AppBadge color="indigo">{{ approvableDivisionPpmps.length }}</AppBadge>
                </AppButton>
                <AppButton v-if="canCreate" as="a" :href="route('ppmp.create')">
                    <PlusIcon class="w-4 h-4" /> Create PPMP
                </AppButton>
            </template>
        </AppPageHeader>

        <!-- Deadline banner -->
        <div v-if="deadline" class="rounded-lg border px-4 py-3 text-sm"
             :class="deadlinePassed ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-blue-50 border-blue-200 text-blue-800'">
            <template v-if="deadlinePassed">
                ⚠ Submission deadline for FY {{ fiscalYear }} has passed. Contact the Procurement Office for late submissions.
            </template>
            <template v-else>
                📅 Submission deadline for FY {{ fiscalYear }}: {{ new Date(deadline).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}
            </template>
        </div>

        <!-- Property Officer review queue banner -->
        <div v-if="canPropertyOfficerReview && pendingPropertyOfficerPpmps?.length"
             class="rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3">
            <div class="flex items-start gap-3">
                <ExclamationCircleIcon class="w-5 h-5 text-yellow-500 mt-0.5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-yellow-800">
                        {{ pendingPropertyOfficerPpmps.length }} division PPMP{{ pendingPropertyOfficerPpmps.length > 1 ? 's' : '' }} awaiting your review
                    </p>
                    <ul class="mt-1 space-y-0.5">
                        <li v-for="p in pendingPropertyOfficerPpmps" :key="p.id">
                            <a :href="route('ppmp.show', p.id)"
                               class="text-sm text-yellow-700 hover:text-yellow-900 underline">
                                {{ p.ppmp_number }} — {{ p.title }}
                                <span v-if="p.division_name" class="font-normal">({{ p.division_name }})</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- DC review queue banner (B2) -->
        <div v-if="canDivisionReview && pendingDivisionPpmps.length"
             class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3">
            <div class="flex items-start gap-3">
                <ExclamationCircleIcon class="w-5 h-5 text-orange-500 mt-0.5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-orange-800">
                        {{ pendingDivisionPpmps.length }} unit PPMP{{ pendingDivisionPpmps.length > 1 ? 's' : '' }} awaiting your review
                    </p>
                    <ul class="mt-1 space-y-0.5">
                        <li v-for="p in pendingDivisionPpmps" :key="p.id">
                            <a :href="route('ppmp.show', p.id)"
                               class="text-sm text-orange-700 hover:text-orange-900 underline">
                                {{ p.ppmp_number }} — {{ p.title }}
                                <span v-if="p.office_name" class="font-normal">({{ p.office_name }})</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <AppFilterBar>
            <select v-model="fiscalYear" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
            </select>
            <select v-model="statusFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="pending_division">Pending Division</option>
                <option value="division_approved">Division Approved</option>
                <option value="pending_property_officer">Pending Property Officer</option>
                <option value="pending_budget_officer">Pending Budget Officer</option>
                <option value="pending_head">Pending Head of Agency</option>
                <option value="pending_bac">Pending BAC</option>
                <option value="submitted">Submitted</option>
                <option value="returned">Returned</option>
                <option value="property_officer_approved">PO Approved</option>
                <option value="pending_budget_officer">Pending Budget Officer</option>
                <option value="pending_ocd">Pending OCD</option>
                <option value="approved">OCD Approved</option>
                <option value="submitted_to_dbm">Submitted to DBM</option>
                <option value="consolidated">Consolidated</option>
            </select>
            <select v-if="divisions.length" v-model="divisionFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Divisions</option>
                <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.acronym || d.division_name }}</option>
            </select>
            <input v-model="search" type="text" placeholder="Search..."
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-48" />
        </AppFilterBar>

        <!-- Table -->
        <AppTable :is-empty="!displayed.length" :skeleton-cols="7">
            <template #head>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PPMP No.</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit / Division</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Items</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Budget</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Prepared By</th>
                </tr>
            </template>

            <tr v-for="p in displayed" :key="p.id"
                class="cursor-pointer"
                :class="(canDivisionReview && p.status === 'pending_division') ? 'bg-orange-50/60 hover:bg-orange-50' : (canPropertyOfficerReview && p.status === 'pending_property_officer') ? 'bg-amber-50/60 hover:bg-amber-50' : 'hover:bg-slate-50/60'"
                @click="router.visit(route('ppmp.show', p.id))">
                <td class="px-4 py-3 font-medium text-indigo-600">
                    {{ p.ppmp_number }}
                    <AppBadge v-if="p.ppmp_type === 'division'" color="blue" class="ml-1">DIV</AppBadge>
                    <AppBadge v-if="p.ppmp_type === 'property'" color="purple" class="ml-1">PROP</AppBadge>
                </td>
                <td class="px-4 py-3 text-slate-700">{{ p.title }}</td>
                <td class="px-4 py-3 text-slate-600">
                    <span v-if="p.office" class="block font-medium text-slate-700">{{ p.office.name }}</span>
                    <span class="text-xs text-slate-500">{{ p.division?.acronym || p.division?.division_name }}</span>
                </td>
                <td class="px-4 py-3 text-center text-slate-600">{{ p.item_count }}</td>
                <td class="px-4 py-3 text-right text-slate-700 font-medium">₱{{ formatPeso(p.grand_total) }}</td>
                <td class="px-4 py-3 text-center">
                    <AppBadge :color="statusBadgeColor(p.status)">{{ statusLabel(p.status) }}</AppBadge>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ p.preparer?.name }}</td>
            </tr>

            <template #empty>
                <EmptyState title="No PPMPs found." />
            </template>

            <template #footer>
                <PaginationControl :current-page="currentPage" :total-pages="totalPages" :total="filtered.length"
                                    @prev="currentPage--" @next="currentPage++" @page="currentPage = $event" />
            </template>
        </AppTable>

        <!-- Division Consolidate Modal (B4) -->
        <AppModal :show="showDivisionConsolidateModal" title="Create Division PPMP"
                  subtitle="The following approved unit PPMPs will be merged into one Division PPMP."
                  size="lg" @close="showDivisionConsolidateModal = false">
            <!-- Unit PPMPs preview table -->
            <div class="max-h-52 overflow-y-auto mb-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold text-slate-500 uppercase">
                            <th class="pb-1 text-left">PPMP No.</th>
                            <th class="pb-1 text-left">Office / Unit</th>
                            <th class="pb-1 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in approvableUnitPpmps" :key="p.id">
                            <td class="py-1.5 font-medium text-indigo-600">{{ p.ppmp_number }}</td>
                            <td class="py-1.5 text-slate-700">{{ p.office_name || p.title }}</td>
                            <td class="py-1.5 text-right text-slate-700">₱{{ formatPeso(p.grand_total) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200">
                            <td colspan="2" class="pt-2 text-xs font-semibold text-slate-500 uppercase">Combined Total</td>
                            <td class="pt-2 text-right font-bold text-slate-800">
                                ₱{{ formatPeso(approvableUnitPpmps.reduce((s, p) => s + p.grand_total, 0)) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fiscal Year</label>
                    <select v-model="divisionConsolidateForm.fiscal_year"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                        <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
                    </select>
                </div>
                <AppInput v-model="divisionConsolidateForm.title" type="text" label="Division PPMP Title"
                          placeholder="e.g. SSD Division PPMP FY 2026" />
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="showDivisionConsolidateModal = false">Cancel</AppButton>
                <AppButton :disabled="!divisionConsolidateForm.title" @click="submitDivisionConsolidate">
                    Consolidate {{ approvableUnitPpmps.length }} Unit PPMP{{ approvableUnitPpmps.length > 1 ? 's' : '' }}
                </AppButton>
            </template>
        </AppModal>

        <!-- Property Consolidate Modal -->
        <AppModal :show="showPropertyConsolidateModal" title="Create Property PPMP"
                  subtitle="The following approved division PPMPs will be merged into one Property PPMP."
                  size="lg" @close="showPropertyConsolidateModal = false">
            <!-- Division PPMPs preview table -->
            <div class="max-h-52 overflow-y-auto mb-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold text-slate-500 uppercase">
                            <th class="pb-1 text-left">PPMP No.</th>
                            <th class="pb-1 text-left">Division</th>
                            <th class="pb-1 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in approvableDivisionPpmps" :key="p.id">
                            <td class="py-1.5 font-medium text-indigo-600">{{ p.ppmp_number }}</td>
                            <td class="py-1.5 text-slate-700">{{ p.division_name || p.title }}</td>
                            <td class="py-1.5 text-right text-slate-700">₱{{ formatPeso(p.grand_total) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200">
                            <td colspan="2" class="pt-2 text-xs font-semibold text-slate-500 uppercase">Combined Total</td>
                            <td class="pt-2 text-right font-bold text-slate-800">
                                ₱{{ formatPeso(approvableDivisionPpmps.reduce((s, p) => s + p.grand_total, 0)) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fiscal Year</label>
                    <select v-model="propertyConsolidateForm.fiscal_year"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                        <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
                    </select>
                </div>
                <AppInput v-model="propertyConsolidateForm.title" type="text" label="Property PPMP Title"
                          placeholder="e.g. PSHS-CRC Property PPMP/APP-CSE FY 2026" />
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="showPropertyConsolidateModal = false">Cancel</AppButton>
                <AppButton :disabled="!propertyConsolidateForm.title" @click="submitPropertyConsolidate">
                    Consolidate {{ approvableDivisionPpmps.length }} Division PPMP{{ approvableDivisionPpmps.length > 1 ? 's' : '' }}
                </AppButton>
            </template>
        </AppModal>

      </div>
    </AdminLayout>
</template>
