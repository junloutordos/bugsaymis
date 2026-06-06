<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeftIcon, CheckIcon, XMarkIcon, ClockIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    dv: Object,
    currentUser: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash)
const d = computed(() => props.dv)
const perms = computed(() => props.currentUser?.permissions ?? {})

const formatDate = (dt) => {
    if (!dt) return '—'
    return new Date(dt).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
const formatDateTime = (dt) => {
    if (!dt) return '—'
    return new Date(dt).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
const formatPeso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const statusClass = (status) => {
    if (status === 'released') return 'bg-emerald-100 text-emerald-700'
    if (status === 'draft') return 'bg-slate-100 text-slate-600'
    if (['returned_bookkeeper', 'returned_accountant'].includes(status)) return 'bg-red-100 text-red-700'
    return 'bg-amber-100 text-amber-700'
}

const phaseLabel = (status) => {
    const labels = {
        draft: 'Phase A — Awaiting Delivery',
        delivery_accepted: 'Phase B — DV Preparation',
        preparing: 'Phase B — DV Preparation',
        pending_bookkeeper: 'Phase C — Bookkeeper Review',
        returned_bookkeeper: 'Phase C — Returned (Incomplete)',
        pending_accountant: 'Phase D — Accountant Review',
        returned_accountant: 'Phase D — Returned (Data Error)',
        pending_ocd_sign: 'Phase E — Campus Director Signing',
        pending_cashier: 'Phase F — Cashier Processing',
        pending_ocd_payment: 'Phase G — CD Payment Signature',
        released: 'Phase H — Released',
    }
    return labels[status] || status
}

// ── Timeline ───────────────────────────────────────────────────────────────
const timelineSteps = computed(() => {
    const r = d.value
    return [
        { label: 'DV Created', actor: r.creator?.name, at: r.created_at, done: true },
        { label: 'Phase A — Delivery & Inspection', actor: r.supply_officer?.name, at: r.delivery_accepted_at, done: !!r.delivery_accepted_at, pending: r.status === 'draft' },
        { label: 'Phase B — DV Prepared', actor: r.prepared_by?.name, at: r.dv_prepared_at, done: !!r.dv_prepared_at, pending: r.status === 'delivery_accepted' },
        { label: 'Phase B — DC eLog', actor: r.division_chief?.name, at: r.division_chief_elog_at, done: !!r.division_chief_elog_at, pending: r.status === 'preparing' },
        { label: 'Phase C — Bookkeeper Verification', actor: r.bookkeeper?.name, at: r.bookkeeper_at, done: !!r.bookkeeper_at && !['returned_bookkeeper'].includes(r.status), pending: r.status === 'pending_bookkeeper', returned: r.status === 'returned_bookkeeper', returnInfo: r.bookkeeper_return_reason },
        { label: 'Phase D — Accountant Review', actor: r.accountant?.name, at: r.accountant_at, done: !!r.accountant_at && !['returned_accountant'].includes(r.status), pending: r.status === 'pending_accountant', returned: r.status === 'returned_accountant', returnInfo: r.accountant_return_reason },
        { label: 'Phase E — Campus Director Signing', actor: r.ocd_signer?.name, at: r.ocd_sign_at, done: !!r.ocd_sign_at, pending: r.status === 'pending_ocd_sign' },
        { label: 'Phase F — Cashier Processing', actor: r.cashier?.name, at: r.cashier_processed_at, done: !!r.cashier_processed_at, pending: r.status === 'pending_cashier' },
        { label: 'Phase G — CD Payment Signature', actor: null, at: r.ocd_payment_sign_at, done: !!r.ocd_payment_sign_at, pending: r.status === 'pending_ocd_payment' },
        { label: 'Phase H — Payment Released', actor: null, at: r.payment_released_at, done: r.status === 'released' },
    ]
})

// ── Actions ────────────────────────────────────────────────────────────────
const actionModal = ref(null)
const actionForm = ref({ action: 'forward', remarks: '', payment_method: 'cheque', cashier_amount: '', iar_number: '', ris_number: '', dr_number: '', ris_complete: false, gross_amount: '', tax_amount: '', activity_title: '', activity_date: '', po_number: '' })
const actionSubmitting = ref(false)

const openAction = (type) => {
    actionModal.value = type
    const dv = d.value
    actionForm.value = {
        action: 'forward', remarks: '',
        payment_method: dv.payment_method || 'cheque',
        cashier_amount: dv.cashier_amount || dv.net_amount || '',
        iar_number: '', ris_number: '', dr_number: '', ris_complete: false,
        gross_amount: dv.gross_amount || '', tax_amount: dv.tax_amount || '',
        activity_title: dv.activity_title || '', activity_date: dv.activity_date || '',
        po_number: dv.po_number || '',
    }
}
const closeAction = () => { actionModal.value = null }

const submitAction = async () => {
    if (actionSubmitting.value) return
    actionSubmitting.value = true
    try {
        const id = d.value.id
        const routeMap = {
            delivery: { url: route('dv.delivery', id), body: { iar_number: actionForm.value.iar_number, ris_number: actionForm.value.ris_number, dr_number: actionForm.value.dr_number, ris_complete: actionForm.value.ris_complete } },
            prepare: { url: route('dv.prepare', id), body: { gross_amount: actionForm.value.gross_amount, tax_amount: actionForm.value.tax_amount, activity_title: actionForm.value.activity_title, activity_date: actionForm.value.activity_date, po_number: actionForm.value.po_number } },
            dc_elog: { url: route('dv.dc-elog', id), body: {} },
            forward_bookkeeper: { url: route('dv.forward-bookkeeper', id), body: {} },
            bookkeeper: { url: route('dv.bookkeeper-action', id), body: { action: actionForm.value.action, remarks: actionForm.value.remarks } },
            accountant: { url: route('dv.accountant-action', id), body: { action: actionForm.value.action, remarks: actionForm.value.remarks } },
            ocd_sign: { url: route('dv.ocd-sign', id), body: {} },
            cashier: { url: route('dv.cashier-process', id), body: { payment_method: actionForm.value.payment_method, cashier_amount: actionForm.value.cashier_amount } },
            ocd_payment: { url: route('dv.ocd-payment-sign', id), body: {} },
        }
        const { url, body } = routeMap[actionModal.value]
        await axios.post(url, body)
        closeAction()
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Action failed.')
    } finally { actionSubmitting.value = false }
}

const isOwner = computed(() => d.value.creator?.id == props.currentUser?.id)
const canDelivery = computed(() => perms.value.canDelivery && d.value.status === 'draft')
const canPrepare = computed(() => (isOwner.value || perms.value.canPrepare) && ['delivery_accepted', 'returned_bookkeeper', 'returned_accountant'].includes(d.value.status))
const canDcElog = computed(() => perms.value.canDcElog && d.value.status === 'preparing')
const canForward = computed(() => (isOwner.value || perms.value.canForward) && d.value.status === 'preparing' && !!d.value.division_chief_elog_at)
const canBookkeeper = computed(() => perms.value.canBookkeep && d.value.status === 'pending_bookkeeper')
const canAccountant = computed(() => perms.value.canAccount && d.value.status === 'pending_accountant')
const canOcdSign = computed(() => perms.value.canOcdSign && d.value.status === 'pending_ocd_sign')
const canCashier = computed(() => perms.value.canCashier && d.value.status === 'pending_cashier')
const canOcdPayment = computed(() => perms.value.canOcdSign && d.value.status === 'pending_ocd_payment')
</script>

<template>
    <Head :title="`DV — ${dv.dv_number || dv.id}`" />
    <AdminLayout :title="`DV — ${dv.dv_number || '#' + dv.id}`">

        <div class="mb-5">
            <a :href="route('dv.index')"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
                <ArrowLeftIcon class="w-4 h-4" />
                Back to Disbursement Vouchers
            </a>
        </div>

        <div v-if="flash?.success" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ flash.success }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Details -->
            <div class="lg:col-span-2 space-y-5">
                <!-- Status header -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-bold text-slate-800 font-mono">{{ d.dv_number || `DV #${d.id}` }}</h2>
                                <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium', statusClass(d.status)]">
                                    {{ d.status_label || d.status }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-500 mt-1">{{ phaseLabel(d.status) }}</p>
                            <p v-if="d.ors" class="text-sm text-slate-500">
                                Linked ORS: <span class="font-mono text-indigo-600">{{ d.ors.ors_number }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="canDelivery" @click="openAction('delivery')"
                                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Record Delivery
                            </button>
                            <button v-if="canPrepare" @click="openAction('prepare')"
                                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Prepare DV
                            </button>
                            <button v-if="canDcElog" @click="openAction('dc_elog')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> DC eLog
                            </button>
                            <button v-if="canForward" @click="openAction('forward_bookkeeper')"
                                class="inline-flex items-center gap-1.5 bg-slate-600 hover:bg-slate-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Forward to Bookkeeper
                            </button>
                            <button v-if="canBookkeeper" @click="openAction('bookkeeper')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Bookkeeper Review
                            </button>
                            <button v-if="canAccountant" @click="openAction('accountant')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Accountant Review
                            </button>
                            <button v-if="canOcdSign" @click="openAction('ocd_sign')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Sign (Campus Director)
                            </button>
                            <button v-if="canCashier" @click="openAction('cashier')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Process Payment
                            </button>
                            <button v-if="canOcdPayment" @click="openAction('ocd_payment')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Sign Payment (CD)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Financial details -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Financial Details</h3>
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div>
                            <dt class="text-slate-500">PO Number</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ d.po_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Activity Date</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ formatDate(d.activity_date) }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-500">Activity</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ d.activity_title || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Gross Amount</dt>
                            <dd class="text-slate-800 font-semibold mt-0.5 text-base">{{ formatPeso(d.gross_amount) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Tax Amount</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ formatPeso(d.tax_amount) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Net Amount</dt>
                            <dd class="text-slate-800 font-bold mt-0.5 text-base">{{ formatPeso(d.net_amount) }}</dd>
                        </div>
                        <div v-if="d.payment_method">
                            <dt class="text-slate-500">Payment Method</dt>
                            <dd class="text-slate-800 font-medium mt-0.5 uppercase">{{ d.payment_method }}</dd>
                        </div>
                        <div v-if="d.cashier_amount">
                            <dt class="text-slate-500">Cashier Amount</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ formatPeso(d.cashier_amount) }}</dd>
                        </div>
                        <div v-if="d.payment_reference">
                            <dt class="text-slate-500">Payment Reference</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ d.payment_reference }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Delivery details -->
                <div v-if="d.delivery_accepted_at" class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Delivery & Inspection (Phase A)</h3>
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div>
                            <dt class="text-slate-500">IAR Number</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ d.iar_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">RIS Number</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ d.ris_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">DR Number</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ d.dr_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">RIS Complete</dt>
                            <dd class="mt-0.5">
                                <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', d.ris_complete ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600']">
                                    {{ d.ris_complete ? 'Yes' : 'No' }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Supply Officer</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ d.supply_officer?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Accepted At</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ formatDateTime(d.delivery_accepted_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Returns -->
                <div v-if="d.bookkeeper_return_reason || d.accountant_return_reason"
                    class="bg-red-50 rounded-xl border border-red-200 p-5">
                    <h3 class="text-xs font-semibold text-red-600 uppercase tracking-wide mb-3">Return Notes</h3>
                    <div v-if="d.bookkeeper_return_reason" class="text-sm">
                        <span class="font-medium text-red-700">Bookkeeper:</span>
                        <span class="text-red-600 ml-1">{{ d.bookkeeper_return_reason }}</span>
                    </div>
                    <div v-if="d.accountant_return_reason" class="text-sm mt-2">
                        <span class="font-medium text-red-700">Accountant:</span>
                        <span class="text-red-600 ml-1">{{ d.accountant_return_reason }}</span>
                    </div>
                </div>
            </div>

            <!-- Right: Timeline -->
            <div>
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Processing Timeline</h3>
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
                                <p v-if="step.returnInfo" class="text-xs text-red-500 mt-0.5">{{ step.returnInfo }}</p>
                                <p v-else-if="step.pending" class="text-xs text-amber-500 mt-0.5">Awaiting action</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Action Modals -->
        <div v-if="actionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-xl">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800">
                        <span v-if="actionModal === 'delivery'">Record Delivery</span>
                        <span v-else-if="actionModal === 'prepare'">Prepare DV Details</span>
                        <span v-else-if="actionModal === 'dc_elog'">Division Chief — Log in eLog</span>
                        <span v-else-if="actionModal === 'forward_bookkeeper'">Forward to Bookkeeper</span>
                        <span v-else-if="actionModal === 'bookkeeper'">Bookkeeper Review</span>
                        <span v-else-if="actionModal === 'accountant'">Accountant Review</span>
                        <span v-else-if="actionModal === 'ocd_sign'">Campus Director Signature</span>
                        <span v-else-if="actionModal === 'cashier'">Process Payment</span>
                        <span v-else-if="actionModal === 'ocd_payment'">CD Payment Signature</span>
                    </h3>
                    <button @click="closeAction" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <!-- Delivery form -->
                    <template v-if="actionModal === 'delivery'">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">IAR Number</label>
                                <input v-model="actionForm.iar_number"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">RIS Number</label>
                                <input v-model="actionForm.ris_number"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">DR Number</label>
                                <input v-model="actionForm.dr_number"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div class="flex items-center gap-2 pt-5">
                                <input type="checkbox" id="ris_complete" v-model="actionForm.ris_complete" class="rounded text-indigo-600 focus:ring-indigo-500" />
                                <label for="ris_complete" class="text-sm text-slate-700">RIS Complete</label>
                            </div>
                        </div>
                    </template>
                    <!-- Prepare DV -->
                    <template v-else-if="actionModal === 'prepare'">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Gross Amount <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" v-model="actionForm.gross_amount"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Tax Amount</label>
                                <input type="number" step="0.01" v-model="actionForm.tax_amount"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">PO Number</label>
                                <input v-model="actionForm.po_number"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Activity Date</label>
                                <input type="date" v-model="actionForm.activity_date"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-slate-600 mb-1">Activity Title</label>
                                <input v-model="actionForm.activity_title"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </template>
                    <!-- Bookkeeper / Accountant -->
                    <template v-else-if="['bookkeeper', 'accountant'].includes(actionModal)">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Action</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.action" value="forward" class="text-indigo-600 focus:ring-indigo-500" />
                                    Forward
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
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>
                    </template>
                    <!-- Cashier -->
                    <template v-else-if="actionModal === 'cashier'">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Payment Method <span class="text-red-500">*</span></label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.payment_method" value="cheque" class="text-indigo-600 focus:ring-indigo-500" />
                                    Cheque
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.payment_method" value="ada" class="text-indigo-600 focus:ring-indigo-500" />
                                    ADA
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Amount <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" v-model="actionForm.cashier_amount"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </template>
                    <!-- Simple confirm -->
                    <template v-else>
                        <p class="text-sm text-slate-600">Are you sure you want to proceed?</p>
                    </template>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="closeAction" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button @click="submitAction" :disabled="actionSubmitting"
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                        {{ actionSubmitting ? 'Processing…' : 'Confirm' }}
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
