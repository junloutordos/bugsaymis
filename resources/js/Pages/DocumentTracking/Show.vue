<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBreadcrumb from '@/Components/AppBreadcrumb.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTabs from '@/Components/AppTabs.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import axios from 'axios'
import { confirmAction } from '@/Composables/useConfirm.js'
import { userDisplayName } from '@/Utils/userDisplay.js'
import { badgeBase, statusBadgeClass, priorityBadgeClass, originBadgeClass, routingStatusBadgeClass } from '@/Composables/useStatusBadge.js'
import {
  LockClosedIcon, ExclamationTriangleIcon,
  CheckCircleIcon, ClockIcon, ArrowRightIcon,
  PaperClipIcon, PencilSquareIcon, EyeIcon, DocumentCheckIcon,
  UserIcon, ArrowTopRightOnSquareIcon, MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  document:              Object,
  users:                 Array,
  documentTypes:         Array,
  isAdmin:               Boolean,
  currentUserId:         Number,
  routingChain:          Array,
  originalSender:        Object,
  latestActionTaker:     Object,
  canCompleteAsReceiver: Boolean,
  canCompleteAndFile:    Boolean,
})

const page = usePage()
const uid  = computed(() => props.currentUserId ?? page.props.auth?.user?.id)

const breadcrumbItems = computed(() => [
  { label: 'Document Tracking', href: route('document-tracking.index') },
  { label: props.document.tracking_no },
])

// ── Active routing step for current user ──────────────────────────────────
const myActiveRouting = computed(() =>
  props.document.routings?.find(r =>
    r.receiver?.id === uid.value &&
    ['Pending', 'Received'].includes(r.status)
  )
)

const isCompleted   = computed(() => props.document.overall_status === 'Completed')
const isPending     = computed(() => myActiveRouting.value?.status === 'Pending')

// ── Edit document details ─────────────────────────────────────────────────
const canEdit = computed(() =>
  !isCompleted.value &&
  (props.isAdmin || props.document.creator?.id === uid.value)
)

const editOpen      = ref(false)
const editSubmitting = ref(false)
const editErrors    = ref({})
const editForm      = ref({})

function openEditModal() {
  const d = props.document
  editForm.value = {
    document_type_id: d.document_type?.id ?? '',
    subject:          d.subject ?? '',
    description:      d.description ?? '',
    priority:         d.priority ?? 'Normal',
    urgency:          d.urgency ?? 'Normal',
    is_confidential:  d.is_confidential ?? false,
    deadline_at:      d.deadline_at ? d.deadline_at.slice(0, 16) : '',
    source_office:    d.source_office ?? '',
    sender_name:      d.sender_name ?? '',
    date_of_document: d.date_of_document ?? '',
    date_received:    d.date_received ?? '',
    document_number:  d.document_number ?? '',
  }
  editErrors.value = {}
  editOpen.value   = true
}

const availableEditTypes = computed(() =>
  (props.documentTypes ?? []).filter(t =>
    t.applicable_to === props.document.origin_type || t.applicable_to === 'both'
  )
)

function submitEdit() {
  editSubmitting.value = true
  editErrors.value     = {}
  router.put(route('document-tracking.update', props.document.id), { ...editForm.value }, {
    onSuccess: () => { editOpen.value = false },
    onError:   e  => { editErrors.value = e },
    onFinish:  () => { editSubmitting.value = false },
    preserveScroll: true,
  })
}

// ── Review & Process modal ────────────────────────────────────────────────
const reviewOpen       = ref(false)
const reviewTab        = ref('forward')  // 'forward' | 'return' | 'complete'
const reviewSubmitting = ref(false)
const reviewErrors     = ref({})
const reviewForm       = ref({
  action_taken:              '',
  remarks:                   '',
  return_target:             'original',
  return_target_routing_id:  null,
  return_reason:             '',
  forward_to:                null,
  instructions:              '',
  completion_notes:          '',
})

// Decision tabs shown in the Review & Process modal
const reviewTabs = computed(() => [
  { key: 'forward', label: 'Forward' },
  { key: 'return',  label: 'Return to Sender' },
  ...(props.canCompleteAsReceiver ? [{ key: 'complete', label: 'Complete' }] : []),
])

