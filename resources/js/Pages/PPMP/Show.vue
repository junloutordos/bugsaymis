<script setup>
import { ref, computed, watch } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
    PlusIcon, PencilSquareIcon, TrashIcon, CheckCircleIcon,
    ArrowUturnLeftIcon, DocumentArrowDownIcon, ShieldCheckIcon,
    XMarkIcon, MagnifyingGlassIcon, BuildingStorefrontIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    ppmp:        Object,
    items:       Array,
    summary:     Array,
    grandTotal:  Number,
    categories:  Object,
    methods:     Object,
    fundSources: Object,
    quarters:    Array,
    canEdit:     Boolean,
    canSubmit:   Boolean,
    canBacReview: Boolean,
    canApprove:  Boolean,
    canExport:   Boolean,
    utilization: Object,
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const months      = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']
const monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

const formatPeso = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ── Part I / Part II split ────────────────────────────────────────────────
// catalogue_part is injected by the controller (ppmp_catalogue.part)
// Legacy items pre-catalogue have catalogue_part=null but are Part I if procurement_method=agency_to_agency
const part1Items = computed(() => props.items.filter(i =>
    i.catalogue_part === 1 ||
    (i.catalogue_part !== 2 && i.procurement_method === 'agency_to_agency')
))
const part2Items = computed(() => props.items.filter(i => !part1Items.value.some(p => p.id === i.id)))

// Group Part II items by category (preserving order: goods → infrastructure → consulting)
const CATEGORY_ORDER = ['goods', 'infrastructure', 'consulting_services']
const part2ByCategory = computed(() => {
    return CATEGORY_ORDER
        .map(cat => ({ cat, label: props.categories[cat] ?? cat, items: part2Items.value.filter(i => i.category === cat) }))
        .filter(g => g.items.length > 0)
})

const part1Total = computed(() => part1Items.value.reduce((s, i) => s + Number(i.total_cost), 0))
const part2Total = computed(() => part2Items.value.reduce((s, i) => s + Number(i.total_cost), 0))

// Effective unit cost: Part II catalogue items use office_unit_cost; others use unit_cost
const effectiveUnitCost = (item) => Number(item.office_unit_cost) > 0 ? Number(item.office_unit_cost) : Number(item.unit_cost)

// Fund-source breakdown across all items
const fundSourceTotals = computed(() => {
    const map = {}
    props.items.forEach(i => {
        const fs = (i.fund_source ?? 'mooe').toLowerCase()
        map[fs] = (map[fs] ?? 0) + Number(i.total_cost)
    })
    return map
})

// ── Status helpers ────────────────────────────────────────────────────────
const statusColors = {
    draft:        'bg-slate-100 text-slate-700',
    pending_bac:  'bg-purple-100 text-purple-700',
    submitted:    'bg-blue-100 text-blue-700',
    returned:     'bg-amber-100 text-amber-700',
    approved:     'bg-green-100 text-green-700',
    consolidated: 'bg-indigo-100 text-indigo-700',
}

// ── Part I Catalogue Picker Modal ─────────────────────────────────────────
const showCatModal     = ref(false)
const catSearch        = ref('')
const catResults       = ref([])
const catSearching     = ref(false)
const catSelectedItems = ref([])   // { catalogue_id, stock_number, description, unit, unit_cost }

const searchCatalogue = async () => {
    catSearching.value = true
    try {
        const { data } = await axios.get(route('ppmp.catalogue.search'), {
            params: { fiscal_year: props.ppmp.fiscal_year, q: catSearch.value, part: 1 },
        })
        catResults.value = data
    } catch {
        catResults.value = []
    }
    catSearching.value = false
}

watch(catSearch, (val) => {
    if (val.length >= 2 || val === '') searchCatalogue()
})

const openCatModal = () => {
    catSearch.value = ''
    catResults.value = []
    catSelectedItems.value = []
    searchCatalogue()
    showCatModal.value = true
}

const isSelectedInCat = (item) => catSelectedItems.value.some(s => s.catalogue_id === item.id)
const toggleCatItem   = (item) => {
    if (isSelectedInCat(item)) {
        catSelectedItems.value = catSelectedItems.value.filter(s => s.catalogue_id !== item.id)
    } else {
        catSelectedItems.value.push({
            catalogue_id: item.id,
            stock_number: item.stock_number,
            description:  item.description,
            unit:         item.unit,
            unit_cost:    item.unit_cost,
            // schedule fields filled in after modal confirm
            fund_source:         'mooe',
            procurement_quarter: null,
            delivery_quarter:    null,
            remarks:             '',
            ...Object.fromEntries(months.map(m => [m, 0])),
        })
    }
}

// After selecting from catalogue, show schedule entry for each selected item
const showScheduleEntry = ref(false)
const proceedToSchedule = () => {
    if (!catSelectedItems.value.length) return
    showScheduleEntry.value = true
}

const part1Submitting = ref(false)
const savePart1Items = async () => {
    part1Submitting.value = true
    for (const sel of catSelectedItems.value) {
        await new Promise((resolve) => {
            router.post(route('ppmp.items.store', props.ppmp.id), sel, {
                preserveScroll: true,
                onFinish: resolve,
            })
        })
    }
    part1Submitting.value = false
    showCatModal.value    = false
    showScheduleEntry.value = false
}

// ── Part II Catalogue Picker Modal ────────────────────────────────────────
const showCat2Modal      = ref(false)
const cat2Search         = ref('')
const cat2Results        = ref([])
const cat2Searching      = ref(false)
const cat2SelectedItems  = ref([])   // { catalogue_id, stock_number, description, unit, office_unit_cost, ... }

const searchCatalogue2 = async () => {
    cat2Searching.value = true
    try {
        const { data } = await axios.get(route('ppmp.catalogue.search'), {
            params: { fiscal_year: props.ppmp.fiscal_year, q: cat2Search.value, part: 2 },
        })
        cat2Results.value = data
    } catch {
        cat2Results.value = []
    }
    cat2Searching.value = false
}

