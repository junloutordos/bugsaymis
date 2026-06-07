<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
    ArrowLeftIcon, PrinterIcon, PlusIcon, XMarkIcon,
    CheckIcon, DocumentArrowUpIcon, DocumentArrowDownIcon,
    PencilSquareIcon, TrophyIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    rfq: Object,
    matrix: { type: Array, default: () => [] },
    currentUser: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash)
const r = computed(() => props.rfq)
const perms = computed(() => props.currentUser?.permissions ?? {})

// ── Helpers ───────────────────────────────────────────────────────────────
const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
const formatCurrency = (v) =>
    Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const statusClass = (status) => {
    if (status === 'awarded') return 'bg-emerald-100 text-emerald-700'
    if (status === 'open') return 'bg-blue-100 text-blue-700'
    if (status === 'closed') return 'bg-slate-100 text-slate-600'
    if (status === 'cancelled') return 'bg-red-100 text-red-700'
    return 'bg-amber-100 text-amber-700'
}

// ── Add Supplier ──────────────────────────────────────────────────────────
const showAddSupplier = ref(false)
const supplierForm = ref({ supplier_name: '', supplier_address: '', supplier_tin: '', supplier_contact: '' })
const addingSupplier = ref(false)

const addSupplier = async () => {
    if (!supplierForm.value.supplier_name.trim() || addingSupplier.value) return
    addingSupplier.value = true
    try {
        await axios.post(route('rfq.suppliers.add', r.value.id), supplierForm.value)
        showAddSupplier.value = false
        supplierForm.value = { supplier_name: '', supplier_address: '', supplier_tin: '', supplier_contact: '' }
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to add supplier.')
    } finally { addingSupplier.value = false }
}

const removeSupplier = async (supplier) => {
    if (!confirm(`Remove ${supplier.supplier_name}?`)) return
    try {
        await axios.delete(route('rfq.suppliers.remove', { rfq: r.value.id, supplier: supplier.id }))
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to remove supplier.')
    }
}

// ── Open RFQ ──────────────────────────────────────────────────────────────
const openingRfq = ref(false)
const openRfq = async () => {
    if (!confirm('Mark this RFQ as Open and distribute to suppliers?')) return
    openingRfq.value = true
    try {
        await axios.post(route('rfq.open', r.value.id))
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Failed.')
    } finally { openingRfq.value = false }
}

// ── Mark Sent ─────────────────────────────────────────────────────────────
const markingSent = ref(null)
const markSent = async (supplier) => {
    markingSent.value = supplier.id
    try {
        await axios.post(route('rfq.suppliers.sent', { rfq: r.value.id, supplier: supplier.id }))
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Failed.')
    } finally { markingSent.value = null }
}

// ── Upload Quotation (base64) ─────────────────────────────────────────────
const uploadingFor = ref(null)
const uploadFile = (supplier) => {
    uploadingFor.value = supplier.id
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = '.pdf,.jpg,.jpeg,.png'
    input.onchange = async (e) => {
        const file = e.target.files[0]
        if (!file) { uploadingFor.value = null; return }
        const reader = new FileReader()
        reader.onload = async (ev) => {
            try {
                await axios.post(route('rfq.suppliers.upload', { rfq: r.value.id, supplier: supplier.id }), {
                    quotation_base64: ev.target.result,
                    filename: file.name,
                })
                router.reload({ preserveScroll: true })
            } catch (err) {
                alert(err.response?.data?.message || 'Upload failed.')
            } finally { uploadingFor.value = null }
        }
        reader.readAsDataURL(file)
    }
    input.oncancel = () => { uploadingFor.value = null }
    input.click()
}

// ── Enter Quotation Prices ────────────────────────────────────────────────
const quotationModal = ref(null)
const quotationRows = ref([])
const savingQuotations = ref(false)

const openQuotationModal = (supplier) => {
    quotationModal.value = supplier
    quotationRows.value = props.matrix.map(row => {
        const existing = row.quotations[supplier.id]
        return {
            rfq_item_id: row.id,
            description: row.description,
            unit: row.unit,
            quantity: row.quantity,
            abc: row.abc,
            unit_cost: existing?.unit_cost > 0 ? existing.unit_cost : '',
            remarks: '',
        }
    })
}

