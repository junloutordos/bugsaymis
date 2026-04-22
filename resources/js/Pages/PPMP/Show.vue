<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, CheckCircleIcon, ArrowUturnLeftIcon, DocumentArrowDownIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    ppmp: Object,
    items: Array,
    summary: Array,
    grandTotal: Number,
    categories: Object,
    methods: Object,
    canEdit: Boolean,
    canSubmit: Boolean,
    canApprove: Boolean,
    canExport: Boolean,
})

const page = usePage()
const flash = computed(() => page.props.flash)

const months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']
const monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

const formatPeso = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ── Status colors ─────────────────────────────────────────────────────────
const statusColors = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-blue-100 text-blue-700',
    returned: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    consolidated: 'bg-indigo-100 text-indigo-700',
}

// ── Add Item Form ─────────────────────────────────────────────────────────
const showAddForm = ref(false)
const itemForm = useForm({
    code: '',
    description: '',
    unit: '',
    category: 'goods',
    unit_cost: '',
    jan: 0, feb: 0, mar: 0, apr: 0, may: 0, jun: 0,
    jul: 0, aug: 0, sep: 0, oct: 0, nov: 0, dec: 0,
    procurement_method: 'competitive_bidding',
    is_ps_dbm: false,
    remarks: '',
})

const itemTotalQty = computed(() => months.reduce((s, m) => s + (Number(itemForm[m]) || 0), 0))
const itemTotalCost = computed(() => itemTotalQty.value * (Number(itemForm.unit_cost) || 0))

const resetItemForm = () => {
    itemForm.reset()
    itemForm.category = 'goods'
    itemForm.procurement_method = 'competitive_bidding'
    showAddForm.value = false
}

const saveItem = () => {
    itemForm.post(route('ppmp.items.store', props.ppmp.id), {
        preserveScroll: true,
        onSuccess: () => resetItemForm(),
    })
}

// ── Edit Item ─────────────────────────────────────────────────────────────
const editingId = ref(null)
const editForm = useForm({
    code: '',
    description: '',
    unit: '',
    category: 'goods',
    unit_cost: '',
    jan: 0, feb: 0, mar: 0, apr: 0, may: 0, jun: 0,
    jul: 0, aug: 0, sep: 0, oct: 0, nov: 0, dec: 0,
    procurement_method: 'competitive_bidding',
    is_ps_dbm: false,
    remarks: '',
})

const startEdit = (item) => {
    editingId.value = item.id
    editForm.code = item.code || ''
    editForm.description = item.description
    editForm.unit = item.unit
    editForm.category = item.category
    editForm.unit_cost = item.unit_cost
    months.forEach(m => editForm[m] = item[m])
    editForm.procurement_method = item.procurement_method
    editForm.is_ps_dbm = item.is_ps_dbm
    editForm.remarks = item.remarks || ''
}

const cancelEdit = () => { editingId.value = null }

const saveEdit = (item) => {
    editForm.put(route('ppmp.items.update', [props.ppmp.id, item.id]), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null },
    })
}

const deleteItem = (item) => {
    if (!confirm(`Remove "${item.description}"?`)) return
    router.delete(route('ppmp.items.destroy', [props.ppmp.id, item.id]), { preserveScroll: true })
}

// ── Validation ────────────────────────────────────────────────────────────
const validationResult = ref(null)
const validating = ref(false)

const runValidation = async () => {
    validating.value = true
    try {
        const { data } = await axios.post(route('ppmp.validate', props.ppmp.id))
        validationResult.value = data
    } catch (e) {
        validationResult.value = null
    }
    validating.value = false
}

const itemHasError = (id) => validationResult.value?.errors?.some(e => e.item_id === id)
const itemHasWarning = (id) => validationResult.value?.warnings?.some(w => w.item_id === id)

// ── Workflow ──────────────────────────────────────────────────────────────
const submitPpmp = () => {
    if (!confirm('Submit this PPMP for review?')) return
    router.post(route('ppmp.submit', props.ppmp.id), {}, { preserveScroll: true })
}

