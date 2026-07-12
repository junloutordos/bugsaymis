<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
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
    draft:                { label: 'Draft',                     color: 'slate' },
    pending_procurement:  { label: 'Pending Procurement',       color: 'amber' },
    pending_ocd:          { label: 'Pending Campus Director',   color: 'blue'  },
    issued:               { label: 'Issued',                    color: 'green' },
    cancelled:            { label: 'Cancelled',                 color: 'red'   },
}
const statusBadge = (s) => STATUS_MAP[s] ?? { label: s, color: 'slate' }

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

const actionModalTitle = computed(() => ({
    submit: 'Submit for Procurement Review',
    review: 'Procurement Officer Action',
    sign:   'Campus Director — Sign & Issue',
    cancel: 'Cancel Purchase Order',
}[actionModal.value] ?? ''))

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
const removeItem = async (itemId) => {
    const confirmed = await confirmDelete('Remove this line item?')
    if (!confirmed) return
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
            <AppButton variant="secondary" size="sm" as="a" :href="route('po.print', po.id)" target="_blank">
                <PrinterIcon class="w-4 h-4" />
                Print / Save PDF
            </AppButton>
        </div>

        <!-- Flash -->
        <div v-if="flash?.success" class="mb-4 rounded-lg bg-success-50 border border-success-100 px-4 py-3 text-sm text-success-700">
            {{ flash.success }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Left: Details + Items ── -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Status header + action buttons -->
                <AppCard>
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-bold text-slate-800 font-mono">{{ o.po_number || `PO #${o.id}` }}</h2>
                                <AppBadge :color="statusBadge(o.status).color">{{ statusBadge(o.status).label }}</AppBadge>
                            </div>
                            <p v-if="o.pr" class="text-sm text-slate-500 mt-1">
                                Linked PR: <span class="font-mono text-indigo-600">{{ o.pr.pr_no }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton v-if="canSubmit" @click="openAction('submit')">
                                Submit for Review
                            </AppButton>
                            <AppButton v-if="canReview" variant="success" @click="openAction('review')">
                                <CheckIcon class="w-4 h-4" /> Procurement Review
                            </AppButton>
                            <AppButton v-if="canSign" variant="success" @click="openAction('sign')">
                                <CheckIcon class="w-4 h-4" /> Sign & Issue
                            </AppButton>
                            <AppButton v-if="canCancel" variant="danger" @click="openAction('cancel')">
                                <XMarkIcon class="w-4 h-4" /> Cancel PO
                            </AppButton>
                        </div>
                    </div>
                </AppCard>

                <!-- PO Details -->
                <AppCard>
                    <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">Purchase Order Details</h3>
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
                </AppCard>

                <!-- Supplier Info -->
                <AppCard>
                    <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">Supplier Information</h3>
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
                </AppCard>

                <!-- Line Items -->
                <AppCard :padded="false" title="Line Items">
                    <template #header>
                        <AppButton v-if="canEdit && !showAddItem" size="sm" @click="showAddItem = true">
                            <PlusIcon class="w-3.5 h-3.5" /> Add Item
                        </AppButton>
                    </template>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100">
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
                                    <AppIconButton label="Remove item" variant="danger" size="sm"
                                        @click="removeItem(item.id)" :disabled="removingItem === item.id">
                                        <TrashIcon class="w-3.5 h-3.5" />
                                    </AppIconButton>
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
                                        <p v-if="itemForm.errors.description" class="text-danger-600 text-xs mt-0.5">{{ itemForm.errors.description }}</p>
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
                                        <AppIconButton label="Cancel add item" size="sm" @click="showAddItem = false">
                                            <XMarkIcon class="w-3.5 h-3.5" />
                                        </AppIconButton>
                                    </td>
                                </tr>
                                <tr class="bg-indigo-50">
                                    <td :colspan="7" class="pb-3 px-3">
                                        <div class="flex gap-2 justify-end">
                                            <AppButton variant="secondary" size="sm" @click="showAddItem = false; itemForm.reset()">Cancel</AppButton>
                                            <AppButton size="sm" :loading="itemForm.processing" @click="submitAddItem">
                                                Add Item
                                            </AppButton>
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
                </AppCard>

                <!-- Linked ORS -->
                <AppCard v-if="(o.ors_list?.length ?? 0) > 0" title="Linked Obligation Requests">
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
                </AppCard>

            </div>

            <!-- ── Right: Timeline + Procurement Officer Remarks ── -->
            <div class="space-y-5">

                <AppCard>
                    <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">Approval Timeline</h3>
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
                                <p v-if="step.returnInfo" class="text-xs text-danger-500 mt-0.5 italic">{{ step.returnInfo }}</p>
                                <p v-else-if="step.pending" class="text-xs text-warning-500 mt-0.5">Awaiting action</p>
                            </div>
                        </li>
                    </ol>
                </AppCard>

                <div v-if="o.procurement_officer_remarks" class="bg-warning-50 rounded-xl border border-warning-100 p-4">
                    <h3 class="text-xs font-semibold text-warning-700 uppercase tracking-wide mb-2">Procurement Officer Remarks</h3>
                    <p class="text-sm text-warning-700">{{ o.procurement_officer_remarks }}</p>
                </div>

            </div>
        </div>

        <!-- ── Action Modal ── -->
        <AppModal :show="!!actionModal" :title="actionModalTitle" size="sm" @close="closeAction">
            <div class="space-y-4">
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
                                <input type="radio" v-model="actionForm.action" value="return" class="text-danger-600 focus:ring-danger-500" />
                                Return
                            </label>
                        </div>
                    </div>
                    <AppTextarea v-model="actionForm.remarks" :rows="2" label="Remarks" />
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
                                <input type="radio" v-model="actionForm.action" value="return" class="text-danger-600 focus:ring-danger-500" />
                                Return to Procurement
                            </label>
                        </div>
                    </div>
                    <AppTextarea v-model="actionForm.remarks" :rows="2" label="Remarks (optional)" />
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

            <template #footer>
                <AppButton variant="secondary" @click="closeAction">Cancel</AppButton>
                <AppButton :variant="actionModal === 'cancel' ? 'danger' : 'primary'"
                    :loading="actionSubmitting" :disabled="actionSubmitting" @click="submitAction">
                    {{ actionSubmitting ? 'Processing…' : 'Confirm' }}
                </AppButton>
            </template>
        </AppModal>

    </AdminLayout>
</template>