// User search for forward-to field
const forwardSearch = ref('')
const filteredUsers = computed(() => {
  const q = forwardSearch.value.toLowerCase()
  return (props.users ?? []).filter(u => {
    const displayName = userDisplayName(u, props.users).toLowerCase()
    return u.id !== uid.value && (!q || displayName.includes(q) || u.name.toLowerCase().includes(q) || (u.email ?? '').toLowerCase().includes(q))
  })
})

function openReviewModal() {
  reviewOpen.value = true
  reviewTab.value  = 'forward'
  reviewForm.value = {
    action_taken:             '',
    remarks:                  '',
    return_target:            props.latestActionTaker ? 'latest_action_taker' : 'original',
    return_target_routing_id: null,
    return_reason:            '',
    forward_to:               null,
    instructions:             '',
    completion_notes:         '',
  }
  forwardSearch.value = ''
  reviewErrors.value  = {}
}

function closeReviewModal() { reviewOpen.value = false }

async function confirmCompleteAndFile() {
  return confirmAction({
    title: 'Mark complete and file?',
    text: 'This will close the document process and mark the document as filed.',
    confirmText: 'Yes, complete and file',
    icon: 'warning',
  })
}

async function doReview() {
  if (reviewTab.value === 'complete' && !await confirmCompleteAndFile()) return

  reviewSubmitting.value = true
  reviewErrors.value     = {}

  const payload = {
    decision:     reviewTab.value,
    action_taken: reviewForm.value.action_taken || null,
    remarks:      reviewForm.value.remarks || null,
  }

  if (reviewTab.value === 'return') {
    payload.return_target  = reviewForm.value.return_target
    if (reviewForm.value.return_target === 'step') {
      payload.return_target_routing_id = reviewForm.value.return_target_routing_id
    }
    payload.return_reason = reviewForm.value.return_reason
  } else if (reviewTab.value === 'forward') {
    payload.forward_to   = reviewForm.value.forward_to
    payload.instructions = reviewForm.value.instructions
  } else {
    payload.completion_notes = reviewForm.value.completion_notes
  }

  router.post(route('document-tracking.review', myActiveRouting.value.id), payload, {
    onSuccess: closeReviewModal,
    onError:   e  => { reviewErrors.value = e },
    onFinish:  () => { reviewSubmitting.value = false },
    preserveScroll: true,
  })
}

// ── Acknowledge (with auto-preview) ──────────────────────────────────────
const acknowledging = ref(false)

function doReceive() {
  acknowledging.value = true
  router.post(route('document-tracking.receive', myActiveRouting.value.id), {}, {
    onSuccess: () => {
      const first = props.document.attachments?.find(a => a.has_preview)
      if (first) openPreview(first)
    },
    onFinish:       () => { acknowledging.value = false },
    preserveScroll: true,
  })
}

// ── Scan preview ──────────────────────────────────────────────────────────
const previewAtt     = ref(null)
const previewLoading = ref(false)
const previewUrl     = ref(null)
const previewMime    = ref('')

async function openPreview(att) {
  if (!att.gdrive_file_id) return
  previewAtt.value     = att
  previewLoading.value = true
  previewUrl.value     = null
  try {
    const res = await axios.get(route('document-tracking.scan', { document: props.document.id, attachment: att.id }), {
      responseType: 'blob',
    })
    previewMime.value = res.headers['content-type'] ?? att.mime_type
    previewUrl.value  = URL.createObjectURL(res.data)
  } catch (e) {
    alert('Could not load scan from Google Drive. Try opening directly.')
  } finally {
    previewLoading.value = false
  }
}

function closePreview() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = null
  previewAtt.value = null
}

// ── Action modals ─────────────────────────────────────────────────────────
const modal       = ref(null) // 'receive'|'annotate'|'act'|'forward'|'return'|'complete'
const modalForm   = ref({})
const submitting  = ref(false)
const modalErrors = ref({})