const saveQuotations = async () => {
    if (savingQuotations.value) return
    savingQuotations.value = true
    try {
        await axios.post(route('rfq.suppliers.quotations', { rfq: r.value.id, supplier: quotationModal.value.id }), {
            quotations: quotationRows.value.map(row => ({
                rfq_item_id: row.rfq_item_id,
                unit_cost: parseFloat(row.unit_cost) || 0,
                remarks: row.remarks || '',
            }))
        })
        quotationModal.value = null
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to save quotations.')
    } finally { savingQuotations.value = false }
}

// ── Close RFQ ─────────────────────────────────────────────────────────────
const closingRfq = ref(false)
const closeRfq = async () => {
    if (!confirm('Close this RFQ for evaluation?')) return
    closingRfq.value = true
    try {
        await axios.post(route('rfq.close', r.value.id))
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Failed.')
    } finally { closingRfq.value = false }
}

// ── Award ─────────────────────────────────────────────────────────────────
const awardSelections = ref({})
const awardingRfq = ref(false)

const initAwardSelections = () => {
    awardSelections.value = {}
    props.matrix.forEach(row => {
        const awardedSupplierId = Object.entries(row.quotations).find(([, q]) => q?.is_awarded)?.[0]
        awardSelections.value[row.id] = awardedSupplierId ? parseInt(awardedSupplierId) : null
    })
}

const awardRfq = async () => {
    if (!confirm('Save award selections? This will update the Abstract of Quotations.')) return
    awardingRfq.value = true
    try {
        const awards = Object.entries(awardSelections.value)
            .filter(([, sid]) => sid)
            .map(([itemId, supplierId]) => ({ rfq_item_id: parseInt(itemId), rfq_supplier_id: supplierId }))
        await axios.post(route('rfq.award', r.value.id), { awards })
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to save award.')
    } finally { awardingRfq.value = false }
}

// ── Generate POs ──────────────────────────────────────────────────────────
const generatingPos = ref(false)
const generatePos = async () => {
    if (!confirm('Generate Purchase Orders for all awarded suppliers?')) return
    generatingPos.value = true
    try {
        await axios.post(route('rfq.generate-pos', r.value.id))
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to generate POs.')
    } finally { generatingPos.value = false }
}

// ── Computed state flags ──────────────────────────────────────────────────
const isDraft   = computed(() => r.value.status === 'draft')
const isOpen    = computed(() => r.value.status === 'open')
const isClosed  = computed(() => r.value.status === 'closed')
const isAwarded = computed(() => r.value.status === 'awarded')
const hasAwardedItems = computed(() => props.matrix.some(row =>
    Object.values(row.quotations).some(q => q?.is_awarded)
))

// ── Lowest price helper for AoQ ───────────────────────────────────────────
const isLowest = (row, supplierId) => {
    const q = row.quotations[supplierId]
    if (!q?.unit_cost || !row.lowest_price) return false
    return Math.abs(q.unit_cost - row.lowest_price) < 0.005
}

// Initialize award selections on mount
initAwardSelections()
</script>