const approvePpmp = () => {
    if (!confirm('Approve this PPMP?')) return
    router.post(route('ppmp.approve', props.ppmp.id), {}, { preserveScroll: true })
}

const returnRemarks = ref('')
const showReturnModal = ref(false)

const returnPpmp = () => {
    if (!returnRemarks.value.trim()) return
    router.post(route('ppmp.return', props.ppmp.id), { remarks: returnRemarks.value }, {
        preserveScroll: true,
        onSuccess: () => { showReturnModal.value = false; returnRemarks.value = '' },
    })
}

// ── Distribute evenly helper ──────────────────────────────────────────────
const distTotal = ref(0)
const distMode = ref('monthly')

const distributeEvenly = () => {
    const total = Number(distTotal.value) || 0
    if (total <= 0) return
    let selected = []
    if (distMode.value === 'monthly') selected = [...months]
    else if (distMode.value === 'quarterly') selected = ['jan','apr','jul','oct']
    else if (distMode.value === 'semi') selected = ['jan','jul']

    months.forEach(m => itemForm[m] = 0)
    const per = Math.floor(total / selected.length)
    const rem = total % selected.length
    selected.forEach((m, i) => {
        itemForm[m] = per + (i === selected.length - 1 ? rem : 0)
    })
}
</script>

