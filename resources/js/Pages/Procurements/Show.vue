<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppTable from '@/Components/AppTable.vue'
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
    if (!status || status === 'approved') return 'green'
    if (status === 'rejected') return 'red'
    if (status === 'draft') return 'slate'
    return 'amber'
}

const rfqStatusColor = (status) => {
    if (status === 'awarded') return 'green'
    if (status === 'closed') return 'slate'
    if (status === 'open') return 'blue'
    return 'amber'
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

const actionModalTitles = {
    dc_sign: 'Sign Purchase Request',
    bo_initial: 'Budget Officer Initial',
    supp_ocd: 'Approve Supplemental Documents',
    assign_number: 'Assign Official PR Number',
    ocd_sign: 'Approve Purchase Request',
    reject: 'Reject Purchase Request',
    submit: 'Submit for Approval',
}
const actionModalTitle = computed(() => actionModalTitles[actionModal.value] ?? '')

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
                <AppButton v-if="p.has_market_study" as="a" :href="route('procurements.market-study', procurement.id)"
                    variant="secondary" size="sm">
                    <DocumentArrowDownIcon class="w-4 h-4" />
                    Market Study
                </AppButton>
                <AppButton as="a" :href="route('procurements.print', procurement.id)" target="_blank"
                    variant="secondary" size="sm">
                    <PrinterIcon class="w-4 h-4" />
                    Print / Save PDF
                </AppButton>
            </div>
        </div>

        <!-- Flash -->
        <div v-if="flash?.success" class="mb-4 rounded-lg bg-success-50 border border-success-100 px-4 py-3 text-sm text-success-700">
            {{ flash.success }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Details -->
            <div class="lg:col-span-2 space-y-5">
                <!-- Status header card -->
                <AppCard>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-bold text-slate-800 font-mono">{{ p.assigned_pr_number || p.pr_no }}</h2>
                                <AppBadge :color="statusClass(p.status)">{{ p.status_label || p.status }}</AppBadge>
                                <AppBadge v-if="p.is_supplemental" color="purple">SUPPLEMENTAL</AppBadge>
                            </div>
                            <p class="text-sm text-slate-500 mt-1">{{ formatDate(p.pr_date) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton v-if="canSubmit" @click="openAction('submit')">
                                Submit for Approval
                            </AppButton>
                            <AppButton v-if="canDcSign" variant="success" @click="openAction('dc_sign')">
                                <CheckIcon class="w-4 h-4" /> Sign
                            </AppButton>
                            <AppButton v-if="canBoInitial" variant="success" @click="openAction('bo_initial')">
                                <CheckIcon class="w-4 h-4" /> Initial (Budget Officer)
                            </AppButton>
                            <AppButton v-if="canSuppOcd" variant="success" @click="openAction('supp_ocd')">
                                <CheckIcon class="w-4 h-4" /> Approve Supplemental
                            </AppButton>
                            <AppButton v-if="canAssignNumber" @click="openAction('assign_number')">
                                Assign PR Number
                            </AppButton>
                            <AppButton v-if="canOcdSign" variant="success" @click="openAction('ocd_sign')">
                                <CheckIcon class="w-4 h-4" /> Approve
                            </AppButton>
                            <AppButton v-if="canReject" variant="danger" @click="openAction('reject')">
                                <XMarkIcon class="w-4 h-4" /> Reject
                            </AppButton>
                        </div>
                    </div>
                </AppCard>

                <!-- Details card -->
                <AppCard title="Details">
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
                            <dt class="text-danger-500">Rejection Reason</dt>
                            <dd class="text-danger-700 font-medium mt-0.5">{{ p.rejection_reason }}</dd>
                        </div>
                    </dl>
                </AppCard>

                <!-- RFQ Section -->
                <AppCard title="Request for Quotation">
                    <template #header>
                        <AppButton v-if="canCreateRfq" size="sm" @click="showRfqModal = true">
                            <PlusIcon class="w-4 h-4" />
                            Create RFQ
                        </AppButton>
                    </template>
                    <div v-if="(p.rfqs || []).length" class="space-y-2">
                        <a v-for="rfq in p.rfqs" :key="rfq.id" :href="route('rfq.show', rfq.id)"
                            class="flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors">
                            <div>
                                <span class="font-mono text-sm font-medium text-slate-800">{{ rfq.rfq_number }}</span>
                                <AppBadge :color="rfqStatusColor(rfq.status)" class="ml-2">{{ rfq.status_label }}</AppBadge>
                            </div>
                            <ArrowLeftIcon class="w-4 h-4 text-slate-400 rotate-180" />
                        </a>
                    </div>
                    <p v-else class="text-sm text-slate-400">
                        <span v-if="p.status === 'approved'">No RFQ created yet.</span>
                        <span v-else>RFQ can be created once this PR is approved.</span>
                    </p>
                </AppCard>

                <!-- Items -->
                <AppCard :title="`Items (${(p.items || []).length})`" :padded="false">
                    <AppTable :is-empty="!(p.items || []).length" :skeleton-cols="6" :card="false">
                        <template #head>
                            <tr>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">PPMP No</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Unit</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Description</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Qty</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Unit Cost</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total</th>
                            </tr>
                        </template>

                        <tr v-for="it in (p.items || [])" :key="it.id" class="hover:bg-indigo-50/40">
                            <td class="px-4 py-3 text-sm text-slate-600">{{ it.ppmp_line_item_no || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ it.unit }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ it.description }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 text-right">{{ it.quantity }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 text-right">{{ Number(it.unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-800 font-medium text-right">
                                {{ Number(it.unit_cost * it.quantity).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                            </td>
                        </tr>

                        <template #empty>
                            <p class="text-sm text-slate-400">No items.</p>
                        </template>
                    </AppTable>
                </AppCard>
            </div>

            <!-- Right: Timeline -->
            <div class="space-y-4">
                <AppCard title="Approval Timeline">
                    <ol class="space-y-4">
                        <li v-for="(step, i) in timelineSteps" :key="i" class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div :class="['w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0',
                                    step.rejected ? 'bg-danger-100' : step.done ? 'bg-success-100' : step.pending ? 'bg-warning-100' : 'bg-slate-100']">
                                    <CheckIcon v-if="step.done && !step.rejected" class="w-4 h-4 text-success-600" />
                                    <XMarkIcon v-else-if="step.rejected" class="w-4 h-4 text-danger-600" />
                                    <ClockIcon v-else-if="step.pending" class="w-4 h-4 text-warning-500" />
                                    <span v-else class="w-2 h-2 rounded-full bg-slate-300"></span>
                                </div>
                                <div v-if="i < timelineSteps.length - 1" class="w-0.5 flex-1 bg-slate-100 mt-1"></div>
                            </div>
                            <div class="pb-4">
                                <p :class="['text-sm font-medium', step.rejected ? 'text-danger-700' : step.done ? 'text-slate-800' : step.pending ? 'text-warning-700' : 'text-slate-400']">
                                    {{ step.label }}
                                </p>
                                <p v-if="step.actor" class="text-xs text-slate-500 mt-0.5">{{ step.actor }}</p>
                                <p v-if="step.at" class="text-xs text-slate-400 mt-0.5">{{ formatDateTime(step.at) }}</p>
                                <p v-else-if="step.pending" class="text-xs text-warning-500 mt-0.5">Awaiting action</p>
                            </div>
                        </li>
                    </ol>
                </AppCard>
            </div>
        </div>

        <!-- Action Modal -->
        <AppModal :show="!!actionModal" :title="actionModalTitle" @close="closeAction">
            <div class="space-y-4">
                <AppTextarea v-if="['dc_sign', 'ocd_sign'].includes(actionModal)"
                    v-model="actionForm.remarks" :rows="3" label="Remarks (optional)" />
                <AppInput v-if="actionModal === 'assign_number'"
                    v-model="actionForm.pr_number" label="Official PR Number" required placeholder="e.g. 2024-01-0001" />
                <AppTextarea v-if="actionModal === 'reject'"
                    v-model="actionForm.reason" :rows="3" label="Reason for Rejection" required />
                <p v-if="['bo_initial', 'supp_ocd', 'submit'].includes(actionModal)" class="text-sm text-slate-600">
                    Are you sure you want to proceed?
                </p>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="closeAction">Cancel</AppButton>
                <AppButton :variant="actionModal === 'reject' ? 'danger' : 'primary'" :loading="actionSubmitting"
                    :disabled="actionSubmitting" @click="submitAction">
                    {{ actionSubmitting ? 'Processing…' : 'Confirm' }}
                </AppButton>
            </template>
        </AppModal>

        <!-- Create RFQ Modal -->
        <AppModal :show="showRfqModal" title="Create Request for Quotation" size="lg" @close="showRfqModal = false">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <AppInput v-model="rfqForm.rfq_date" type="date" label="RFQ Date" required />
                    <AppInput v-model.number="rfqForm.validity_days" type="number" min="1" max="365" label="Validity (days)" required />
                </div>
                <AppInput v-model="rfqForm.delivery_place" label="Delivery Place" />
                <AppInput v-model="rfqForm.payment_terms" label="Payment Terms" />
                <p v-if="rfqForm.errors?.procurement_id" class="text-xs text-danger-600">{{ rfqForm.errors.procurement_id }}</p>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="showRfqModal = false">Cancel</AppButton>
                <AppButton :loading="rfqForm.processing" :disabled="rfqForm.processing" @click="createRfq">
                    {{ rfqForm.processing ? 'Creating…' : 'Create RFQ' }}
                </AppButton>
            </template>
        </AppModal>

    </AdminLayout>
</template>