function openModal(type, defaults = {}) {
  modal.value      = type
  modalForm.value  = { ...defaults }
  modalErrors.value = {}
}
function closeModal() { modal.value = null }

function submit(routeKey, params, body) {
  submitting.value  = true
  modalErrors.value = {}
  router.post(route(routeKey, params), body, {
    onSuccess: closeModal,
    onError:   e  => { modalErrors.value = e },
    onFinish:  () => { submitting.value = false },
    preserveScroll: true,
  })
}

function doAnnotate() { submit('document-tracking.annotate', props.document.id, { remarks: modalForm.value.remarks }) }
async function doComplete() {
  if (!await confirmCompleteAndFile()) return

  submit('document-tracking.complete', props.document.id, { action_taken: modalForm.value.action_taken })
}

// ── Helpers ────────────────────────────────────────────────────────────────
function fmtDt(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
}
function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
function fmtSize(b) {
  if (!b) return ''
  if (b < 1024)        return b + ' B'
  if (b < 1048576)     return (b / 1024).toFixed(1) + ' KB'
  return (b / 1048576).toFixed(1) + ' MB'
}
function duration(from, to) {
  if (!from || !to) return null
  const mins = Math.round((new Date(to) - new Date(from)) / 60000)
  if (mins < 60)   return `${mins}m`
  if (mins < 1440) return `${Math.round(mins / 60)}h`
  return `${Math.round(mins / 1440)}d`
}

function timelineNodeCls(r) {
  if (r.status === 'Queued')       return 'bg-slate-100 border-slate-200 text-slate-400'
  if (r.status === 'Returned')     return 'bg-red-100 border-red-300 text-red-700'
  if (r.status === 'Action Taken') return 'bg-emerald-100 border-emerald-300 text-emerald-700'
  if (r.status === 'Forwarded')    return 'bg-blue-100 border-blue-300 text-blue-700'
  if (r.status === 'Received')     return 'bg-indigo-100 border-indigo-300 text-indigo-700'
  if (r.is_overdue)                return 'bg-red-100 border-red-300 text-red-700'
  return 'bg-amber-100 border-amber-300 text-amber-700'
}
function timelineCardCls(r) {
  if (r.receiver?.id === uid.value && ['Pending','Received'].includes(r.status))
    return 'border-indigo-300 bg-indigo-50/40 ring-1 ring-indigo-200'
  if (r.is_overdue && ['Pending','Received'].includes(r.status))
    return 'border-red-200 bg-red-50/40'
  if (r.status === 'Queued')       return 'border-slate-100 bg-slate-50/50 opacity-60'
  return 'border-slate-100 bg-white'
}
function statusLabel(r) {
  if (r.is_overdue && ['Pending','Received'].includes(r.status)) return 'Overdue'
  return r.status
}
// statusBadgeCls → routingStatusBadgeClass (from useStatusBadge.js)
const overallBadgeCls = computed(() => {
  const s = props.document.overall_status
  if (s === 'Completed') return 'bg-emerald-100 text-emerald-700'
  if (s === 'Returned')  return 'bg-red-100 text-red-700'
  if (props.document.routings?.some(r => r.is_overdue)) return 'bg-red-100 text-red-700'
  return 'bg-blue-100 text-blue-700'
})
</script>

