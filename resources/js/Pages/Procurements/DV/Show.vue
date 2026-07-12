<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import { ArrowLeftIcon, CheckIcon, XMarkIcon, ClockIcon, PrinterIcon } from '@heroicons/vue/24/outline'
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
    if (status === 'released') return 'green'
    if (status === 'draft') return 'slate'
    if (['returned_bookkeeper', 'returned_accountant'].includes(status)) return 'red'
    return 'amber'
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

const actionModalTitle = computed(() => ({
    delivery: 'Record Delivery',
    prepare: 'Prepare DV Details',
    dc_elog: 'Division Chief — Log in eLog',
    forward_bookkeeper: 'Forward to Bookkeeper',
    bookkeeper: 'Bookkeeper Review',
    accountant: 'Accountant Review',
    ocd_sign: 'Campus Director Signature',
    cashier: 'Process Payment',
    ocd_payment: 'CD Payment Signature',
}[actionModal.value] ?? ''))

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

        <div class="mb-5 flex items-center justify-between">
            <a :href="route('dv.index')"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
                <ArrowLeftIcon class="w-4 h-4" />
                Back to Disbursement Vouchers
            </a>
            <AppButton variant="secondary" size="sm" as="a" :href="route('dv.print', dv.id)" target="_blank">
                <PrinterIcon class="w-4 h-4" />
                Print / Save PDF
            </AppButton>
        </div>

        <div v-if="flash?.success" class="mb-4 rounded-lg bg-success-50 border border-success-100 px-4 py-3 text-sm text-success-700">
            {{ flash.success }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Details -->
            <div class="lg:col-span-2 space-y-5">
                <!-- Status header -->
                <AppCard>
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-bold text-slate-800 font-mono">{{ d.dv_number || `DV #${d.id}` }}</h2>
                                <AppBadge :color="statusClass(d.status)">{{ d.status_label || d.status }}</AppBadge>
                            </div>
                            <p class="text-sm text-slate-500 mt-1">{{ phaseLabel(d.status) }}</p>
                            <p v-if="d.ors" class="text-sm text-slate-500">
                                Linked ORS: <span class="font-mono text-indigo-600">{{ d.ors.ors_number }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton v-if="canDelivery" @click="openAction('delivery')">
                                Record Delivery
                            </AppButton>
                            <AppButton v-if="canPrepare" @click="openAction('prepare')">
                                Prepare DV
                            </AppButton>
                            <AppButton v-if="canDcElog" variant="success" @click="openAction('dc_elog')">
                                <CheckIcon class="w-4 h-4" /> DC eLog
                            </AppButton>
                            <AppButton v-if="canForward" variant="secondary" @click="openAction('forward_bookkeeper')">
                                Forward to Bookkeeper
                            </AppButton>
                            <AppButton v-if="canBookkeeper" variant="success" @click="openAction('bookkeeper')">
                                <CheckIcon class="w-4 h-4" /> Bookkeeper Review
                            </AppButton>
                            <AppButton v-if="canAccountant" variant="success" @click="openAction('accountant')">
                                <CheckIcon class="w-4 h-4" /> Accountant Review
                            </AppButton>
                            <AppButton v-if="canOcdSign" variant="success" @click="openAction('ocd_sign')">
                                <CheckIcon class="w-4 h-4" /> Sign (Campus Director)
                            </AppButton>
                            <AppButton v-if="canCashier" variant="success" @click="openAction('cashier')">
                                Process Payment
                            </AppButton>
                            <AppButton v-if="canOcdPayment" variant="success" @click="openAction('ocd_payment')">
                                <CheckIcon class="w-4 h-4" /> Sign Payment (CD)
                            </AppButton>
                        </div>
                    </div>
                </AppCard>

                <!-- Financial details -->
                <AppCard>
                    <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">Financial Details</h3>
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
                </AppCard>

                <!-- Delivery details -->
                <AppCard v-if="d.delivery_accepted_at">
                    <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">Delivery &amp; Inspection (Phase A)</h3>
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
                                <AppBadge :color="d.ris_complete ? 'green' : 'slate'">{{ d.ris_complete ? 'Yes' : 'No' }}</AppBadge>
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
                </AppCard>

                <!-- Returns -->
                <div v-if="d.bookkeeper_return_reason || d.accountant_return_reason"
                    class="bg-danger-50 rounded-xl border border-danger-100 p-5">
                    <h3 class="text-xs font-semibold text-danger-600 uppercase tracking-wide mb-3">Return Notes</h3>
                    <div v-if="d.bookkeeper_return_reason" class="text-sm">
                        <span class="font-medium text-danger-700">Bookkeeper:</span>
                        <span class="text-danger-600 ml-1">{{ d.bookkeeper_return_reason }}</span>
                    </div>
                    <div v-if="d.accountant_return_reason" class="text-sm mt-2">
                        <span class="font-medium text-danger-700">Accountant:</span>
                        <span class="text-danger-600 ml-1">{{ d.accountant_return_reason }}</span>
                    </div>
                </div>
            </div>

            <!-- Right: Timeline -->
            <div>
                <AppCard>
                    <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">Processing Timeline</h3>
                    <ol class="space-y-4">
                        <li v-for="(step, i) in timelineSteps" :key="i" class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div :class="['w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0',
                                    step.returned ? 'bg-danger-100' : step.done ? 'bg-success-100' : step.pending ? 'bg-warning-100' : 'bg-slate-100']">
                                    <XMarkIcon v-if="step.returned" class="w-4 h-4 text-danger-600" />
                                    <CheckIcon v-else-if="step.done" class="w-4 h-4 text-success-600" />
                                    <ClockIcon v-else-if="step.pending" class="w-4 h-4 text-warning-500" />
                                    <span v-else class="w-2 h-2 rounded-full bg-slate-300"></span>
                                </div>
                                <div v-if="i < timelineSteps.length - 1" class="w-0.5 flex-1 bg-slate-100 mt-1"></div>
                            </div>
                            <div class="pb-4">
                                <p :class="['text-sm font-medium',
                                    step.returned ? 'text-danger-700' : step.done ? 'text-slate-800' : step.pending ? 'text-warning-700' : 'text-slate-400']">
                                    {{ step.label }}
                                </p>
                                <p v-if="step.actor" class="text-xs text-slate-500 mt-0.5">{{ step.actor }}</p>
                                <p v-if="step.at" class="text-xs text-slate-400 mt-0.5">{{ formatDateTime(step.at) }}</p>
                                <p v-if="step.returnInfo" class="text-xs text-danger-500 mt-0.5">{{ step.returnInfo }}</p>
                                <p v-else-if="step.pending" class="text-xs text-warning-500 mt-0.5">Awaiting action</p>
                            </div>
                        </li>
                    </ol>
                </AppCard>
            </div>
        </div>

        <!-- Action Modal -->
        <AppModal :show="!!actionModal" :title="actionModalTitle" size="sm" @close="closeAction">
            <div class="space-y-4">
                <!-- Delivery form -->
                <template v-if="actionModal === 'delivery'">
                    <div class="grid grid-cols-2 gap-4">
                        <AppInput v-model="actionForm.iar_number" label="IAR Number" />
                        <AppInput v-model="actionForm.ris_number" label="RIS Number" />
                        <AppInput v-model="actionForm.dr_number" label="DR Number" />
                        <div class="flex items-center gap-2 pt-5">
                            <input type="checkbox" id="ris_complete" v-model="actionForm.ris_complete" class="rounded text-indigo-600 focus:ring-indigo-500" />
                            <label for="ris_complete" class="text-sm text-slate-700">RIS Complete</label>
                        </div>
                    </div>
                </template>
                <!-- Prepare DV -->
                <template v-else-if="actionModal === 'prepare'">
                    <div class="grid grid-cols-2 gap-4">
                        <AppInput v-model="actionForm.gross_amount" type="number" step="0.01" label="Gross Amount" required />
                        <AppInput v-model="actionForm.tax_amount" type="number" step="0.01" label="Tax Amount" />
                        <AppInput v-model="actionForm.po_number" label="PO Number" />
                        <AppInput v-model="actionForm.activity_date" type="date" label="Activity Date" />
                        <div class="col-span-2">
                            <AppInput v-model="actionForm.activity_title" label="Activity Title" />
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
                                <input type="radio" v-model="actionForm.action" value="return" class="text-danger-600 focus:ring-danger-500" />
                                Return
                            </label>
                        </div>
                    </div>
                    <AppTextarea v-model="actionForm.remarks" :rows="2" label="Remarks" />
                </template>
                <!-- Cashier -->
                <template v-else-if="actionModal === 'cashier'">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Payment Method <span class="text-danger-600">*</span></label>
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
                    <AppInput v-model="actionForm.cashier_amount" type="number" step="0.01" label="Amount" required />
                </template>
                <!-- Simple confirm -->
                <template v-else>
                    <p class="text-sm text-slate-600">Are you sure you want to proceed?</p>
                </template>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="closeAction">Cancel</AppButton>
                <AppButton :loading="actionSubmitting" :disabled="actionSubmitting" @click="submitAction">
                    {{ actionSubmitting ? 'Processing…' : 'Confirm' }}
                </AppButton>
            </template>
        </AppModal>

    </AdminLayout>
</template>