watch(cat2Search, (val) => {
    if (val.length >= 2 || val === '') searchCatalogue2()
})

const openCat2Modal = () => {
    cat2Search.value = ''
    cat2Results.value = []
    cat2SelectedItems.value = []
    searchCatalogue2()
    showCat2Modal.value = true
}

const isSelectedInCat2  = (item) => cat2SelectedItems.value.some(s => s.catalogue_id === item.id)
const toggleCat2Item    = (item) => {
    if (isSelectedInCat2(item)) {
        cat2SelectedItems.value = cat2SelectedItems.value.filter(s => s.catalogue_id !== item.id)
    } else {
        cat2SelectedItems.value.push({
            catalogue_id:        item.id,
            stock_number:        item.stock_number,
            description:         item.description,
            unit:                item.unit,
            office_unit_cost:    '',
            procurement_method:  'competitive_bidding',
            fund_source:         'mooe',
            price_source:        '',
            price_validity_date: '',
            procurement_quarter: null,
            delivery_quarter:    null,
            remarks:             '',
            ...Object.fromEntries(months.map(m => [m, 0])),
        })
    }
}

const showSchedule2Entry  = ref(false)
const proceedToSchedule2  = () => {
    if (!cat2SelectedItems.value.length) return
    showSchedule2Entry.value = true
}

const part2CatSubmitting = ref(false)
const savePart2CatItems = async () => {
    part2CatSubmitting.value = true
    for (const sel of cat2SelectedItems.value) {
        await new Promise((resolve) => {
            router.post(route('ppmp.items.store', props.ppmp.id), sel, {
                preserveScroll: true,
                onFinish: resolve,
            })
        })
    }
    part2CatSubmitting.value = false
    showCat2Modal.value      = false
    showSchedule2Entry.value = false
}

// ── Part II Add/Edit Forms ────────────────────────────────────────────────
const showAddForm = ref(false)
const itemForm    = useForm({
    code: '', description: '', unit: '', category: 'goods', unit_cost: '',
    jan: 0, feb: 0, mar: 0, apr: 0, may: 0, jun: 0,
    jul: 0, aug: 0, sep: 0, oct: 0, nov: 0, dec: 0,
    procurement_method: 'competitive_bidding',
    remarks: '', fund_source: 'mooe',
    procurement_quarter: null, delivery_quarter: null,
    price_source: '', price_validity_date: '',
})

const itemTotalQty  = computed(() => months.reduce((s, m) => s + (Number(itemForm[m]) || 0), 0))
const itemTotalCost = computed(() => itemTotalQty.value * (Number(itemForm.unit_cost) || 0))

const resetItemForm = () => {
    itemForm.reset()
    itemForm.category = 'goods'
    itemForm.procurement_method = 'competitive_bidding'
    itemForm.fund_source = 'mooe'
    itemForm.procurement_quarter = null
    itemForm.delivery_quarter    = null
    showAddForm.value = false
}

const saveItem = () => {
    itemForm.post(route('ppmp.items.store', props.ppmp.id), {
        preserveScroll: true,
        onSuccess: () => resetItemForm(),
    })
}

// ── Edit Modal (handles both Part I and Part II) ──────────────────────────
const showEditModal = ref(false)
const editingItem   = ref(null)
const editForm      = useForm({
    // Part I-only fields
    fund_source: 'mooe', procurement_quarter: null, delivery_quarter: null, remarks: '',
    jan: 0, feb: 0, mar: 0, apr: 0, may: 0, jun: 0,
    jul: 0, aug: 0, sep: 0, oct: 0, nov: 0, dec: 0,
    // Part II catalogue extra field
    office_unit_cost: '',
    // Manual Part II extra fields
    code: '', description: '', unit: '', category: 'goods', unit_cost: '',
    procurement_method: 'competitive_bidding', price_source: '', price_validity_date: '',
})

const editTotalQty = computed(() => months.reduce((s, m) => s + (Number(editForm[m]) || 0), 0))

const startEdit = (item) => {
    editingItem.value = item
    Object.assign(editForm, {
        fund_source:         item.fund_source || 'mooe',
        procurement_quarter: item.procurement_quarter || null,
        delivery_quarter:    item.delivery_quarter    || null,
        remarks:             item.remarks || '',
        office_unit_cost:    item.office_unit_cost || '',
        code:                item.code || '',
        description:         item.description,
        unit:                item.unit,
        category:            item.category,
        unit_cost:           item.unit_cost,
        procurement_method:  item.procurement_method,
        price_source:        item.price_source || '',
        price_validity_date: item.price_validity_date || '',
    })
    months.forEach(m => editForm[m] = item[m] ?? 0)
    showEditModal.value = true
}

const cancelEdit = () => { showEditModal.value = false; editingItem.value = null }

const saveEdit = () => {
    editForm.put(route('ppmp.items.update', [props.ppmp.id, editingItem.value.id]), {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    })
}

const deleteItem = (item) => {
    if (!confirm(`Remove "${item.description}"?`)) return
    router.delete(route('ppmp.items.destroy', [props.ppmp.id, item.id]), { preserveScroll: true })
}

// ── Quick-fill distribute helper ──────────────────────────────────────────
const makeDistributor = (form) => {
    const total = ref(0)
    const mode  = ref('monthly')
    const run = () => {
        const qty = Number(total.value) || 0
        if (qty <= 0) return
        const map = { monthly: months, quarterly: ['jan','apr','jul','oct'], semi: ['jan','jul'] }
        const sel = map[mode.value]
        months.forEach(m => form[m] = 0)
        const per = Math.floor(qty / sel.length)
        const rem = qty % sel.length
        sel.forEach((m, i) => { form[m] = per + (i === sel.length - 1 ? rem : 0) })
    }
    return { total, mode, run }
}
const addDist  = makeDistributor(itemForm)
const editDist = makeDistributor(editForm)

