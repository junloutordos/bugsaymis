<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeftIcon, CheckIcon, XMarkIcon, ClockIcon, PrinterIcon, DocumentArrowDownIcon, PlusIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    procurement: Object,
    currentUser: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash)
const p = computed(() => props.procurement)
const perms = computed(() => props.currentUser?.permissions ?? {})

const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
const formatDateTime = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const statusClass = (status) => {
    if (!status || status === 'approved') return 'bg-emerald-100 text-emerald-700'
    if (status === 'rejected') return 'bg-red-100 text-red-700'
    if (status === 'draft') return 'bg-slate-100 text-slate-600'
    return 'bg-amber-100 text-amber-700'
}

// ── Timeline steps ─────────────────────────────────────────────────────────
const timelineSteps = computed(() => {
    const pr = p.value
    const steps = [
        {
            label: 'Purchase Request Created',
            actor: pr.requester?.name,
            at: pr.created_at,
            done: true,
        },
    ]

    if (pr.is_supplemental) {
        steps.push({
            label: 'Supplemental: DC/EU Sign',
            actor: null,
            at: null,
            done: !!pr.division_chief_at,
            pending: pr.status === 'supplemental_pending_dc',
        })
        steps.push({
            label: 'Supplemental: Budget Officer Initial',
            actor: null,
            at: pr.supplemental_bo_at,
            done: !!pr.supplemental_bo_at,
            pending: pr.status === 'supplemental_pending_bo',
        })
        steps.push({
            label: 'Supplemental: Campus Director Approval',
            actor: null,
            at: null,
            done: pr.supplemental_status === 'ocd_approved',
            pending: pr.status === 'supplemental_pending_ocd',
        })
    }

    steps.push({
        label: 'Division Chief Signature',
        actor: pr.division_chief?.name,
        at: pr.division_chief_at,
        done: !!pr.division_chief_at,
        pending: pr.status === 'pending_dc' || pr.status === 'supplemental_pending_dc',
    })
    steps.push({
        label: 'PR Number Assigned (Procurement Officer)',
        actor: pr.procurement_officer?.name,
        at: pr.procurement_officer_at,
        done: !!pr.procurement_officer_at,
        pending: pr.status === 'pending_procurement',
    })
    steps.push({
        label: 'Campus Director Approval',
        actor: pr.ocd?.name,
        at: pr.ocd_at,
        done: !!pr.ocd_at && pr.status === 'approved',
        pending: pr.status === 'pending_ocd',
    })

    if (pr.status === 'rejected') {
        steps.push({
            label: 'Rejected',
            actor: pr.rejected_by_user?.name,
            at: pr.rejected_at,
            done: true,
            rejected: true,
        })
    }

    return steps
})

// ── Action modals ──────────────────────────────────────────────────────────
const actionModal = ref(null)
const actionForm = ref({ remarks: '', reason: '', pr_number: '' })
const actionSubmitting = ref(false)

const openAction = (type) => {
    actionModal.value = type
    actionForm.value = { remarks: '', reason: '', pr_number: '' }
}
const closeAction = () => { actionModal.value = null }

const submitAction = async () => {
    if (actionSubmitting.value) return
    actionSubmitting.value = true
    try {
        const pr = p.value
        const routeMap = {
            dc_sign: { url: route('procurements.dc-sign', pr.id), body: { remarks: actionForm.value.remarks } },
            bo_initial: { url: route('procurements.bo-initial', pr.id), body: {} },
            supp_ocd: { url: route('procurements.supplemental-ocd-sign', pr.id), body: {} },
            assign_number: { url: route('procurements.assign-number', pr.id), body: { pr_number: actionForm.value.pr_number } },
            ocd_sign: { url: route('procurements.ocd-sign', pr.id), body: { remarks: actionForm.value.remarks } },
            reject: { url: route('procurements.reject', pr.id), body: { reason: actionForm.value.reason } },
            submit: { url: route('procurements.submit', pr.id), body: {} },
        }
        const { url, body } = routeMap[actionModal.value]
        await axios.post(url, body)
        closeAction()
        router.reload({ preserveScroll: true })
    } catch (e) {
        alert(e.response?.data?.message || 'Action failed.')
    } finally { actionSubmitting.value = false }
}

const canSubmit = computed(() => p.value.status === 'draft' && p.value.requested_by == props.currentUser?.id)
const canDcSign = computed(() => perms.value.canDcSign && ['pending_dc', 'supplemental_pending_dc'].includes(p.value.status))
const canBoInitial = computed(() => perms.value.canBoInitial && p.value.status === 'supplemental_pending_bo')
const canSuppOcd = computed(() => perms.value.canOcdSign && p.value.status === 'supplemental_pending_ocd')
const canAssignNumber = computed(() => perms.value.canNumber && p.value.status === 'pending_procurement')
const canOcdSign = computed(() => perms.value.canOcdSign && p.value.status === 'pending_ocd')
const canReject = computed(() => (perms.value.canDcSign || perms.value.canNumber || perms.value.canOcdSign || perms.value.canBoInitial)
    && !['approved', 'rejected', 'draft'].includes(p.value.status))

