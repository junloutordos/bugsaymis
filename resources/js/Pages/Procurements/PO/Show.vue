<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
    ArrowLeftIcon, CheckIcon, XMarkIcon, ClockIcon, PrinterIcon,
    PlusIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    po: Object,
    currentUser: Object,
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const o     = computed(() => props.po)
const perms = computed(() => props.currentUser?.permissions ?? {})

const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
const formatDateTime = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
const formatPeso = (v) => '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ── Status badge ──────────────────────────────────────────────────────────
const STATUS_MAP = {
    draft:                { label: 'Draft',                     cls: 'bg-slate-100 text-slate-600' },
    pending_procurement:  { label: 'Pending Procurement',       cls: 'bg-amber-100 text-amber-700' },
    pending_ocd:          { label: 'Pending Campus Director',   cls: 'bg-blue-100 text-blue-700'   },
    issued:               { label: 'Issued',                    cls: 'bg-emerald-100 text-emerald-700' },
    cancelled:            { label: 'Cancelled',                 cls: 'bg-red-100 text-red-600'     },
}
const statusBadge = (s) => STATUS_MAP[s] ?? { label: s, cls: 'bg-slate-100 text-slate-600' }

// ── Timeline ──────────────────────────────────────────────────────────────
const timelineSteps = computed(() => {
    const r = o.value
    return [
        { label: 'PO Created', actor: r.creator?.name, at: r.created_at, done: true },
        { label: 'Procurement Officer Review', actor: r.procurement_officer?.name, at: r.procurement_officer_at, done: !!r.procurement_officer_at && r.status !== 'draft', pending: r.status === 'pending_procurement', returned: r.status === 'draft' && !!r.procurement_officer_at, returnInfo: r.procurement_officer_remarks },
        { label: 'Campus Director Signature', actor: r.ocd?.name, at: r.ocd_at, done: !!r.ocd_at, pending: r.status === 'pending_ocd' },
        { label: 'Issued', actor: null, at: r.issued_at, done: r.status === 'issued' },
    ]
})

// ── Access guards ─────────────────────────────────────────────────────────
const isOwner    = computed(() => o.value.creator?.id == props.currentUser?.id)
const isDraft    = computed(() => o.value.status === 'draft')
const canEdit    = computed(() => (perms.value.canEdit || isOwner.value) && isDraft.value)
const canSubmit  = computed(() => isOwner.value && isDraft.value && (o.value.items?.length ?? 0) > 0)
const canReview  = computed(() => perms.value.canReview && o.value.status === 'pending_procurement')
const canSign    = computed(() => perms.value.canSign && o.value.status === 'pending_ocd')
const canCancel  = computed(() => isOwner.value && isDraft.value)

// ── Action modal ──────────────────────────────────────────────────────────
const actionModal = ref(null) // 'submit' | 'review' | 'sign' | 'cancel'
const actionForm  = ref({ action: 'approve', remarks: '' })

const openAction = (type) => {
    actionModal.value = type
    actionForm.value  = { action: 'approve', remarks: '' }
}
const closeAction = () => { actionModal.value = null }

const actionSubmitting = ref(false)
const submitAction = () => {
    if (actionSubmitting.value) return
    actionSubmitting.value = true
    const id = o.value.id
    const routes = {
        submit:  { url: route('po.submit',              id), body: {} },
        review:  { url: route('po.procurement-review',  id), body: { action: actionForm.value.action, remarks: actionForm.value.remarks } },
        sign:    { url: route('po.ocd-sign',            id), body: { action: actionForm.value.action, remarks: actionForm.value.remarks } },
        cancel:  { url: route('po.cancel',              id), body: {} },
    }
    const { url, body } = routes[actionModal.value]
    router.post(url, body, {
        onSuccess: () => closeAction(),
        onFinish:  () => { actionSubmitting.value = false },
    })
}

// ── Add item form ─────────────────────────────────────────────────────────
const showAddItem = ref(false)
const itemForm = useForm({
    unit:        '',
    description: '',
    quantity:    1,
    unit_cost:   0,
})
const itemTotal = computed(() => (Number(itemForm.quantity) || 0) * (Number(itemForm.unit_cost) || 0))