<template>
  <Head :title="`${document.tracking_no} — Document Tracking`" />
  <AdminLayout :title="document.tracking_no">
    <div class="space-y-5 max-w-5xl">

      <AppBreadcrumb :items="breadcrumbItems" />

      <!-- ── Document Header Card ─────────────────────────────────────────── -->
      <AppCard>
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
          <div class="flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-2">
              <span class="font-mono text-sm font-bold text-indigo-700">{{ document.tracking_no }}</span>
              <span :class="[badgeBase, originBadgeClass(document.origin_type)]">
                {{ document.origin_type === 'external' ? 'External Incoming' : 'Internal' }}
              </span>
              <span :class="[badgeBase, priorityBadgeClass(document.priority)]">
                {{ document.priority }}
              </span>
              <AppBadge v-if="document.is_confidential" color="purple" class="gap-1">
                <LockClosedIcon class="h-3 w-3" /> Confidential
              </AppBadge>
              <span :class="[badgeBase, overallBadgeCls]">
                {{ document.overall_status }}
              </span>
            </div>
            <h1 class="text-base font-bold text-slate-800">{{ document.subject }}</h1>
            <p v-if="document.description" class="text-sm text-slate-500 mt-1">{{ document.description }}</p>
          </div>
          <div class="text-xs text-slate-500 text-right shrink-0 space-y-0.5">
            <div>Filed: <strong>{{ fmtDate(document.created_at) }}</strong></div>
            <div>By: <strong class="text-slate-700">{{ document.creator?.name }}</strong></div>
            <div v-if="document.completed_at">Completed: <strong>{{ fmtDate(document.completed_at) }}</strong></div>
            <AppButton v-if="canEdit" variant="secondary" size="sm" class="mt-2" @click="openEditModal()">
              <PencilSquareIcon class="h-3.5 w-3.5" /> Edit Details
            </AppButton>
          </div>
        </div>

        <!-- Metadata grid -->
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Type</p>
            <p class="font-medium text-slate-700">{{ document.document_type?.name ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Current Holder</p>
            <p class="font-medium text-slate-700">{{ document.current_holder?.name ?? '— Completed' }}</p>
          </div>
          <div v-if="document.deadline_at">
            <p class="text-xs text-slate-400 mb-0.5">Deadline</p>
            <p class="font-semibold text-red-600">{{ fmtDt(document.deadline_at) }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Routing Mode</p>
            <p class="font-medium text-slate-700 capitalize">{{ document.document_type?.routing_type ?? '—' }}</p>
          </div>
        </div>

        <!-- External doc details -->
        <div v-if="document.origin_type === 'external'" class="mt-4 bg-green-50 border border-green-100 rounded-lg p-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Source Office</p>
            <p class="font-medium text-slate-700">{{ document.source_office ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Sender</p>
            <p class="font-medium text-slate-700">{{ document.sender_name ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Document Date</p>
            <p class="font-medium text-slate-700">{{ fmtDate(document.date_of_document) }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Date Received</p>
            <p class="font-medium text-slate-700">{{ fmtDate(document.date_received) }}</p>
          </div>
          <div v-if="document.document_number">
            <p class="text-xs text-slate-400 mb-0.5">Ref. / Control No.</p>
            <p class="font-medium text-slate-700">{{ document.document_number }}</p>
          </div>
        </div>

        <!-- Action buttons for current receiver -->
        <div v-if="myActiveRouting && !isCompleted" class="mt-4 pt-4 border-t border-slate-100 space-y-2">
          <div class="flex flex-wrap gap-2">
            <!-- Acknowledge Receipt — only when Pending -->
            <AppButton v-if="isPending" :loading="acknowledging" :disabled="acknowledging" @click="doReceive()">
              <CheckCircleIcon class="h-4 w-4" />
              {{ acknowledging ? 'Acknowledging…' : 'Acknowledge Receipt' }}
            </AppButton>

            <!-- Review & Process — primary action, disabled until acknowledged -->
            <AppButton
              :disabled="isPending"
              :title="isPending ? 'Acknowledge receipt first' : ''"
              @click="!isPending && openReviewModal()">
              <DocumentCheckIcon class="h-4 w-4" /> Review & Process
            </AppButton>

            <!-- Add Note — disabled until acknowledged -->
            <AppButton
              variant="secondary"
              :disabled="isPending"
              :title="isPending ? 'Acknowledge receipt first' : ''"
              @click="!isPending && openModal('annotate')">
              <PencilSquareIcon class="h-4 w-4" /> Add Note
            </AppButton>
          </div>

          <!-- Hint shown while status is Pending -->
          <p v-if="isPending" class="text-xs text-amber-600 flex items-center gap-1">
            <ExclamationTriangleIcon class="h-3.5 w-3.5 shrink-0" />
            Acknowledge receipt first to enable further actions.
          </p>
        </div>

        <!-- Mark Complete & File — admin or terminal/manual receiver -->
        <div v-if="canCompleteAndFile" class="mt-3 flex justify-end">
          <AppButton variant="secondary" @click="openModal('complete')">
            <CheckCircleIcon class="h-4 w-4 text-emerald-500" /> Mark Complete & File
          </AppButton>
        </div>
      </AppCard>

      <!-- ── Attachments / Scans ──────────────────────────────────────────── -->
      <AppCard v-if="document.attachments?.length">
        <h2 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-1.5">
          <PaperClipIcon class="h-4 w-4 text-slate-400" /> Scans & Attachments
        </h2>
        <ul class="divide-y divide-slate-100">
          <li v-for="att in document.attachments" :key="att.id"
            class="flex items-center justify-between py-2.5 gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
              <span class="text-lg shrink-0">{{ (att.mime_type ?? '').includes('pdf') ? '📋' : '🖼️' }}</span>
              <div class="min-w-0">
                <p class="text-sm font-medium text-slate-800 truncate">{{ att.file_name }}</p>
                <p class="text-xs text-slate-400">{{ fmtSize(att.file_size) }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <AppButton v-if="att.has_preview" variant="secondary" size="sm" @click="openPreview(att)">
                <EyeIcon class="h-3.5 w-3.5" /> Preview
              </AppButton>
              <AppButton v-if="att.gdrive_link" as="a" :href="att.gdrive_link" target="_blank" variant="secondary" size="sm">
                <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" /> Google Drive
              </AppButton>
            </div>
          </li>
        </ul>
      </AppCard>

      <!-- ── Routing Timeline ────────────────────────────────────────────── -->
      <AppCard title="Routing Timeline">
        <div class="relative">
          <!-- Vertical line -->
          <div class="absolute left-5 top-4 bottom-4 w-px bg-slate-200" />

          <div class="space-y-3">
            <div v-for="r in document.routings" :key="r.id" class="relative flex gap-4">

              <!-- Node circle -->
              <div class="relative z-10 shrink-0">
                <div class="w-10 h-10 rounded-full border-2 flex items-center justify-center text-xs font-bold"
                  :class="timelineNodeCls(r)">
                  {{ r.status === 'Action Taken' || r.status === 'Forwarded' ? '✓' : r.sequence }}
                </div>
                <!-- Pulse for active step -->
                <span v-if="r.receiver?.id === uid && ['Pending','Received'].includes(r.status)"
                  class="absolute inset-0 rounded-full animate-ping bg-indigo-300 opacity-50" />
              </div>

              <!-- Card -->
              <div class="flex-1 rounded-xl border p-4 mb-1 transition-all" :class="timelineCardCls(r)">
                <!-- Top row -->
                <div class="flex items-center justify-between flex-wrap gap-2">
                  <div class="flex items-center gap-2 text-sm flex-wrap">
                    <span class="text-slate-500 flex items-center gap-1 text-xs">
                      <UserIcon class="h-3.5 w-3.5" /> {{ r.sender?.name ?? '—' }}
                    </span>
                    <ArrowRightIcon class="h-3.5 w-3.5 text-slate-300" />
                    <span class="font-semibold text-slate-800 flex items-center gap-1 text-xs">
                      <UserIcon class="h-3.5 w-3.5 text-indigo-400" /> {{ r.receiver?.name ?? '—' }}
                    </span>
                    <AppBadge v-if="r.receiver?.id === uid && ['Pending','Received'].includes(r.status)" color="indigo">YOU</AppBadge>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <ExclamationTriangleIcon v-if="r.is_overdue && ['Pending','Received'].includes(r.status)"
                      class="h-3.5 w-3.5 text-red-500" />
                    <span :class="[badgeBase, routingStatusBadgeClass(r)]">
                      {{ statusLabel(r) }}
                    </span>
                  </div>
                </div>

                <!-- Timestamps -->
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-400">
                  <span v-if="r.due_at" :class="r.is_overdue ? 'text-red-500 font-semibold' : ''">
                    <ClockIcon class="inline h-3 w-3" /> Due: {{ fmtDt(r.due_at) }}
                  </span>
                  <span v-if="r.received_at">✓ Received: {{ fmtDt(r.received_at) }}</span>
                  <span v-if="r.action_taken_at">
                    ✓ Acted: {{ fmtDt(r.action_taken_at) }}
                    <span v-if="duration(r.received_at ?? r.created_at, r.action_taken_at)" class="text-slate-300">
                      ({{ duration(r.received_at ?? r.created_at, r.action_taken_at) }})
                    </span>
                  </span>
                  <span v-if="r.reviewed_at" class="text-violet-500">⊙ Reviewed: {{ fmtDt(r.reviewed_at) }}</span>
                  <span v-if="r.forwarded_at">↪ Forwarded: {{ fmtDt(r.forwarded_at) }}</span>
                  <span v-if="r.returned_at" class="text-red-400">↩ Returned: {{ fmtDt(r.returned_at) }}</span>
                </div>

                <!-- Instructions -->
                <div v-if="r.instructions" class="mt-2 text-xs bg-indigo-50 text-indigo-800 rounded-lg px-3 py-2">
                  <strong>Instructions:</strong> {{ r.instructions }}
                </div>

                <!-- Remarks -->
                <div v-if="r.remarks" class="mt-2 text-xs bg-slate-50 text-slate-600 rounded-lg px-3 py-2">
                  <strong>Remarks:</strong> {{ r.remarks }}
                </div>

                <!-- Action taken -->
                <div v-if="r.action_taken" class="mt-2 text-xs bg-emerald-50 text-emerald-800 rounded-lg px-3 py-2">
                  <strong>Action Taken:</strong> {{ r.action_taken }}
                </div>

                <!-- Return reason -->
                <div v-if="r.return_reason" class="mt-2 text-xs bg-red-50 text-red-700 rounded-lg px-3 py-2">
                  <strong>Return Reason:</strong> {{ r.return_reason }}
                </div>
              </div>
            </div>

            <!-- Completion node -->
            <div v-if="isCompleted" class="relative flex gap-4">
              <div class="relative z-10 shrink-0">
                <div class="w-10 h-10 rounded-full border-2 bg-emerald-100 border-emerald-300 flex items-center justify-center">
                  <CheckCircleIcon class="h-5 w-5 text-emerald-600" />
                </div>
              </div>
              <div class="flex-1 rounded-xl border border-emerald-200 bg-emerald-50 p-3 mb-1">
                <p class="text-sm font-semibold text-emerald-700">Document Completed & Filed</p>
                <p class="text-xs text-emerald-600 mt-0.5">{{ fmtDt(document.completed_at) }}</p>
              </div>
            </div>
          </div>
        </div>
      </AppCard>

    </div>

    <!-- ── Review Document Modal ─────────────────────────────────────────── -->
    <AppModal :show="reviewOpen" title="Review Document" size="lg" body-class="px-6 py-5 space-y-4" @close="closeReviewModal">
      <AppTabs :tabs="reviewTabs" v-model="reviewTab">

        <!-- Action notes (shared across all tabs) -->
        <div class="space-y-3 pb-3 border-b border-slate-100">
          <AppTextarea v-model="reviewForm.action_taken" :rows="2" label="Action Taken (optional)"
            placeholder="What did you do with this document? e.g. Reviewed, signed, and initialled." />
          <AppTextarea v-model="reviewForm.remarks" :rows="2" label="Remarks (optional)"
            placeholder="Additional notes…" />
          <p class="text-xs text-slate-400 font-medium uppercase tracking-wide">Route to next step ↓</p>
        </div>

        <!-- Return tab -->
        <template v-if="reviewTab === 'return'">
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Return to</p>
            <div class="space-y-2">
              <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                :class="reviewForm.return_target === 'original' ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50'">
                <input type="radio" value="original" v-model="reviewForm.return_target" class="mt-0.5 shrink-0" />
                <div>
                  <p class="text-sm font-medium text-slate-800">Original Sender</p>
                  <p class="text-xs text-slate-500">{{ originalSender?.name ?? '—' }}</p>
                </div>
              </label>
              <label v-if="latestActionTaker && latestActionTaker.id !== originalSender?.id"
                class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                :class="reviewForm.return_target === 'latest_action_taker' ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50'">
                <input type="radio" value="latest_action_taker" v-model="reviewForm.return_target" class="mt-0.5 shrink-0" />
                <div>
                  <p class="text-sm font-medium text-slate-800">Latest Action Taker <AppBadge color="amber" class="ml-1">Recommended</AppBadge></p>
                  <p class="text-xs text-slate-500">{{ latestActionTaker?.name }} — most recently processed this document</p>
                </div>
              </label>
              <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                :class="reviewForm.return_target === 'step' ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50'">
                <input type="radio" value="step" v-model="reviewForm.return_target" class="mt-0.5 shrink-0" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-slate-800">Choose a previous step</p>
                  <select
                    v-if="reviewForm.return_target === 'step'"
                    v-model="reviewForm.return_target_routing_id"
                    class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option :value="null">Select step…</option>
                    <option v-for="r in routingChain" :key="r.id" :value="r.id">
                      Step {{ r.sequence }} — {{ r.receiver?.name }} ({{ r.status }})
                    </option>
                  </select>
                </div>
              </label>
            </div>
            <p v-if="reviewErrors.return_target" class="text-xs text-red-500 mt-1">{{ reviewErrors.return_target }}</p>
            <p v-if="reviewErrors.return_target_routing_id" class="text-xs text-red-500 mt-1">{{ reviewErrors.return_target_routing_id }}</p>
          </div>
          <AppTextarea v-model="reviewForm.return_reason" :rows="3" required label="Reason"
            :error="reviewErrors.return_reason"
            placeholder="Explain why this document is being returned…" />
        </template>

        <!-- Forward tab -->
        <template v-if="reviewTab === 'forward'">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Forward To <span class="text-red-500">*</span></label>
            <div class="relative mb-1">
              <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none z-10" />
              <AppInput v-model="forwardSearch" placeholder="Search name…" class="[&_input]:pl-9" />
            </div>
            <select v-model="reviewForm.forward_to" required size="5"
              class="w-full rounded-lg border border-slate-200 px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="u in filteredUsers" :key="u.id" :value="u.id">{{ userDisplayName(u, users) }}</option>
            </select>
            <p v-if="reviewErrors.forward_to" class="text-xs text-red-500 mt-1">{{ reviewErrors.forward_to }}</p>
          </div>
          <AppTextarea v-model="reviewForm.instructions" :rows="2" label="Instructions for Receiver"
            placeholder="What should the next person do?" />
        </template>

        <!-- Complete tab -->
        <template v-if="reviewTab === 'complete'">
          <div class="flex items-start gap-3 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-800">
            <CheckCircleIcon class="h-5 w-5 text-emerald-500 shrink-0 mt-0.5" />
            <p>You are the final step in this routing chain. Completing will close the document process and notify the creator.</p>
          </div>
          <AppTextarea v-model="reviewForm.completion_notes" :rows="3" label="Completion Notes (optional)"
            placeholder="e.g. Filed in Records. No further action required." />
        </template>

      </AppTabs>

      <template #footer>
        <AppButton variant="secondary" @click="closeReviewModal">Cancel</AppButton>
        <AppButton
          :loading="reviewSubmitting"
          :disabled="reviewSubmitting"
          :variant="{ forward: 'primary', return: 'danger', complete: 'success' }[reviewTab]"
          @click="doReview()">
          {{ { forward: 'Forward Document', return: 'Return Document', complete: 'Complete Document' }[reviewTab] }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Modals (Add Note / Complete & File) ────────────────────────────── -->
    <AppModal :show="!!modal" :title="modal ? { annotate: 'Add Note', complete: 'Complete & File' }[modal] : ''" size="lg" @close="closeModal">

      <!-- Annotate -->
      <template v-if="modal === 'annotate'">
        <AppTextarea v-model="modalForm.remarks" :rows="3" required label="Note / Remarks"
          placeholder="Add a note without recording an action…" />
      </template>

      <!-- Complete -->
      <template v-if="modal === 'complete'">
        <p class="text-sm text-slate-600 mb-3">This will close the document and mark it as filed. All remaining routing steps will be resolved.</p>
        <AppTextarea v-model="modalForm.action_taken" :rows="2" label="Final Action / Notes (optional)"
          placeholder="e.g. Filed in Records. No further action required." />
      </template>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton :loading="submitting" :disabled="submitting"
          @click="{ annotate: doAnnotate, complete: doComplete }[modal]()">
          {{ { annotate: 'Save Note', complete: 'Complete & File' }[modal] }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Scan Preview Modal ──────────────────────────────────────────────── -->
    <AppModal :show="!!previewAtt" :title="previewAtt?.file_name" size="4xl" body-class="p-2" @close="closePreview">
      <div v-if="previewLoading" class="flex items-center justify-center h-64 text-slate-400 text-sm">
        Loading from Google Drive…
      </div>
      <iframe v-else-if="previewMime.includes('pdf') && previewUrl"
        :src="previewUrl" class="w-full h-[75vh] rounded-lg border border-slate-100" />
      <img v-else-if="previewUrl"
        :src="previewUrl" class="max-w-full mx-auto rounded-lg" alt="Document scan" />
    </AppModal>

    <!-- ── Edit Document Details Modal ─────────────────────────────────────── -->
    <AppModal :show="editOpen" title="Edit Document Details" :subtitle="document.tracking_no" size="2xl" @close="editOpen = false">
      <form @submit.prevent="submitEdit" class="space-y-4">

        <!-- Document Type -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Document Type <span class="text-red-500">*</span></label>
          <select v-model="editForm.document_type_id" required
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">Select type…</option>
            <option v-for="t in availableEditTypes" :key="t.id" :value="t.id">[{{ t.code }}] {{ t.name }}</option>
          </select>
          <p v-if="editErrors.document_type_id" class="text-xs text-red-500 mt-1">{{ editErrors.document_type_id }}</p>
        </div>

        <!-- Subject -->
        <AppInput v-model="editForm.subject" label="Subject" required :error="editErrors.subject" />

        <!-- Description -->
        <AppTextarea v-model="editForm.description" :rows="2" label="Description" :error="editErrors.description"
          placeholder="Additional details…" />

        <!-- Priority / Urgency -->
        <div class="grid grid-cols-2 gap-3">
          <AppSelect v-model="editForm.priority" label="Priority" :show-blank="false">
            <option>Normal</option><option>Urgent</option><option>Rush</option>
          </AppSelect>
          <AppSelect v-model="editForm.urgency" label="Urgency" :show-blank="false">
            <option>Normal</option><option>Urgent</option><option>Very Urgent</option>
          </AppSelect>
        </div>

        <!-- Deadline -->
        <AppInput v-model="editForm.deadline_at" type="datetime-local" label="Deadline" />

        <!-- Confidential -->
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="editForm.is_confidential" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700 flex items-center gap-1">
            <LockClosedIcon class="h-3.5 w-3.5 text-purple-500" /> Mark as confidential
          </span>
        </label>

        <!-- External-only fields -->
        <template v-if="document.origin_type === 'external'">
          <div class="bg-green-50 border border-green-200 rounded-xl p-4 space-y-3">
            <p class="text-xs font-semibold text-green-700 uppercase tracking-wide">External Document Details</p>
            <div class="grid grid-cols-2 gap-3">
              <AppInput v-model="editForm.source_office" label="Source Office" required />
              <AppInput v-model="editForm.sender_name" label="Sender Name" />
            </div>
            <div class="grid grid-cols-3 gap-3">
              <AppInput v-model="editForm.date_of_document" type="date" label="Doc Date" />
              <AppInput v-model="editForm.date_received" type="date" label="Date Received" />
              <AppInput v-model="editForm.document_number" label="Ref. No." />
            </div>
          </div>
        </template>

      </form>

      <template #footer>
        <AppButton variant="secondary" @click="editOpen = false">Cancel</AppButton>
        <AppButton :loading="editSubmitting" :disabled="editSubmitting" @click="submitEdit">
          Save Changes
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