// ── Create RFQ modal ───────────────────────────────────────────────────────
const showRfqModal = ref(false)
const rfqForm = useForm({
    procurement_id: props.procurement.id,
    rfq_date: new Date().toISOString().slice(0, 10),
    validity_days: 30,
    delivery_place: 'PSHS-CRC, Ampayon, Butuan City',
    payment_terms: '30 days after acceptance',
})
const canCreateRfq = computed(() =>
    p.value.status === 'approved' && perms.value.canCreateRfq && !(p.value.rfqs?.length)
)
const createRfq = () => {
    rfqForm.post(route('rfq.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showRfqModal.value = false
            router.reload({ preserveScroll: true })
        },
    })
}
</script>

<template>
    <Head :title="`PR — ${procurement.pr_no}`" />
    <AdminLayout :title="`PR — ${procurement.pr_no}`">

        <!-- Back + Print -->
        <div class="mb-5 flex items-center justify-between">
            <a :href="route('procurements.index')"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
                <ArrowLeftIcon class="w-4 h-4" />
                Back to Purchase Requests
            </a>
            <div class="flex items-center gap-2">
                <a v-if="p.has_market_study" :href="route('procurements.market-study', procurement.id)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors shadow-sm">
                    <DocumentArrowDownIcon class="w-4 h-4" />
                    Market Study
                </a>
                <a :href="route('procurements.print', procurement.id)" target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors shadow-sm">
                    <PrinterIcon class="w-4 h-4" />
                    Print / Save PDF
                </a>
            </div>
        </div>

        <!-- Flash -->
        <div v-if="flash?.success" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ flash.success }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Details -->
            <div class="lg:col-span-2 space-y-5">
                <!-- Status header card -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-bold text-slate-800 font-mono">{{ p.assigned_pr_number || p.pr_no }}</h2>
                                <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium', statusClass(p.status)]">
                                    {{ p.status_label || p.status }}
                                </span>
                                <span v-if="p.is_supplemental" class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-purple-100 text-purple-700">
                                    SUPPLEMENTAL
                                </span>
                            </div>
                            <p class="text-sm text-slate-500 mt-1">{{ formatDate(p.pr_date) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="canSubmit" @click="openAction('submit')"
                                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Submit for Approval
                            </button>
                            <button v-if="canDcSign" @click="openAction('dc_sign')"
                                class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Sign
                            </button>
                            <button v-if="canBoInitial" @click="openAction('bo_initial')"
                                class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Initial (Budget Officer)
                            </button>
                            <button v-if="canSuppOcd" @click="openAction('supp_ocd')"
                                class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Approve Supplemental
                            </button>
                            <button v-if="canAssignNumber" @click="openAction('assign_number')"
                                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                Assign PR Number
                            </button>
                            <button v-if="canOcdSign" @click="openAction('ocd_sign')"
                                class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <CheckIcon class="w-4 h-4" /> Approve
                            </button>
                            <button v-if="canReject" @click="openAction('reject')"
                                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                <XMarkIcon class="w-4 h-4" /> Reject
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Details card -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Details</h3>
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div>
                            <dt class="text-slate-500">Requested By</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ p.requester?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Division</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ p.division?.division_name || '—' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-500">Purpose</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ p.purpose }}</dd>
                        </div>
                        <div v-if="p.assigned_pr_number">
                            <dt class="text-slate-500">Official PR Number</dt>
                            <dd class="text-slate-800 font-mono font-medium mt-0.5">{{ p.assigned_pr_number }}</dd>
                        </div>
                        <div v-if="p.division_chief_remarks">
                            <dt class="text-slate-500">DC Remarks</dt>
                            <dd class="text-slate-800 font-medium mt-0.5">{{ p.division_chief_remarks }}</dd>
                        </div>
                        <div v-if="p.rejection_reason" class="col-span-2">
                            <dt class="text-red-500">Rejection Reason</dt>
                            <dd class="text-red-700 font-medium mt-0.5">{{ p.rejection_reason }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- RFQ Section -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Request for Quotation</h3>
                        <button v-if="canCreateRfq" @click="showRfqModal = true"
                            class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
                            <PlusIcon class="w-4 h-4" />
                            Create RFQ
                        </button>
                    </div>
                    <div v-if="(p.rfqs || []).length" class="space-y-2">
                        <a v-for="rfq in p.rfqs" :key="rfq.id" :href="route('rfq.show', rfq.id)"
                            class="flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors">
                            <div>
                                <span class="font-mono text-sm font-medium text-slate-800">{{ rfq.rfq_number }}</span>
                                <span :class="['ml-2 inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                    rfq.status === 'awarded' ? 'bg-emerald-100 text-emerald-700' :
                                    rfq.status === 'closed' ? 'bg-slate-100 text-slate-600' :
                                    rfq.status === 'open' ? 'bg-blue-100 text-blue-700' :
                                    'bg-amber-100 text-amber-700']">
                                    {{ rfq.status_label }}
                                </span>
                            </div>
                            <ArrowLeftIcon class="w-4 h-4 text-slate-400 rotate-180" />
                        </a>
                    </div>
                    <p v-else class="text-sm text-slate-400">
                        <span v-if="p.status === 'approved'">No RFQ created yet.</span>
                        <span v-else>RFQ can be created once this PR is approved.</span>
                    </p>
                </div>

                <!-- Items -->
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Items ({{ (p.items || []).length }})</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PPMP No</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Qty</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit Cost</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="it in (p.items || [])" :key="it.id" class="hover:bg-slate-50/60">
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ it.ppmp_line_item_no || '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ it.unit }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ it.description }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700 text-right">{{ it.quantity }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700 text-right">{{ Number(it.unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-800 font-medium text-right">
                                        {{ Number(it.unit_cost * it.quantity).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                                    </td>
                                </tr>
                                <tr v-if="!(p.items || []).length">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No items.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Timeline -->
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Approval Timeline</h3>
                    <ol class="space-y-4">
                        <li v-for="(step, i) in timelineSteps" :key="i" class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div :class="['w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0',
                                    step.rejected ? 'bg-red-100' : step.done ? 'bg-emerald-100' : step.pending ? 'bg-amber-100' : 'bg-slate-100']">
                                    <CheckIcon v-if="step.done && !step.rejected" class="w-4 h-4 text-emerald-600" />
                                    <XMarkIcon v-else-if="step.rejected" class="w-4 h-4 text-red-600" />
                                    <ClockIcon v-else-if="step.pending" class="w-4 h-4 text-amber-500" />
                                    <span v-else class="w-2 h-2 rounded-full bg-slate-300"></span>
                                </div>
                                <div v-if="i < timelineSteps.length - 1" class="w-0.5 flex-1 bg-slate-100 mt-1"></div>
                            </div>
                            <div class="pb-4">
                                <p :class="['text-sm font-medium', step.rejected ? 'text-red-700' : step.done ? 'text-slate-800' : step.pending ? 'text-amber-700' : 'text-slate-400']">
                                    {{ step.label }}
                                </p>
                                <p v-if="step.actor" class="text-xs text-slate-500 mt-0.5">{{ step.actor }}</p>
                                <p v-if="step.at" class="text-xs text-slate-400 mt-0.5">{{ formatDateTime(step.at) }}</p>
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
                        <span v-if="actionModal === 'dc_sign'">Sign Purchase Request</span>
                        <span v-else-if="actionModal === 'bo_initial'">Budget Officer Initial</span>
                        <span v-else-if="actionModal === 'supp_ocd'">Approve Supplemental Documents</span>
                        <span v-else-if="actionModal === 'assign_number'">Assign Official PR Number</span>
                        <span v-else-if="actionModal === 'ocd_sign'">Approve Purchase Request</span>
                        <span v-else-if="actionModal === 'reject'">Reject Purchase Request</span>
                        <span v-else-if="actionModal === 'submit'">Submit for Approval</span>
                    </h3>
                    <button @click="closeAction" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div v-if="['dc_sign', 'ocd_sign'].includes(actionModal)">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Remarks (optional)</label>
                        <textarea v-model="actionForm.remarks" rows="3"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                    <div v-if="actionModal === 'assign_number'">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Official PR Number <span class="text-red-500">*</span></label>
                        <input v-model="actionForm.pr_number"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="e.g. 2024-01-0001" />
                    </div>
                    <div v-if="actionModal === 'reject'">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Reason for Rejection <span class="text-red-500">*</span></label>
                        <textarea v-model="actionForm.reason" rows="3"
                            class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>
                    </div>
                    <div v-if="['bo_initial', 'supp_ocd', 'submit'].includes(actionModal)">
                        <p class="text-sm text-slate-600">Are you sure you want to proceed?</p>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="closeAction" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button @click="submitAction" :disabled="actionSubmitting"
                        :class="['inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-60 transition-colors',
                            actionModal === 'reject' ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700']">
                        {{ actionSubmitting ? 'Processing…' : 'Confirm' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Create RFQ Modal -->
        <div v-if="showRfqModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800">Create Request for Quotation</h3>
                    <button @click="showRfqModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">RFQ Date <span class="text-red-500">*</span></label>
                            <input v-model="rfqForm.rfq_date" type="date"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Validity (days) <span class="text-red-500">*</span></label>
                            <input v-model.number="rfqForm.validity_days" type="number" min="1" max="365"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Delivery Place</label>
                        <input v-model="rfqForm.delivery_place"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Payment Terms</label>
                        <input v-model="rfqForm.payment_terms"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <p v-if="rfqForm.errors?.procurement_id" class="text-xs text-red-600">{{ rfqForm.errors.procurement_id }}</p>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="showRfqModal = false" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button @click="createRfq" :disabled="rfqForm.processing"
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                        {{ rfqForm.processing ? 'Creating…' : 'Create RFQ' }}
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
