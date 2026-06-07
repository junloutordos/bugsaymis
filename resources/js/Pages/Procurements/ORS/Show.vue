<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeftIcon, CheckIcon, XMarkIcon, ClockIcon, PrinterIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    ors: Object,
    currentUser: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash)
const o = computed(() => props.ors)
const perms = computed(() => props.currentUser?.permissions ?? {})

const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
const formatDateTime = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
const formatPeso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const statusClass = (status) => {
    if (status === 'completed') return 'bg-emerald-100 text-emerald-700'
    if (status === 'draft') return 'bg-slate-100 text-slate-600'
    if (['returned_bo', 'returned_bookkeeper'].includes(status)) return 'bg-red-100 text-red-700'
    return 'bg-amber-100 text-amber-700'
}

// ── Timeline ───────────────────────────────────────────────────────────────
const timelineSteps = computed(() => {
    const r = o.value
    return [
        { label: 'ORS Created', actor: r.creator?.name, at: r.created_at, done: true },
        { label: 'Division Chief Signature', actor: r.division_chief?.name, at: r.division_chief_at, done: !!r.division_chief_at, pending: r.status === 'pending_dc' },
        { label: 'Budget Officer — Verify & Number', actor: r.budget_officer?.name, at: r.budget_officer_at, done: !!r.budget_officer_at, pending: r.status === 'pending_bo',
            returned: r.status === 'returned_bo', returnInfo: r.budget_officer_return_reason },
        { label: 'Bookkeeper — Completeness Check', actor: r.bookkeeper?.name, at: r.bookkeeper_at, done: !!r.bookkeeper_at && !['returned_bookkeeper'].includes(r.status), pending: r.status === 'pending_bookkeeper',
            returned: r.status === 'returned_bookkeeper', returnInfo: r.bookkeeper_return_reason },
        { label: 'Accountant — Data Review', actor: r.accountant?.name, at: r.accountant_at, done: !!r.accountant_at, pending: r.status === 'pending_accountant' },
        { label: 'Campus Director Signature', actor: r.ocd?.name, at: r.ocd_at, done: !!r.ocd_at, pending: r.status === 'pending_ocd' },
        { label: 'Forwarded to Canvaser', actor: r.canvaser?.name, at: r.canvaser_at, done: !!r.canvaser_at, pending: r.status === 'at_canvaser' },
        { label: 'Completed', actor: null, at: null, done: r.status === 'completed', pending: false },
    ]
})

// ── Action modal ───────────────────────────────────────────────────────────
const actionModal = ref(null)
const actionForm = ref({ remarks: '', action: 'approve', ors_number: '', log_po_number: '', log_activity_title: '', log_activity_date: '', log_amount: '' })
const actionSubmitting = ref(false)

const openAction = (type) => {
    actionModal.value = type
    actionForm.value = { remarks: '', action: 'approve', ors_number: '', log_po_number: o.value.po_number || '', log_activity_title: o.value.activity_title || '', log_activity_date: o.value.activity_date || '', log_amount: o.value.amount || '' }
}
const closeAction = () => { actionModal.value = null }

const submitAction = async () => {
    if (actionSubmitting.value) return
    actionSubmitting.value = true
    try {
        const id = o.value.id
        const routeMap = {
            submit: { url: route('ors.submit', id), body: {} },
            dc_sign: { url: route('ors.dc-sign', id), body: {} },
            log: { url: route('ors.log', id), body: { log_po_number: actionForm.value.log_po_number, log_activity_title: actionForm.value.log_activity_title, log_activity_date: actionForm.value.log_activity_date, log_amount: actionForm.value.log_amount } },
            bo_action: { url: route('ors.bo-action', id), body: { action: actionForm.value.action, ors_number: actionForm.value.ors_number, remarks: actionForm.value.remarks } },
            bookkeeper: { url: route('ors.bookkeeper-action', id), body: { action: actionForm.value.action, remarks: actionForm.value.remarks } },
            accountant: { url: route('ors.accountant-sign', id), body: { remarks: actionForm.value.remarks } },
            ocd: { url: route('ors.ocd-sign', id), body: {} },
            canvaser: { url: route('ors.canvaser-receive', id), body: {} },
        }
        const { url, body } = routeMap[actionModal.value]
        await axios.post(url, body)
        closeAction()
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Action failed.')
    } finally { actionSubmitting.value = false }
}

const isOwner = computed(() => o.value.creator?.id == props.currentUser?.id)
const canSubmit = computed(() => isOwner.value && ['draft', 'returned_bo', 'returned_bookkeeper'].includes(o.value.status))
const canDcSign = computed(() => perms.value.canDcSign && o.value.status === 'pending_dc')
const canLog = computed(() => isOwner.value && o.value.status === 'pending_bo' && !o.value.is_logged)
const canBoAction = computed(() => perms.value.canBudgetSign && o.value.status === 'pending_bo' && o.value.is_logged)
const canBookkeeper = computed(() => perms.value.canBookkeep && o.value.status === 'pending_bookkeeper')
const canAccountant = computed(() => perms.value.canAccount && o.value.status === 'pending_accountant')
const canOcd = computed(() => perms.value.canOcdSign && o.value.status === 'pending_ocd')
const canCanvaser = computed(() => o.value.status === 'at_canvaser')
</script>