// ── Validation ────────────────────────────────────────────────────────────
const validationResult = ref(null)
const validating       = ref(false)

const runValidation = async () => {
    validating.value = true
    try {
        const { data } = await axios.post(route('ppmp.validate', props.ppmp.id))
        validationResult.value = data
    } catch {
        validationResult.value = null
    }
    validating.value = false
}

const itemHasError   = (id) => validationResult.value?.errors?.some(e => e.item_id === id)
const itemHasWarning = (id) => validationResult.value?.warnings?.some(w => w.item_id === id)

// ── Workflow ──────────────────────────────────────────────────────────────
const submitPpmp  = () => { if (!confirm('Submit this PPMP for BAC review?')) return; router.post(route('ppmp.submit', props.ppmp.id), {}, { preserveScroll: true }) }
const approvePpmp = () => { if (!confirm('Approve this PPMP?'))              return; router.post(route('ppmp.approve', props.ppmp.id), {}, { preserveScroll: true }) }

const returnRemarks   = ref('')
const showReturnModal = ref(false)
const returnPpmp = () => {
    if (!returnRemarks.value.trim()) return
    router.post(route('ppmp.return', props.ppmp.id), { remarks: returnRemarks.value }, {
        preserveScroll: true,
        onSuccess: () => { showReturnModal.value = false; returnRemarks.value = '' },
    })
}

const showBacReturnModal = ref(false)
const bacRemarks         = ref('')
const bacEndorse = () => { if (!confirm('Endorse this PPMP to the approving authority?')) return; router.post(route('ppmp.bac_review', props.ppmp.id), { action: 'endorse' }, { preserveScroll: true }) }
const bacReturn  = () => {
    if (!bacRemarks.value.trim()) return
    router.post(route('ppmp.bac_review', props.ppmp.id), { action: 'return', remarks: bacRemarks.value }, {
        preserveScroll: true,
        onSuccess: () => { showBacReturnModal.value = false; bacRemarks.value = '' },
    })
}

// ── Utilization ───────────────────────────────────────────────────────────
const utilizationRate = computed(() => {
    if (!props.utilization || !props.grandTotal) return 0
    return props.grandTotal > 0 ? Math.min(100, (props.utilization.pr_total / props.grandTotal) * 100) : 0
})
</script>