<template>
    <Head :title="ppmp.ppmp_number" />
    <AdminLayout :title="ppmp.ppmp_number">
        <!-- Flash messages -->
        <div v-if="flash.success" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ flash.success }}</div>
        <div v-if="flash.error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ flash.error }}</div>

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
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-semibold text-slate-800">{{ ppmp.title }}</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                              :class="statusColors[ppmp.status]">{{ ppmp.status }}</span>
                    </div>
                    <p class="text-sm text-slate-500">{{ ppmp.division?.division_name }} · FY {{ ppmp.fiscal_year }}</p>
                    <p class="text-sm text-slate-500">Prepared by: {{ ppmp.preparer?.name }}</p>
                    <p v-if="ppmp.remarks && ppmp.status === 'returned'" class="text-sm text-amber-700 mt-2">
                        <strong>Return remarks:</strong> {{ ppmp.remarks }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="canEdit" @click="runValidation" :disabled="validating"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 hover:bg-slate-200 text-slate-700">
                        <ShieldCheckIcon class="w-4 h-4" /> Validate
                    </button>
                    <button v-if="canSubmit" @click="submitPpmp"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white">
                        Submit for Review
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
                </div>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                <div v-for="s in summary" :key="s.category" class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ s.label }}</p>
                    <p class="text-lg font-bold text-slate-800">₱{{ formatPeso(s.subtotal) }}</p>
                    <p class="text-xs text-slate-500">{{ s.item_count }} item(s)</p>
                </div>
                <div class="bg-indigo-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wide">Grand Total</p>
                    <p class="text-lg font-bold text-indigo-800">₱{{ formatPeso(grandTotal) }}</p>
                    <p class="text-xs text-indigo-500">{{ items.length }} item(s)</p>
                </div>
            </div>
        </div>

        <!-- Items table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-700">Procurement Items</h3>
                <button v-if="canEdit && !showAddForm" @click="showAddForm = true"
                        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
                    <PlusIcon class="w-4 h-4" /> Add Item
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1400px] w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-16">Code</th>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-14">Unit</th>
                            <th v-for="(ml, mi) in monthLabels" :key="mi" class="px-1 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-10">{{ ml }}</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-14">Total</th>
                            <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-20">Unit Cost</th>
                            <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-24">Total Cost</th>
                            <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-28">Method</th>
                            <th v-if="canEdit" class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-16">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="(item, idx) in items" :key="item.id">
                            <!-- Category header -->
                            <tr v-if="idx === 0 || item.category !== items[idx - 1].category">
                                <td :colspan="canEdit ? 19 : 18" class="px-2 py-2 bg-slate-100 font-semibold text-xs text-slate-700 uppercase tracking-wide">
                                    {{ categories[item.category] || item.category }}
                                </td>
                            </tr>

                            <!-- Item row -->
                            <tr :class="{
                                'border-l-4 border-red-400': itemHasError(item.id),
                                'border-l-4 border-amber-400': !itemHasError(item.id) && itemHasWarning(item.id),
                            }">
                                <td class="px-2 py-1.5 text-slate-600">{{ item.code }}</td>
                                <td class="px-2 py-1.5 text-slate-700">{{ item.description }}</td>
                                <td class="px-2 py-1.5 text-slate-600">{{ item.unit }}</td>
                                <td v-for="m in months" :key="m" class="px-1 py-1.5 text-center text-slate-600">
                                    {{ item[m] > 0 ? item[m] : '—' }}
                                </td>
                                <td class="px-2 py-1.5 text-center font-medium text-slate-700">{{ item.total_quantity }}</td>
                                <td class="px-2 py-1.5 text-right text-slate-700">{{ formatPeso(item.unit_cost) }}</td>
                                <td class="px-2 py-1.5 text-right font-medium text-slate-800">{{ formatPeso(item.total_cost) }}</td>
                                <td class="px-2 py-1.5 text-slate-600 text-xs">{{ methods[item.procurement_method] || item.procurement_method }}</td>
                                <td v-if="canEdit" class="px-2 py-1.5 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="startEdit(item)" class="text-slate-400 hover:text-indigo-600"><PencilSquareIcon class="w-4 h-4" /></button>
                                        <button @click="deleteItem(item)" class="text-slate-400 hover:text-red-600"><TrashIcon class="w-4 h-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="!items.length">
                            <td :colspan="canEdit ? 19 : 18" class="px-4 py-8 text-center text-slate-400">
                                No items yet. Click "Add Item" to start building your PPMP.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Item Form (slide-down) -->
        <div v-if="showAddForm && canEdit" class="mt-4 bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Add Procurement Item</h3>
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
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="itemForm.is_ps_dbm" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Available from PS-DBM
                        </label>
                    </div>
                </div>

                <!-- Distribute helper -->
                <div class="flex items-end gap-2 bg-slate-50 rounded-lg p-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Quick fill: Total Qty</label>
                        <input v-model.number="distTotal" type="number" min="0" class="w-24 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <select v-model="distMode" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="monthly">Monthly (12)</option>
                            <option value="quarterly">Quarterly (4)</option>
                            <option value="semi">Semi-Annual (2)</option>
                        </select>
                    </div>
                    <button type="button" @click="distributeEvenly" class="px-3 py-2 rounded-lg text-sm font-medium bg-slate-200 hover:bg-slate-300 text-slate-700">Distribute</button>
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

                <!-- Remarks -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                    <textarea v-model="itemForm.remarks" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="resetItemForm" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Cancel</button>
                    <button type="submit" :disabled="itemForm.processing" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">Save Item</button>
                </div>
            </form>
        </div>

        <!-- Status History -->
        <div v-if="ppmp.status_history?.length" class="mt-4 bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Status History</h3>
            <div class="space-y-2">
                <div v-for="h in ppmp.status_history" :key="h.id" class="flex items-start gap-3 text-sm">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                          :class="statusColors[h.to_status]">{{ h.to_status }}</span>
                    <div>
                        <span class="text-slate-700">{{ h.actor?.name }}</span>
                        <span class="text-slate-400 ml-2">{{ new Date(h.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                        <p v-if="h.remarks" class="text-slate-500 text-xs mt-0.5">{{ h.remarks }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Return Modal -->
        <div v-if="showReturnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold text-slate-800 mb-3">Return PPMP for Revision</h3>
                <textarea v-model="returnRemarks" rows="4" placeholder="Explain what needs to be corrected..."
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-3"></textarea>
                <div class="flex justify-end gap-2">
                    <button @click="showReturnModal = false" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200">Cancel</button>
                    <button @click="returnPpmp" :disabled="!returnRemarks.trim()" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-50">Return</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