<template>
    <Head :title="`RFQ — ${rfq.rfq_number}`" />
    <AdminLayout :title="`RFQ — ${rfq.rfq_number}`">

        <!-- Back + Actions bar -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <a :href="route('rfq.index')"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
                <ArrowLeftIcon class="w-4 h-4" />
                Back to RFQ List
            </a>
            <div class="flex flex-wrap gap-2">
                <a :href="route('rfq.print', rfq.id)" target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 shadow-sm">
                    <PrinterIcon class="w-4 h-4" /> Print RFQ
                </a>
                <a v-if="!isDraft" :href="route('rfq.aoq', rfq.id)" target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 shadow-sm">
                    <PrinterIcon class="w-4 h-4" /> Print AoQ
                </a>
            </div>
        </div>

        <!-- Flash -->
        <div v-if="flash?.success" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ flash.success }}
        </div>
        <div v-if="flash?.error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ flash.error }}
        </div>

        <div class="space-y-5">

            <!-- Header card -->
            <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-bold text-slate-800 font-mono">{{ r.rfq_number }}</h2>
                            <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium', statusClass(r.status)]">
                                {{ r.status_label }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-500 mt-1">{{ r.pr?.purpose }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">PR: {{ r.pr?.pr_no }} &nbsp;·&nbsp; {{ r.pr?.division?.division_name }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="isDraft && perms.canCreate && (r.suppliers?.length >= 1)"
                            @click="openRfq" :disabled="openingRfq"
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                            Open for Quotations
                        </button>
                        <button v-if="isOpen && perms.canEvaluate"
                            @click="closeRfq" :disabled="closingRfq"
                            class="inline-flex items-center gap-2 bg-slate-600 hover:bg-slate-700 text-white px-3 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                            Close for Evaluation
                        </button>
                        <button v-if="isClosed && perms.canAward && hasAwardedItems"
                            @click="generatePos" :disabled="generatingPos"
                            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                            <TrophyIcon class="w-4 h-4" /> Generate POs
                        </button>
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-slate-400 text-xs">RFQ Date</dt>
                        <dd class="text-slate-700 font-medium">{{ formatDate(r.rfq_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400 text-xs">Validity</dt>
                        <dd class="text-slate-700 font-medium">{{ r.validity_days }} days</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400 text-xs">Delivery Place</dt>
                        <dd class="text-slate-700 font-medium">{{ r.delivery_place || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400 text-xs">Payment Terms</dt>
                        <dd class="text-slate-700 font-medium">{{ r.payment_terms || '—' }}</dd>
                    </div>
                    <div v-if="r.awarded_at">
                        <dt class="text-slate-400 text-xs">Awarded</dt>
                        <dd class="text-emerald-700 font-medium">{{ formatDate(r.awarded_at) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Items / AoQ Section -->
            <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        {{ isClosed || isAwarded ? 'Abstract of Quotations' : 'Items' }}
                    </h3>
                    <div class="flex gap-2 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="w-3 h-3 rounded bg-blue-100 border border-blue-300 inline-block"></span>Awarded
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-300 inline-block"></span>Lowest
                        </span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-8">#</th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-16">Unit</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide w-12">Qty</th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide w-24">ABC/unit</th>
                                <template v-for="sup in (r.suppliers || [])" :key="sup.id">
                                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide" colspan="2">
                                        {{ sup.supplier_name }}
                                    </th>
                                </template>
                                <th v-if="isClosed && perms.canAward" class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-32">Award To</th>
                                <th v-else-if="isAwarded" class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-32">Awarded To</th>
                            </tr>
                            <tr v-if="(r.suppliers || []).length" class="border-b border-slate-100 bg-slate-50/60">
                                <th colspan="5"></th>
                                <template v-for="sup in (r.suppliers || [])" :key="sup.id">
                                    <th class="px-3 py-1.5 text-right text-xs text-slate-400">Unit</th>
                                    <th class="px-3 py-1.5 text-right text-xs text-slate-400">Total</th>
                                </template>
                                <th v-if="isClosed && perms.canAward"></th>
                                <th v-else-if="isAwarded"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in matrix" :key="row.id" class="hover:bg-slate-50/50">
                                <td class="px-3 py-2.5 text-slate-500 text-center">{{ row.item_no }}</td>
                                <td class="px-3 py-2.5 text-slate-600">{{ row.unit }}</td>
                                <td class="px-3 py-2.5 text-slate-700 text-right">{{ row.quantity }}</td>
                                <td class="px-3 py-2.5 text-slate-700">{{ row.description }}</td>
                                <td class="px-3 py-2.5 text-slate-600 text-right font-mono text-xs">
                                    {{ row.abc > 0 ? formatCurrency(row.abc / row.quantity) : '—' }}
                                </td>
                                <template v-for="sup in (r.suppliers || [])" :key="sup.id">
                                    <td :class="['px-3 py-2.5 text-right font-mono text-xs',
                                        row.quotations[sup.id]?.is_awarded ? 'bg-blue-50 text-blue-800 font-semibold' :
                                        isLowest(row, sup.id) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600']">
                                        {{ row.quotations[sup.id]?.unit_cost > 0 ? formatCurrency(row.quotations[sup.id].unit_cost) : '—' }}
                                    </td>
                                    <td :class="['px-3 py-2.5 text-right font-mono text-xs',
                                        row.quotations[sup.id]?.is_awarded ? 'bg-blue-50 text-blue-800 font-semibold' :
                                        isLowest(row, sup.id) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600']">
                                        {{ row.quotations[sup.id]?.total_cost > 0 ? formatCurrency(row.quotations[sup.id].total_cost) : '—' }}
                                    </td>
                                </template>
                                <td v-if="isClosed && perms.canAward" class="px-3 py-2.5">
                                    <select v-model="awardSelections[row.id]"
                                        class="w-full rounded border border-slate-200 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white">
                                        <option :value="null">— none —</option>
                                        <option v-for="sup in (r.suppliers || [])" :key="sup.id"
                                            :value="sup.id"
                                            :disabled="!row.quotations[sup.id]?.unit_cost">
                                            {{ sup.supplier_name }}
                                        </option>
                                    </select>
                                </td>
                                <td v-else-if="isAwarded" class="px-3 py-2.5 text-xs text-slate-600 text-center">
                                    {{ (r.suppliers || []).find(s => row.quotations[s.id]?.is_awarded)?.supplier_name || '—' }}
                                </td>
                            </tr>
                            <tr v-if="!matrix.length">
                                <td :colspan="5 + (r.suppliers?.length || 0) * 2 + 1" class="px-4 py-8 text-center text-sm text-slate-400">
                                    No items.
                                </td>
                            </tr>
                        </tbody>
                        <!-- Supplier totals -->
                        <tfoot v-if="(r.suppliers || []).length" class="border-t-2 border-slate-200 bg-slate-50">
                            <tr>
                                <td colspan="5" class="px-3 py-2.5 text-xs font-semibold text-slate-600 text-right">Total Quoted:</td>
                                <template v-for="sup in (r.suppliers || [])" :key="sup.id">
                                    <td colspan="2" class="px-3 py-2.5 text-right text-xs font-semibold text-slate-700 font-mono">
                                        {{ sup.quotation_total > 0 ? '₱ ' + formatCurrency(sup.quotation_total) : '—' }}
                                    </td>
                                </template>
                                <td v-if="isClosed && perms.canAward" class="px-3 py-2.5 text-right">
                                    <button @click="awardRfq" :disabled="awardingRfq"
                                        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium disabled:opacity-60">
                                        <TrophyIcon class="w-3.5 h-3.5" />
                                        {{ awardingRfq ? 'Saving…' : 'Save Award' }}
                                    </button>
                                </td>
                                <td v-else-if="isAwarded"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Suppliers Section -->
            <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        Suppliers ({{ (r.suppliers || []).length }})
                    </h3>
                    <button v-if="isDraft && perms.canCreate" @click="showAddSupplier = true"
                        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
                        <PlusIcon class="w-4 h-4" /> Add Supplier
                    </button>
                </div>

                <div class="divide-y divide-slate-100">
                    <div v-for="sup in (r.suppliers || [])" :key="sup.id" class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800">{{ sup.supplier_name }}</p>
                                <p v-if="sup.supplier_address" class="text-xs text-slate-500 mt-0.5">{{ sup.supplier_address }}</p>
                                <div class="flex flex-wrap gap-3 mt-1.5 text-xs text-slate-500">
                                    <span v-if="sup.supplier_tin">TIN: {{ sup.supplier_tin }}</span>
                                    <span v-if="sup.supplier_contact">📞 {{ sup.supplier_contact }}</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <!-- Quotation total -->
                                <span v-if="sup.quotation_total > 0" class="text-sm font-mono font-medium text-slate-700">
                                    ₱ {{ formatCurrency(sup.quotation_total) }}
                                </span>
                                <!-- Award badge -->
                                <span v-if="sup.awarded_items_count > 0"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    <TrophyIcon class="w-3 h-3" /> {{ sup.awarded_items_count }} item{{ sup.awarded_items_count > 1 ? 's' : '' }}
                                </span>
                                <!-- NOA button (awarded) -->
                                <a v-if="isAwarded && sup.awarded_items_count > 0"
                                    :href="route('rfq.noa', { rfq: rfq.id, supplier: sup.id })" target="_blank"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 hover:bg-emerald-100">
                                    <PrinterIcon class="w-3.5 h-3.5" /> NOA
                                </a>
                                <!-- Sent badge / button -->
                                <span v-if="sup.sent_at" class="inline-flex items-center gap-1 text-xs text-slate-500">
                                    <CheckIcon class="w-3.5 h-3.5 text-emerald-500" /> Sent
                                </span>
                                <button v-else-if="isOpen && perms.canCreate"
                                    @click="markSent(sup)" :disabled="markingSent === sup.id"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-50">
                                    Mark Sent
                                </button>
                                <!-- Quotation file -->
                                <a v-if="sup.quotation_filename"
                                    :href="route('rfq.suppliers.download', { rfq: rfq.id, supplier: sup.id })"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50">
                                    <DocumentArrowDownIcon class="w-3.5 h-3.5" /> {{ sup.quotation_filename }}
                                </a>
                                <!-- Upload button -->
                                <button v-if="(isOpen || isClosed) && perms.canUpload"
                                    @click="uploadFile(sup)" :disabled="uploadingFor === sup.id"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-50">
                                    <DocumentArrowUpIcon class="w-3.5 h-3.5" />
                                    {{ uploadingFor === sup.id ? 'Uploading…' : 'Upload' }}
                                </button>
                                <!-- Enter prices button -->
                                <button v-if="(isOpen || isClosed) && perms.canEvaluate"
                                    @click="openQuotationModal(sup)"
                                    class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700 hover:bg-indigo-100">
                                    <PencilSquareIcon class="w-3.5 h-3.5" /> Enter Prices
                                </button>
                                <!-- Remove button (draft only) -->
                                <button v-if="isDraft && perms.canCreate"
                                    @click="removeSupplier(sup)"
                                    class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="!(r.suppliers || []).length" class="px-5 py-8 text-center text-sm text-slate-400">
                        No suppliers added yet.
                    </div>
                </div>
            </div>

        </div>

        <!-- Add Supplier Modal -->
        <div v-if="showAddSupplier" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800">Add Supplier</h3>
                    <button @click="showAddSupplier = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Supplier Name <span class="text-red-500">*</span></label>
                        <input v-model="supplierForm.supplier_name"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Business or vendor name" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Business Address</label>
                        <input v-model="supplierForm.supplier_address"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">TIN</label>
                            <input v-model="supplierForm.supplier_tin"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="000-000-000-000" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Contact No.</label>
                            <input v-model="supplierForm.supplier_contact"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="showAddSupplier = false" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button @click="addSupplier" :disabled="addingSupplier || !supplierForm.supplier_name.trim()"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                        {{ addingSupplier ? 'Adding…' : 'Add Supplier' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Enter Quotation Prices Modal -->
        <div v-if="quotationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white w-full max-w-2xl rounded-2xl shadow-xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
                    <h3 class="text-base font-semibold text-slate-800">Quotation Prices — {{ quotationModal.supplier_name }}</h3>
                    <button @click="quotationModal = null" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="overflow-y-auto flex-1 px-6 py-4">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200">
                                <th class="pb-2 text-left text-xs font-semibold text-slate-500 uppercase">Item</th>
                                <th class="pb-2 text-right text-xs font-semibold text-slate-500 uppercase w-16">Qty</th>
                                <th class="pb-2 text-right text-xs font-semibold text-slate-500 uppercase w-20">ABC/u</th>
                                <th class="pb-2 text-right text-xs font-semibold text-slate-500 uppercase w-28">Unit Cost <span class="text-red-400">*</span></th>
                                <th class="pb-2 text-right text-xs font-semibold text-slate-500 uppercase w-28">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in quotationRows" :key="row.rfq_item_id">
                                <td class="py-2.5 pr-3 text-slate-700 text-xs">{{ row.description }}</td>
                                <td class="py-2.5 text-right text-slate-500 text-xs">{{ row.quantity }}</td>
                                <td class="py-2.5 text-right text-slate-400 text-xs font-mono">
                                    {{ row.abc > 0 ? formatCurrency(row.abc / row.quantity) : '—' }}
                                </td>
                                <td class="py-2.5 text-right">
                                    <input v-model.number="row.unit_cost" type="number" step="0.01" min="0"
                                        class="w-full rounded border border-slate-200 px-2 py-1 text-right text-xs font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                                </td>
                                <td class="py-2.5 text-right text-xs font-mono text-slate-700">
                                    {{ row.unit_cost > 0 ? formatCurrency(Number(row.unit_cost) * row.quantity) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 shrink-0">
                    <button @click="quotationModal = null" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button @click="saveQuotations" :disabled="savingQuotations"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                        {{ savingQuotations ? 'Saving…' : 'Save Prices' }}
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