<template>
    <Head :title="ppmp.ppmp_number" />
    <AdminLayout :title="ppmp.ppmp_number">

        <!-- Flash -->
        <div v-if="flash.success" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ flash.success }}</div>
        <div v-if="flash.error"   class="mb-4 rounded-lg bg-red-50   border border-red-200   px-4 py-3 text-sm text-red-800">{{ flash.error }}</div>

        <!-- Validation results -->
        <div v-if="validationResult?.errors?.length" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
            <h4 class="text-sm font-semibold text-red-800">Validation Errors ({{ validationResult.errors.length }})</h4>
            <ul class="mt-1 text-sm text-red-700 list-disc list-inside">
                <li v-for="(err, i) in validationResult.errors" :key="i">{{ err.message }}</li>
            </ul>
        </div>
        <div v-if="validationResult?.warnings?.length" class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
            <h4 class="text-sm font-semibold text-amber-800">Warnings ({{ validationResult.warnings.length }})</h4>
            <ul class="mt-1 text-sm text-amber-700 list-disc list-inside">
                <li v-for="(w, i) in validationResult.warnings" :key="i">{{ w.message }}</li>
            </ul>
        </div>

        <!-- Header card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-lg font-semibold text-slate-800">{{ ppmp.title }}</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                              :class="statusColors[ppmp.status] ?? 'bg-slate-100 text-slate-700'">
                            {{ ppmp.status?.replace('_', ' ') }}
                        </span>
                        <span v-if="ppmp.is_supplemental" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Supplemental</span>
                    </div>
                    <p class="text-sm text-slate-500">{{ ppmp.division?.division_name }} · FY {{ ppmp.fiscal_year }}</p>
                    <p class="text-sm text-slate-500">Prepared by: {{ ppmp.preparer?.name }}</p>
                    <p v-if="ppmp.parent_ppmp" class="text-sm text-slate-500">Parent PPMP: {{ ppmp.parent_ppmp.ppmp_number }}</p>
                    <p v-if="ppmp.remarks && ppmp.status === 'returned'" class="text-sm text-amber-700 mt-2">
                        <strong>Return remarks:</strong> {{ ppmp.remarks }}
                    </p>
                    <p v-if="ppmp.bac_remarks" class="text-sm text-purple-700 mt-1">
                        <strong>BAC remarks:</strong> {{ ppmp.bac_remarks }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="canEdit" @click="runValidation" :disabled="validating"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 hover:bg-slate-200 text-slate-700">
                        <ShieldCheckIcon class="w-4 h-4" /> Validate
                    </button>
                    <button v-if="canSubmit" @click="submitPpmp"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white">
                        Submit for BAC Review
                    </button>
                    <button v-if="canBacReview" @click="bacEndorse"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-purple-600 hover:bg-purple-700 text-white">
                        <CheckCircleIcon class="w-4 h-4" /> Endorse
                    </button>
                    <button v-if="canBacReview" @click="showBacReturnModal = true"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-amber-500 hover:bg-amber-600 text-white">
                        <ArrowUturnLeftIcon class="w-4 h-4" /> Return to Unit
                    </button>
                    <button v-if="canApprove" @click="approvePpmp"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-green-600 hover:bg-green-700 text-white">
                        <CheckCircleIcon class="w-4 h-4" /> Approve
                    </button>
                    <button v-if="canApprove" @click="showReturnModal = true"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-amber-500 hover:bg-amber-600 text-white">
                        <ArrowUturnLeftIcon class="w-4 h-4" /> Return
                    </button>
                    <a v-if="canExport" :href="route('ppmp.export.pdf', ppmp.id)"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 hover:bg-slate-200 text-slate-700">
                        <DocumentArrowDownIcon class="w-4 h-4" /> PDF
                    </a>
                    <a v-if="canExport" :href="route('ppmp.export.excel', ppmp.id)"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-emerald-100 hover:bg-emerald-200 text-emerald-800">
                        <DocumentArrowDownIcon class="w-4 h-4" /> APP-CSE Excel
                    </a>
                </div>
            </div>

            <!-- Category summary cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                <div v-for="s in summary" :key="s.category" class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ s.label }}</p>
                    <p class="text-lg font-bold text-slate-800">₱{{ formatPeso(s.subtotal) }}</p>
                    <p class="text-xs text-slate-500">{{ s.item_count }} item(s)</p>
                </div>
                <div class="bg-indigo-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wide">Grand Total (ABC)</p>
                    <p class="text-lg font-bold text-indigo-800">₱{{ formatPeso(grandTotal) }}</p>
                    <p class="text-xs text-indigo-500">{{ items.length }} item(s)</p>
                </div>
            </div>

            <!-- Fund-source breakdown -->
            <div v-if="Object.keys(fundSourceTotals).length" class="mt-3 flex flex-wrap gap-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide self-center">By Fund Source:</span>
                <span v-for="(amt, fs) in fundSourceTotals" :key="fs"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                    {{ fs.toUpperCase() }}: ₱{{ formatPeso(amt) }}
                </span>
            </div>

            <!-- Part I / Part II summary bar -->
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="bg-blue-50 rounded-lg p-3 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide">Part I — PS-DBM (Agency-to-Agency)</p>
                        <p class="text-base font-bold text-blue-800">₱{{ formatPeso(part1Total) }}</p>
                        <p class="text-xs text-blue-500">{{ part1Items.length }} item(s)</p>
                    </div>
                </div>
                <div class="bg-emerald-50 rounded-lg p-3 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Part II — Other Procurement</p>
                        <p class="text-base font-bold text-emerald-800">₱{{ formatPeso(part2Total) }}</p>
                        <p class="text-xs text-emerald-500">{{ part2Items.length }} item(s)</p>
                    </div>
                </div>
            </div>

            <!-- Utilization -->
            <div v-if="utilization" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Linked PRs</p>
                    <p class="text-lg font-bold text-slate-800">{{ utilization.pr_count }}</p>
                </div>
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">PR Total</p>
                    <p class="text-lg font-bold text-slate-800">₱{{ formatPeso(utilization.pr_total) }}</p>
                </div>
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Budget Utilization</p>
                    <p class="text-lg font-bold text-slate-800">{{ utilizationRate.toFixed(1) }}%</p>
                    <div class="mt-1 w-full bg-slate-200 rounded-full h-1.5">
                        <div class="bg-indigo-500 h-1.5 rounded-full transition-all" :style="{ width: utilizationRate + '%' }"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ PART I TABLE ════════════════════════════════════════════════════ -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-4">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-blue-50">
                <div>
                    <h3 class="text-sm font-semibold text-blue-800">Part I — Items from PS-DBM (Agency-to-Agency)</h3>
                    <p class="text-xs text-blue-600 mt-0.5">Common-Use Supplies and Equipment procured through Procurement Service – DBM at published catalogue prices.</p>
                </div>
                <button v-if="canEdit" @click="openCatModal"
                        class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
                    <BuildingStorefrontIcon class="w-4 h-4" /> Add from Catalogue
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1400px] w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-28">Stock No.</th>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-14">Unit</th>
                            <th v-for="ml in monthLabels" :key="ml" class="px-1 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-10">{{ ml }}</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-14">Total</th>
                            <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-24">Unit Cost</th>
                            <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-28">Total Cost</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-16">Fund</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-14">Proc. Q</th>
                            <th v-if="canEdit" class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-16">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in part1Items" :key="item.id"
                            :class="{
                                'border-l-4 border-red-400':   itemHasError(item.id),
                                'border-l-4 border-amber-400': !itemHasError(item.id) && itemHasWarning(item.id),
                            }">
                            <td class="px-2 py-1.5 font-mono text-xs text-slate-600">{{ item.code }}</td>
                            <td class="px-2 py-1.5 text-slate-700">{{ item.description }}</td>
                            <td class="px-2 py-1.5 text-slate-600">{{ item.unit }}</td>
                            <td v-for="m in months" :key="m" class="px-1 py-1.5 text-center text-xs text-slate-600">{{ item[m] > 0 ? item[m] : '—' }}</td>
                            <td class="px-2 py-1.5 text-center font-medium text-slate-700">{{ item.total_quantity }}</td>
                            <td class="px-2 py-1.5 text-right text-xs text-slate-700">₱{{ formatPeso(item.unit_cost) }}</td>
                            <td class="px-2 py-1.5 text-right font-medium text-xs text-slate-800">₱{{ formatPeso(item.total_cost) }}</td>
                            <td class="px-2 py-1.5 text-center text-xs">
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 uppercase">{{ item.fund_source ?? 'mooe' }}</span>
                            </td>
                            <td class="px-2 py-1.5 text-center text-xs text-slate-600">{{ item.procurement_quarter ? 'Q' + item.procurement_quarter : '—' }}</td>
                            <td v-if="canEdit" class="px-2 py-1.5 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="startEdit(item)" class="text-slate-400 hover:text-indigo-600"><PencilSquareIcon class="w-4 h-4" /></button>
                                    <button @click="deleteItem(item)" class="text-slate-400 hover:text-red-600"><TrashIcon class="w-4 h-4" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!part1Items.length">
                            <td :colspan="canEdit ? 20 : 19" class="px-4 py-6 text-center text-slate-400 text-sm">
                                No PS-DBM items yet. Click "Add from Catalogue" to add Part I items.
                            </td>
                        </tr>
                        <tr v-if="part1Items.length" class="bg-blue-50">
                            <td colspan="13" class="px-2 py-1.5 text-right text-xs font-semibold text-blue-700">Part I Subtotal</td>
                            <td class="px-2 py-1.5 text-right text-xs font-semibold text-blue-800" colspan="2">₱{{ formatPeso(part1Total) }}</td>
                            <td :colspan="canEdit ? 5 : 4"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ PART II TABLE ═══════════════════════════════════════════════════ -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-4">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-emerald-50">
                <div>
                    <h3 class="text-sm font-semibold text-emerald-800">Part II — Other Procurement Items</h3>
                    <p class="text-xs text-emerald-600 mt-0.5">Items not available from PS-DBM. Procured via Competitive Bidding, Shopping, SVP, etc.</p>
                </div>
                <div v-if="canEdit" class="flex items-center gap-2">
                    <button @click="openCat2Modal"
                            class="inline-flex items-center gap-1.5 bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
                        <BuildingStorefrontIcon class="w-4 h-4" /> Add from List (Part II)
                    </button>
                    <button v-if="!showAddForm" @click="showAddForm = true"
                            class="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
                        <PlusIcon class="w-4 h-4" /> Add Custom Item
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1600px] w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-16">Code</th>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-14">Unit</th>
                            <th v-for="ml in monthLabels" :key="ml" class="px-1 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-10">{{ ml }}</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-14">Total</th>
                            <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-20">Unit Cost</th>
                            <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-24">Total Cost</th>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-28">Method</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-16">Fund</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-14">Proc. Q</th>
                            <th v-if="canEdit" class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-16">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="group in part2ByCategory" :key="group.cat">
                            <!-- Category header -->
                            <tr>
                                <td :colspan="canEdit ? 22 : 21" class="px-2 py-2 bg-slate-100 font-semibold text-xs text-slate-700 uppercase tracking-wide">
                                    {{ group.label }}
                                </td>
                            </tr>
                            <tr v-for="item in group.items" :key="item.id"
                                :class="{
                                    'border-l-4 border-red-400':   itemHasError(item.id),
                                    'border-l-4 border-amber-400': !itemHasError(item.id) && itemHasWarning(item.id),
                                }">
                                <td class="px-2 py-1.5 text-xs text-slate-600">{{ item.code }}</td>
                                <td class="px-2 py-1.5 text-slate-700">{{ item.description }}</td>
                                <td class="px-2 py-1.5 text-slate-600">{{ item.unit }}</td>
                                <td v-for="m in months" :key="m" class="px-1 py-1.5 text-center text-xs text-slate-600">{{ item[m] > 0 ? item[m] : '—' }}</td>
                                <td class="px-2 py-1.5 text-center font-medium text-slate-700">{{ item.total_quantity }}</td>
                                <td class="px-2 py-1.5 text-right text-xs text-slate-700">
                                    ₱{{ formatPeso(effectiveUnitCost(item)) }}
                                    <span v-if="item.catalogue_part === 2" class="block text-[9px] text-emerald-600 font-medium">office price</span>
                                </td>
                                <td class="px-2 py-1.5 text-right font-medium text-xs text-slate-800">₱{{ formatPeso(item.total_cost) }}</td>
                                <td class="px-2 py-1.5 text-xs text-slate-600">{{ methods[item.procurement_method] || item.procurement_method }}</td>
                                <td class="px-2 py-1.5 text-center text-xs">
                                    <span v-if="item.fund_source" class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 uppercase">{{ item.fund_source }}</span>
                                </td>
                                <td class="px-2 py-1.5 text-center text-xs text-slate-600">{{ item.procurement_quarter ? 'Q' + item.procurement_quarter : '—' }}</td>
                                <td v-if="canEdit" class="px-2 py-1.5 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="startEdit(item)" class="text-slate-400 hover:text-indigo-600"><PencilSquareIcon class="w-4 h-4" /></button>
                                        <button @click="deleteItem(item)" class="text-slate-400 hover:text-red-600"><TrashIcon class="w-4 h-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!part2Items.length">
                            <td :colspan="canEdit ? 22 : 21" class="px-4 py-6 text-center text-slate-400 text-sm">
                                No Part II items yet. Click "Add Item" to add items procured through other modes.
                            </td>
                        </tr>
                        <tr v-if="part2Items.length" class="bg-emerald-50">
                            <td colspan="14" class="px-2 py-1.5 text-right text-xs font-semibold text-emerald-700">Part II Subtotal</td>
                            <td class="px-2 py-1.5 text-right text-xs font-semibold text-emerald-800" colspan="2">₱{{ formatPeso(part2Total) }}</td>
                            <td :colspan="canEdit ? 6 : 5"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Part II Add Form -->
        <div v-if="showAddForm && canEdit" class="mt-2 mb-4 bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Add Part II Item</h3>
            <form @submit.prevent="saveItem" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Code</label>
                        <input v-model="itemForm.code" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Description *</label>
                        <input v-model="itemForm.description" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p v-if="itemForm.errors.description" class="mt-1 text-xs text-red-600">{{ itemForm.errors.description }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Unit *</label>
                        <input v-model="itemForm.unit" placeholder="e.g., ream, unit, lot" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Category *</label>
                        <select v-model="itemForm.category" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Unit Cost (₱) *</label>
                        <input v-model="itemForm.unit_cost" type="number" step="0.01" min="0.01" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Mode of Procurement *</label>
                        <select v-model="itemForm.procurement_method" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option v-for="(label, key) in methods" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Fund Source *</label>
                        <select v-model="itemForm.fund_source" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option v-for="(label, key) in fundSources" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Procurement Quarter</label>
                        <select v-model="itemForm.procurement_quarter" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option :value="null">— Any —</option>
                            <option v-for="(label, value) in quarters" :key="value" :value="Number(value)">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Delivery Quarter</label>
                        <select v-model="itemForm.delivery_quarter" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option :value="null">— Any —</option>
                            <option v-for="(label, value) in quarters" :key="value" :value="Number(value)">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Price Source</label>
                        <input v-model="itemForm.price_source" placeholder="PhilGEPS, Canvass, etc." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Price Validity Date</label>
                        <input v-model="itemForm.price_validity_date" type="date" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>
                <!-- Quick-fill -->
                <div class="flex items-end gap-2 bg-slate-50 rounded-lg p-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Quick fill: Total Qty</label>
                        <input v-model.number="addDist.total.value" type="number" min="0" class="w-24 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <select v-model="addDist.mode.value" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="monthly">Monthly (12)</option>
                        <option value="quarterly">Quarterly (4)</option>
                        <option value="semi">Semi-Annual (2)</option>
                    </select>
                    <button type="button" @click="addDist.run()" class="px-3 py-2 rounded-lg text-sm font-medium bg-slate-200 hover:bg-slate-300 text-slate-700">Distribute</button>
                </div>
                <!-- Monthly quantities -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Monthly Quantities</label>
                    <div class="grid grid-cols-6 sm:grid-cols-12 gap-2">
                        <div v-for="(ml, mi) in monthLabels" :key="mi" class="text-center">
                            <span class="block text-xs text-slate-500 mb-0.5">{{ ml }}</span>
                            <input v-model.number="itemForm[months[mi]]" type="number" min="0"
                                   class="w-full rounded border border-slate-200 bg-white px-1 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div class="mt-2 flex gap-4 text-sm">
                        <span class="text-slate-600">Total Qty: <strong>{{ itemTotalQty }}</strong></span>
                        <span class="text-slate-600">Total Cost: <strong>₱{{ formatPeso(itemTotalCost) }}</strong></span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Remarks / Justification</label>
                    <textarea v-model="itemForm.remarks" rows="2" placeholder="Required for Direct Contracting, Emergency, etc." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="resetItemForm" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Cancel</button>
                    <button type="submit" :disabled="itemForm.processing" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">Save Item</button>
                </div>
            </form>
        </div>

        <!-- Status History -->
        <div v-if="ppmp.status_history?.length" class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Status History</h3>
            <div class="space-y-2">
                <div v-for="h in ppmp.status_history" :key="h.id" class="flex items-start gap-3 text-sm">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                          :class="statusColors[h.to_status] ?? 'bg-slate-100 text-slate-700'">
                        {{ h.to_status?.replace('_', ' ') }}
                    </span>
                    <div>
                        <span class="text-slate-700">{{ h.actor?.name }}</span>
                        <span class="text-slate-400 ml-2">{{ new Date(h.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                        <p v-if="h.remarks" class="text-slate-500 text-xs mt-0.5">{{ h.remarks }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ PART I CATALOGUE PICKER MODAL ══════════════════════════════════ -->
        <div v-if="showCatModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Add from PS-DBM Catalogue — Part I</h3>
                        <p class="text-xs text-slate-500 mt-0.5">FY {{ ppmp.fiscal_year }} · Agency-to-Agency · Prices from PS-DBM price list</p>
                    </div>
                    <button @click="showCatModal = false; showScheduleEntry = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400"><XMarkIcon class="w-5 h-5" /></button>
                </div>

                <!-- Step 1: Select items -->
                <div v-if="!showScheduleEntry" class="flex flex-col flex-1 overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100">
                        <div class="relative">
                            <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input v-model="catSearch" placeholder="Search by stock number or description…"
                                   class="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <p v-if="catSelectedItems.length" class="text-xs text-indigo-600 mt-2 font-medium">{{ catSelectedItems.length }} item(s) selected</p>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div v-if="catSearching" class="px-5 py-8 text-center text-sm text-slate-400">Searching…</div>
                        <table v-else class="w-full text-sm">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 w-10"></th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-32">Stock No.</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-16">Unit</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-28">Unit Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in catResults" :key="item.id"
                                    @click="toggleCatItem(item)"
                                    class="cursor-pointer hover:bg-indigo-50 transition-colors"
                                    :class="{ 'bg-indigo-50': isSelectedInCat(item) }">
                                    <td class="px-3 py-2 text-center">
                                        <input type="checkbox" :checked="isSelectedInCat(item)"
                                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" readonly />
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ item.stock_number }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ item.description }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ item.unit }}</td>
                                    <td class="px-3 py-2 text-right text-slate-700">₱{{ formatPeso(item.unit_cost) }}</td>
                                </tr>
                                <tr v-if="!catResults.length">
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                                        {{ catSearch ? 'No items match your search.' : 'No catalogue loaded for this fiscal year. Ask the budget officer to upload the PS-DBM price list.' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-5 py-4 border-t border-slate-100 flex justify-end gap-2">
                        <button @click="showCatModal = false" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Cancel</button>
                        <button @click="proceedToSchedule" :disabled="!catSelectedItems.length"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                            Next: Set Schedule ({{ catSelectedItems.length }})
                        </button>
                    </div>
                </div>

                <!-- Step 2: Set monthly schedule per selected item -->
                <div v-if="showScheduleEntry" class="flex flex-col flex-1 overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100">
                        <p class="text-sm text-slate-600">Set the monthly quantities and fund source for each selected item. Unit cost is from the PS-DBM catalogue and cannot be changed.</p>
                    </div>
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                        <div v-for="sel in catSelectedItems" :key="sel.catalogue_id" class="border border-slate-200 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <p class="font-medium text-slate-800 text-sm">{{ sel.description }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ sel.stock_number }} · {{ sel.unit }} · <strong>₱{{ formatPeso(sel.unit_cost) }}</strong> (PS-DBM published price)</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-6 sm:grid-cols-12 gap-1.5 mb-3">
                                <div v-for="(ml, mi) in monthLabels" :key="mi" class="text-center">
                                    <span class="block text-xs text-slate-400 mb-0.5">{{ ml }}</span>
                                    <input v-model.number="sel[months[mi]]" type="number" min="0"
                                           class="w-full rounded border border-slate-200 bg-white px-1 py-1 text-xs text-center focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Fund Source *</label>
                                    <select v-model="sel.fund_source" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option v-for="(label, key) in fundSources" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Proc. Quarter</label>
                                    <select v-model="sel.procurement_quarter" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option :value="null">— Any —</option>
                                        <option v-for="(label, value) in quarters" :key="value" :value="Number(value)">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Del. Quarter</label>
                                    <select v-model="sel.delivery_quarter" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option :value="null">— Any —</option>
                                        <option v-for="(label, value) in quarters" :key="value" :value="Number(value)">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Remarks</label>
                                    <input v-model="sel.remarks" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-4 border-t border-slate-100 flex justify-end gap-2">
                        <button @click="showScheduleEntry = false" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Back</button>
                        <button @click="savePart1Items" :disabled="part1Submitting"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                            {{ part1Submitting ? 'Saving…' : 'Add to PPMP' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ PART II CATALOGUE PICKER MODAL ════════════════════════════════ -->
        <div v-if="showCat2Modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Add from PS-DBM List — Part II</h3>
                        <p class="text-xs text-slate-500 mt-0.5">FY {{ ppmp.fiscal_year }} · Items not available at PS-DBM · Enter your canvassed price per item</p>
                    </div>
                    <button @click="showCat2Modal = false; showSchedule2Entry = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400"><XMarkIcon class="w-5 h-5" /></button>
                </div>

                <!-- Step 1: Select items -->
                <div v-if="!showSchedule2Entry" class="flex flex-col flex-1 overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100">
                        <div class="relative">
                            <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input v-model="cat2Search" placeholder="Search Part II items by stock number or description…"
                                   class="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                        </div>
                        <p v-if="cat2SelectedItems.length" class="text-xs text-emerald-600 mt-2 font-medium">{{ cat2SelectedItems.length }} item(s) selected</p>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div v-if="cat2Searching" class="px-5 py-8 text-center text-sm text-slate-400">Searching…</div>
                        <table v-else class="w-full text-sm">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 w-10"></th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-32">Stock No.</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-16">Unit</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-28">Category</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in cat2Results" :key="item.id"
                                    @click="toggleCat2Item(item)"
                                    class="cursor-pointer hover:bg-emerald-50 transition-colors"
                                    :class="{ 'bg-emerald-50': isSelectedInCat2(item) }">
                                    <td class="px-3 py-2 text-center">
                                        <input type="checkbox" :checked="isSelectedInCat2(item)"
                                               class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" readonly />
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ item.stock_number }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ item.description }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ item.unit }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-500">{{ item.category_group }}</td>
                                </tr>
                                <tr v-if="!cat2Results.length">
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                                        {{ cat2Search ? 'No items match your search.' : 'No Part II catalogue loaded for this fiscal year.' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-5 py-4 border-t border-slate-100 flex justify-end gap-2">
                        <button @click="showCat2Modal = false" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Cancel</button>
                        <button @click="proceedToSchedule2" :disabled="!cat2SelectedItems.length"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50">
                            Next: Set Price & Schedule ({{ cat2SelectedItems.length }})
                        </button>
                    </div>
                </div>

                <!-- Step 2: Set price + monthly schedule per selected item -->
                <div v-if="showSchedule2Entry" class="flex flex-col flex-1 overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100">
                        <p class="text-sm text-slate-600">Enter your canvassed price and monthly quantities for each item. These are Part II items — price is not fixed by PS-DBM.</p>
                    </div>
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                        <div v-for="sel in cat2SelectedItems" :key="sel.catalogue_id" class="border border-slate-200 rounded-lg p-4">
                            <div class="mb-3">
                                <p class="font-medium text-slate-800 text-sm">{{ sel.description }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ sel.stock_number }} · {{ sel.unit }}</p>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Office Unit Cost (₱) *</label>
                                    <input v-model.number="sel.office_unit_cost" type="number" step="0.01" min="0.01"
                                           class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Mode of Procurement *</label>
                                    <select v-model="sel.procurement_method" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                        <option v-for="(label, key) in methods" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Fund Source *</label>
                                    <select v-model="sel.fund_source" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                        <option v-for="(label, key) in fundSources" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Price Source</label>
                                    <input v-model="sel.price_source" placeholder="PhilGEPS, Canvass…"
                                           class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-6 sm:grid-cols-12 gap-1.5 mb-2">
                                <div v-for="(ml, mi) in monthLabels" :key="mi" class="text-center">
                                    <span class="block text-xs text-slate-400 mb-0.5">{{ ml }}</span>
                                    <input v-model.number="sel[months[mi]]" type="number" min="0"
                                           class="w-full rounded border border-slate-200 bg-white px-1 py-1 text-xs text-center focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Proc. Quarter</label>
                                    <select v-model="sel.procurement_quarter" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                        <option :value="null">— Any —</option>
                                        <option v-for="(label, value) in quarters" :key="value" :value="Number(value)">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Del. Quarter</label>
                                    <select v-model="sel.delivery_quarter" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                        <option :value="null">— Any —</option>
                                        <option v-for="(label, value) in quarters" :key="value" :value="Number(value)">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Remarks</label>
                                    <input v-model="sel.remarks" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-4 border-t border-slate-100 flex justify-end gap-2">
                        <button @click="showSchedule2Entry = false" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Back</button>
                        <button @click="savePart2CatItems" :disabled="part2CatSubmitting"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50">
                            {{ part2CatSubmitting ? 'Saving…' : 'Add to PPMP' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Item Modal (Part I: schedule only; Part II catalogue: price+schedule; Manual: full edit) -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">
                            {{ editingItem?.catalogue_part === 1 ? 'Edit PS-DBM Item Schedule' : editingItem?.catalogue_part === 2 ? 'Edit Part II Catalogue Item' : 'Edit Item' }}
                        </h3>
                        <p v-if="editingItem?.catalogue_id" class="text-xs text-slate-500 mt-0.5">Description and unit are from the catalogue and cannot be changed.</p>
                    </div>
                    <button @click="cancelEdit" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400"><XMarkIcon class="w-5 h-5" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <!-- Part I: locked info banner -->
                    <div v-if="editingItem?.catalogue_part === 1" class="bg-blue-50 rounded-lg p-3 text-sm">
                        <span class="font-medium text-blue-800">{{ editingItem.description }}</span>
                        <span class="text-blue-600 ml-2">{{ editingItem.code }} · {{ editingItem.unit }} · ₱{{ formatPeso(editingItem.unit_cost) }} (PS-DBM price, fixed)</span>
                    </div>

                    <!-- Part II catalogue: locked description + editable price + procurement method -->
                    <template v-if="editingItem?.catalogue_part === 2">
                        <div class="bg-emerald-50 rounded-lg p-3 text-sm">
                            <span class="font-medium text-emerald-800">{{ editingItem.description }}</span>
                            <span class="text-emerald-600 ml-2">{{ editingItem.code }} · {{ editingItem.unit }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Office Unit Cost (₱) *</label>
                                <input v-model="editForm.office_unit_cost" type="number" step="0.01" min="0.01"
                                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Mode of Procurement *</label>
                                <select v-model="editForm.procurement_method" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="(label, key) in methods" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Price Source</label>
                                <input v-model="editForm.price_source" placeholder="PhilGEPS, Canvass, etc."
                                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </template>

                    <!-- Manual Part II: full fields -->
                    <template v-if="!editingItem?.catalogue_id">
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Code</label>
                                <input v-model="editForm.code" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-slate-600 mb-1">Description *</label>
                                <input v-model="editForm.description" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Unit *</label>
                                <input v-model="editForm.unit" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Category *</label>
                                <select v-model="editForm.category" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Unit Cost (₱) *</label>
                                <input v-model="editForm.unit_cost" type="number" step="0.01" min="0.01" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Mode of Procurement *</label>
                                <select v-model="editForm.procurement_method" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="(label, key) in methods" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Price Source</label>
                                <input v-model="editForm.price_source" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </template>

                    <!-- Shared: fund source + quarters -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Fund Source *</label>
                            <select v-model="editForm.fund_source" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option v-for="(label, key) in fundSources" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Procurement Quarter</label>
                            <select v-model="editForm.procurement_quarter" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option :value="null">— Any —</option>
                                <option v-for="(label, value) in quarters" :key="value" :value="Number(value)">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Delivery Quarter</label>
                            <select v-model="editForm.delivery_quarter" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option :value="null">— Any —</option>
                                <option v-for="(label, value) in quarters" :key="value" :value="Number(value)">{{ label }}</option>
                            </select>
                        </div>
                        <div v-if="!editingItem?.catalogue_id">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Price Validity Date</label>
                            <input v-model="editForm.price_validity_date" type="date" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div v-if="editingItem?.catalogue_part === 2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Price Validity Date</label>
                            <input v-model="editForm.price_validity_date" type="date" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <!-- Quick-fill distribute -->
                    <div class="flex items-end gap-2 bg-slate-50 rounded-lg p-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Quick fill: Total Qty</label>
                            <input v-model.number="editDist.total.value" type="number" min="0" class="w-24 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <select v-model="editDist.mode.value" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="monthly">Monthly (12)</option>
                            <option value="quarterly">Quarterly (4)</option>
                            <option value="semi">Semi-Annual (2)</option>
                        </select>
                        <button type="button" @click="editDist.run()" class="px-3 py-2 rounded-lg text-sm font-medium bg-slate-200 hover:bg-slate-300 text-slate-700">Distribute</button>
                    </div>

                    <!-- Monthly quantities -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Monthly Quantities</label>
                        <div class="grid grid-cols-6 sm:grid-cols-12 gap-2">
                            <div v-for="(ml, mi) in monthLabels" :key="mi" class="text-center">
                                <span class="block text-xs text-slate-500 mb-0.5">{{ ml }}</span>
                                <input v-model.number="editForm[months[mi]]" type="number" min="0"
                                       class="w-full rounded border border-slate-200 bg-white px-1 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                        <div class="mt-2 text-sm text-slate-600">Total Qty: <strong>{{ editTotalQty }}</strong></div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Remarks / Justification</label>
                        <textarea v-model="editForm.remarks" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-5 py-4 border-t border-slate-100">
                    <button @click="cancelEdit" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Cancel</button>
                    <button @click="saveEdit" :disabled="editForm.processing" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">Update</button>
                </div>
            </div>
        </div>

        <!-- Return Modal -->
        <div v-if="showReturnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold text-slate-800 mb-3">Return PPMP for Revision</h3>
                <textarea v-model="returnRemarks" rows="4" placeholder="Explain what needs to be corrected…"
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-3"></textarea>
                <div class="flex justify-end gap-2">
                    <button @click="showReturnModal = false" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Cancel</button>
                    <button @click="returnPpmp" :disabled="!returnRemarks.trim()" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-50">Return</button>
                </div>
            </div>
        </div>

        <!-- BAC Return Modal -->
        <div v-if="showBacReturnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold text-slate-800 mb-3">Return to End-User Unit</h3>
                <p class="text-sm text-slate-500 mb-3">State what compliance issues need to be resolved.</p>
                <textarea v-model="bacRemarks" rows="4" placeholder="Compliance issues, missing justifications…"
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-3"></textarea>
                <div class="flex justify-end gap-2">
                    <button @click="showBacReturnModal = false" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Cancel</button>
                    <button @click="bacReturn" :disabled="!bacRemarks.trim()" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-50">Return</button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