const submitAddItem = () => {
    itemForm.post(route('po.store-item', o.value.id), {
        preserveScroll: true,
        onSuccess: () => { showAddItem.value = false; itemForm.reset() },
    })
}

// ── Remove item ───────────────────────────────────────────────────────────
const removingItem = ref(null)
const removeItem = (itemId) => {
    if (!confirm('Remove this line item?')) return
    removingItem.value = itemId
    router.delete(route('po.destroy-item', { po: o.value.id, item: itemId }), {
        preserveScroll: true,
        onFinish: () => { removingItem.value = null },
    })
}
</script>

<template>
    <Head :title="`PO — ${po.po_number || '#' + po.id}`" />
    <AdminLayout :title="`PO — ${po.po_number || '#' + po.id}`">

        <!-- Top bar -->
        <div class="mb-5 flex items-center justify-between flex-wrap gap-3">
            <a :href="route('po.index')"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
                <ArrowLeftIcon class="w-4 h-4" />
                Back to Purchase Orders
            </a>
            <a :href="route('po.print', po.id)" target="_blank"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors shadow-sm">
                <PrinterIcon class="w-4 h-4" />
                Print / Save PDF
            </a>
        </div>

        <!-- Flash -->
        <div v-if="flash?.success" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ flash.success }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Left: Details + Items ── -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Status header + action buttons -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-bold text-slate-800 font-mono">{{ o.po_number || `PO #${o.id}` }}</h2>
                                <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium', statusBadge(o.status).cls]">
                                    {{ statusBadge(o.status).label }}
                                </span>
                            </div>
                            <p v-if="o.pr" class="text-sm text-slate-500 mt-1">
                                Linked PR: <span class="font-mono text-indigo-600">{{ o.pr.pr_no }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="canSubmit" @click="openAction('submit')"
                                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Submit for Review
                            </button>
                            <button v-if="canReview" @click="openAction('review')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Procurement Review
                            </button>
                            <button v-if="canSign" @click="openAction('sign')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Sign & Issue
                            </button>
                            <button v-if="canCancel" @click="openAction('cancel')"
                                class="inline-flex items-center gap-1.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 px-3 py-2 rounded-lg text-sm font-medium">
                                <XMarkIcon class="w-4 h-4" /> Cancel PO
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PO Details -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Purchase Order Details</h3>
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div>
                            <dt class="text-slate-500">PO Number</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ o.po_number || '(Not yet issued)' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">PO Date</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ formatDate(o.po_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Prepared By</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.creator?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Total Amount</dt>
                            <dd class="text-slate-800 font-semibold text-lg mt-0.5">{{ formatPeso(o.total_amount) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Supplier Info -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Supplier Information</h3>
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div class="col-span-2">
                            <dt class="text-slate-500">Supplier Name</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.supplier_name }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-500">Address</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.supplier_address || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">TIN</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ o.supplier_tin || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Delivery Place</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.delivery_place || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Delivery Date</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ formatDate(o.delivery_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Payment Terms</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.payment_terms || '—' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-500">Delivery Terms</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.delivery_terms || '—' }}</dd>
                        </div>
                        <div v-if="o.remarks" class="col-span-2">
                            <dt class="text-slate-500">Remarks</dt>
                            <dd class="text-slate-700 mt-0.5">{{ o.remarks }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Line Items -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 flex items-center justify-between border-b border-slate-100">
                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Line Items</h3>
                        <button v-if="canEdit && !showAddItem" @click="showAddItem = true"
                            class="inline-flex items-center gap-1.5 text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg font-medium">
                            <PlusIcon class="w-3.5 h-3.5" /> Add Item
                        </button>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100">
                                <th class="text-center py-2.5 px-3 w-12">#</th>
                                <th class="text-left py-2.5 px-3 w-16">Unit</th>
                                <th class="text-left py-2.5 px-3">Description</th>
                                <th class="text-center py-2.5 px-3 w-16">Qty</th>
                                <th class="text-right py-2.5 px-3 w-28">Unit Cost</th>
                                <th class="text-right py-2.5 px-3 w-28">Total</th>
                                <th v-if="canEdit" class="py-2.5 px-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="(o.items?.length ?? 0) === 0 && !showAddItem">
                                <td :colspan="canEdit ? 7 : 6" class="py-8 text-center text-slate-400 text-xs">
                                    No line items yet. Add items to this PO.
                                </td>
                            </tr>
                            <tr v-for="item in (o.items ?? [])" :key="item.id" class="border-b border-slate-50 hover:bg-slate-50">
                                <td class="py-2.5 px-3 text-center text-slate-500 text-xs">{{ item.item_no }}</td>
                                <td class="py-2.5 px-3 text-slate-600 text-xs">{{ item.unit || '—' }}</td>
                                <td class="py-2.5 px-3 text-slate-800">{{ item.description }}</td>
                                <td class="py-2.5 px-3 text-center text-slate-600">{{ item.quantity }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-700">{{ formatPeso(item.unit_cost) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-medium text-slate-800">{{ formatPeso(item.total_cost) }}</td>
                                <td v-if="canEdit" class="py-2.5 px-3 text-center">
                                    <button @click="removeItem(item.id)" :disabled="removingItem === item.id"
                                        class="text-red-400 hover:text-red-600 disabled:opacity-40 transition-colors">
                                        <TrashIcon class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>

                            <!-- Add item row -->
                            <template v-if="showAddItem">
                                <tr class="bg-indigo-50 border-b border-indigo-100">
                                    <td class="py-2 px-3 text-center text-xs text-slate-400">new</td>
                                    <td class="py-2 px-3">
                                        <input v-model="itemForm.unit" placeholder="pcs"
                                            class="w-full text-xs rounded border border-slate-200 px-2 py-1 focus:ring-1 focus:ring-indigo-500 focus:outline-none" />
                                    </td>
                                    <td class="py-2 px-3">
                                        <input v-model="itemForm.description" placeholder="Description *"
                                            class="w-full text-xs rounded border border-slate-200 px-2 py-1 focus:ring-1 focus:ring-indigo-500 focus:outline-none" />
                                        <p v-if="itemForm.errors.description" class="text-red-500 text-xs mt-0.5">{{ itemForm.errors.description }}</p>
                                    </td>
                                    <td class="py-2 px-3">
                                        <input v-model.number="itemForm.quantity" type="number" min="1"
                                            class="w-full text-xs rounded border border-slate-200 px-2 py-1 focus:ring-1 focus:ring-indigo-500 focus:outline-none text-center" />
                                    </td>
                                    <td class="py-2 px-3">
                                        <input v-model.number="itemForm.unit_cost" type="number" step="0.01" min="0"
                                            class="w-full text-xs rounded border border-slate-200 px-2 py-1 focus:ring-1 focus:ring-indigo-500 focus:outline-none text-right" />
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono text-xs text-slate-600">{{ formatPeso(itemTotal) }}</td>
                                    <td class="py-2 px-3">
                                        <button @click="showAddItem = false" class="text-slate-400 hover:text-slate-600">
                                            <XMarkIcon class="w-3.5 h-3.5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr class="bg-indigo-50">
                                    <td :colspan="7" class="pb-3 px-3">
                                        <div class="flex gap-2 justify-end">
                                            <button @click="showAddItem = false; itemForm.reset()"
                                                class="text-xs px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-white">Cancel</button>
                                            <button @click="submitAddItem" :disabled="itemForm.processing"
                                                class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium disabled:opacity-50">
                                                Add Item
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Total row -->
                            <tr v-if="(o.items?.length ?? 0) > 0" class="bg-slate-50 font-semibold">
                                <td :colspan="canEdit ? 5 : 4" class="py-2.5 px-3 text-right text-xs uppercase tracking-wide text-slate-500">Total Amount</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-800">{{ formatPeso(o.total_amount) }}</td>
                                <td v-if="canEdit"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Linked ORS -->
                <div v-if="(o.ors_list?.length ?? 0) > 0" class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Linked Obligation Requests</h3>
                    <div class="space-y-2">
                        <div v-for="ors in o.ors_list" :key="ors.id"
                            class="flex items-center justify-between py-2 px-3 rounded-lg border border-slate-100 bg-slate-50 text-sm">
                            <span class="font-mono font-medium text-slate-800">{{ ors.ors_number || `ORS #${ors.id}` }}</span>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-slate-500">{{ ors.status }}</span>
                                <a :href="route('ors.show', ors.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View →</a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── Right: Timeline + Procurement Officer Remarks ── -->
            <div class="space-y-5">

                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Approval Timeline</h3>
                    <ol class="space-y-4">
                        <li v-for="(step, i) in timelineSteps" :key="i" class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div :class="['w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0',
                                    step.returned ? 'bg-red-100' : step.done ? 'bg-emerald-100' : step.pending ? 'bg-amber-100' : 'bg-slate-100']">
                                    <XMarkIcon v-if="step.returned" class="w-4 h-4 text-red-600" />
                                    <CheckIcon v-else-if="step.done" class="w-4 h-4 text-emerald-600" />
                                    <ClockIcon v-else-if="step.pending" class="w-4 h-4 text-amber-500" />
                                    <span v-else class="w-2 h-2 rounded-full bg-slate-300"></span>
                                </div>
                                <div v-if="i < timelineSteps.length - 1" class="w-0.5 flex-1 bg-slate-100 mt-1"></div>
                            </div>
                            <div class="pb-4">
                                <p :class="['text-sm font-medium',
                                    step.returned ? 'text-red-700' : step.done ? 'text-slate-800' : step.pending ? 'text-amber-700' : 'text-slate-400']">
                                    {{ step.label }}
                                </p>
                                <p v-if="step.actor" class="text-xs text-slate-500 mt-0.5">{{ step.actor }}</p>
                                <p v-if="step.at" class="text-xs text-slate-400 mt-0.5">{{ formatDateTime(step.at) }}</p>
                                <p v-if="step.returnInfo" class="text-xs text-red-500 mt-0.5 italic">{{ step.returnInfo }}</p>
                                <p v-else-if="step.pending" class="text-xs text-amber-500 mt-0.5">Awaiting action</p>
                            </div>
                        </li>
                    </ol>
                </div>

                <div v-if="o.procurement_officer_remarks" class="bg-amber-50 rounded-xl border border-amber-100 p-4">
                    <h3 class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-2">Procurement Officer Remarks</h3>
                    <p class="text-sm text-amber-800">{{ o.procurement_officer_remarks }}</p>
                </div>

            </div>
        </div>

        <!-- ── Action Modals ── -->
        <div v-if="actionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-xl">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800">
                        <span v-if="actionModal === 'submit'">Submit for Procurement Review</span>
                        <span v-else-if="actionModal === 'review'">Procurement Officer Action</span>
                        <span v-else-if="actionModal === 'sign'">Campus Director — Sign & Issue</span>
                        <span v-else-if="actionModal === 'cancel'">Cancel Purchase Order</span>
                    </h3>
                    <button @click="closeAction" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <!-- Procurement review: approve or return -->
                    <template v-if="actionModal === 'review'">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Action</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.action" value="approve" class="text-indigo-600 focus:ring-indigo-500" />
                                    Approve — forward to Campus Director
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.action" value="return" class="text-red-600 focus:ring-red-500" />
                                    Return
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                            <textarea v-model="actionForm.remarks" rows="2"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                        </div>
                    </template>

                    <!-- OCD sign: sign or return -->
                    <template v-else-if="actionModal === 'sign'">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Action</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.action" value="sign" class="text-indigo-600 focus:ring-indigo-500" />
                                    Sign &amp; Issue PO
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.action" value="return" class="text-red-600 focus:ring-red-500" />
                                    Return to Procurement
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks (optional)</label>
                            <textarea v-model="actionForm.remarks" rows="2"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                        </div>
                    </template>

                    <!-- Cancel confirmation -->
                    <template v-else-if="actionModal === 'cancel'">
                        <p class="text-sm text-slate-600">This will permanently cancel the Purchase Order. Are you sure?</p>
                    </template>

                    <!-- Submit confirmation -->
                    <template v-else>
                        <p class="text-sm text-slate-600">Submit this PO to the Procurement Officer for review?</p>
                    </template>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="closeAction"
                        class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button @click="submitAction" :disabled="actionSubmitting"
                        :class="['inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60',
                            actionModal === 'cancel' ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white']">
                        {{ actionSubmitting ? 'Processing…' : 'Confirm' }}
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