<template>
    <Head :title="`ORS — ${ors.ors_number || ors.id}`" />
    <AdminLayout :title="`ORS — ${ors.ors_number || '#' + ors.id}`">

        <div class="mb-5 flex items-center justify-between">
            <a :href="route('ors.index')"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
                <ArrowLeftIcon class="w-4 h-4" />
                Back to ORS
            </a>
            <a :href="route('ors.print', ors.id)" target="_blank"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors shadow-sm">
                <PrinterIcon class="w-4 h-4" />
                Print / Save PDF
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
                                <h2 class="text-lg font-bold text-slate-800 font-mono">{{ o.ors_number || `ORS #${o.id}` }}</h2>
                                <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium', statusClass(o.status)]">
                                    {{ o.status_label || o.status }}
                                </span>
                            </div>
                            <p v-if="o.procurement" class="text-sm text-slate-500 mt-1">
                                Linked PR: <span class="font-mono text-indigo-600">{{ o.procurement.pr_no }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="canSubmit" @click="openAction('submit')"
                                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Submit for DC Signature
                            </button>
                            <button v-if="canDcSign" @click="openAction('dc_sign')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Sign
                            </button>
                            <button v-if="canLog" @click="openAction('log')"
                                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Log in eLog
                            </button>
                            <button v-if="canBoAction" @click="openAction('bo_action')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Budget Officer Action
                            </button>
                            <button v-if="canBookkeeper" @click="openAction('bookkeeper')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Bookkeeper Review
                            </button>
                            <button v-if="canAccountant" @click="openAction('accountant')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Accountant Sign
                            </button>
                            <button v-if="canOcd" @click="openAction('ocd')"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Campus Director Sign
                            </button>
                            <button v-if="canCanvaser" @click="openAction('canvaser')"
                                class="inline-flex items-center gap-1.5 bg-slate-600 hover:bg-slate-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Mark as Received
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Details</h3>
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div>
                            <dt class="text-slate-500">Created By</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.creator?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Division</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.division?.division_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">PO Number</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ o.po_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Supplier</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.supplier_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Account Title</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.account_title || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Object Code</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ o.object_code || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Activity</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.activity_title || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Activity Date</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ formatDate(o.activity_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Amount</dt>
                            <dd class="text-slate-800 font-semibold mt-0.5 text-lg">{{ formatPeso(o.amount) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- eLog data (if logged) -->
                <div v-if="o.is_logged" class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">eLog Data</h3>
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div>
                            <dt class="text-slate-500">Logged PO No</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ o.log_po_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Logged Activity</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ o.log_activity_title || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Logged Date</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ formatDate(o.log_activity_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Logged Amount</dt>
                            <dd class="text-slate-800 font-semibold mt-0.5">{{ formatPeso(o.log_amount) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Returns info -->
                <div v-if="o.budget_officer_return_reason || o.bookkeeper_return_reason"
                    class="bg-red-50 rounded-xl border border-red-200 p-5">
                    <h3 class="text-xs font-semibold text-red-600 uppercase tracking-wide mb-3">Return Notes</h3>
                    <div v-if="o.budget_officer_return_reason" class="text-sm">
                        <span class="font-medium text-red-700">Budget Officer:</span>
                        <span class="text-red-600 ml-1">{{ o.budget_officer_return_reason }}</span>
                    </div>
                    <div v-if="o.bookkeeper_return_reason" class="text-sm mt-2">
                        <span class="font-medium text-red-700">Bookkeeper:</span>
                        <span class="text-red-600 ml-1">{{ o.bookkeeper_return_reason }}</span>
                    </div>
                </div>
            </div>

            <!-- Right: Timeline -->
            <div>
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
                        <span v-if="actionModal === 'submit'">Submit for Division Chief Signature</span>
                        <span v-else-if="actionModal === 'dc_sign'">Division Chief Sign</span>
                        <span v-else-if="actionModal === 'log'">Log in eLog</span>
                        <span v-else-if="actionModal === 'bo_action'">Budget Officer Action</span>
                        <span v-else-if="actionModal === 'bookkeeper'">Bookkeeper Review</span>
                        <span v-else-if="actionModal === 'accountant'">Accountant Sign</span>
                        <span v-else-if="actionModal === 'ocd'">Campus Director Sign</span>
                        <span v-else-if="actionModal === 'canvaser'">Mark as Received by Canvaser</span>
                    </h3>
                    <button @click="closeAction" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <!-- eLog form -->
                    <template v-if="actionModal === 'log'">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">PO Number</label>
                                <input v-model="actionForm.log_po_number"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Amount</label>
                                <input type="number" step="0.01" v-model="actionForm.log_amount"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-slate-600 mb-1">Activity Title</label>
                                <input v-model="actionForm.log_activity_title"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Activity Date</label>
                                <input type="date" v-model="actionForm.log_activity_date"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </template>
                    <!-- Budget Officer action -->
                    <template v-else-if="actionModal === 'bo_action'">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Action</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.action" value="approve" class="text-indigo-600 focus:ring-indigo-500" />
                                    Approve & Number
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.action" value="return" class="text-red-600 focus:ring-red-500" />
                                    Return
                                </label>
                            </div>
                        </div>
                        <div v-if="actionForm.action === 'approve'">
                            <label class="block text-xs font-medium text-slate-600 mb-1">ORS Number <span class="text-red-500">*</span></label>
                            <input v-model="actionForm.ors_number"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="e.g. ORS-2024-001" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                            <textarea v-model="actionForm.remarks" rows="2"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>
                    </template>
                    <!-- Bookkeeper -->
                    <template v-else-if="actionModal === 'bookkeeper'">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Action</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="actionForm.action" value="forward" class="text-indigo-600 focus:ring-indigo-500" />
                                    Forward to Accountant
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
                    <!-- Accountant -->
                    <template v-else-if="actionModal === 'accountant'">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks (optional)</label>
                            <textarea v-model="actionForm.remarks" rows="2"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
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
