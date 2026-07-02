<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { userDisplayName } from '@/Utils/userDisplay.js'
import { badgeBase, statusBadgeClass, priorityBadgeClass, originBadgeClass, routingStatusBadgeClass } from '@/Composables/useStatusBadge.js'
import {
  ChevronLeftIcon, LockClosedIcon, ExclamationTriangleIcon,
  CheckCircleIcon, ClockIcon, ArrowRightIcon, ArrowUturnLeftIcon,
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
  const result = await Swal.fire({
    title: 'Mark complete and file?',
    text: 'This will close the document process and mark the document as filed.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#059669',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Yes, complete and file',
    cancelButtonText: 'Cancel',
  })

  return result.isConfirmed
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

      <!-- Back -->
      <button @click="router.visit(route('document-tracking.index'))"
        class="flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Document Tracking
      </button>

      <!-- ── Document Header Card ─────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
          <div class="flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-2">
              <span class="font-mono text-sm font-bold text-indigo-700">{{ document.tracking_no }}</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                :class="document.origin_type === 'external' ? 'bg-green-100 text-green-700' : 'bg-indigo-100 text-indigo-700'">
                {{ document.origin_type === 'external' ? 'External Incoming' : 'Internal' }}
              </span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                :class="{ Normal: 'bg-slate-100 text-slate-600', Urgent: 'bg-amber-100 text-amber-700', Rush: 'bg-red-100 text-red-700' }[document.priority] ?? 'bg-slate-100 text-slate-600'">
                {{ document.priority }}
              </span>
              <span v-if="document.is_confidential"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-purple-100 text-purple-700">
                <LockClosedIcon class="h-3 w-3" /> Confidential
              </span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold" :class="overallBadgeCls">
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
            <button v-if="canEdit" @click="openEditModal()"
              class="mt-2 flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
              <PencilSquareIcon class="h-3.5 w-3.5" /> Edit Details
            </button>
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
            <button v-if="isPending"
              :disabled="acknowledging"
              @click="doReceive()"
              class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-60 disabled:cursor-not-allowed">
              <CheckCircleIcon class="h-4 w-4" />
              {{ acknowledging ? 'Acknowledging…' : 'Acknowledge Receipt' }}
            </button>

            <!-- Review & Process — primary action, disabled until acknowledged -->
            <button
              :disabled="isPending"
              @click="!isPending && openReviewModal()"
              :title="isPending ? 'Acknowledge receipt first' : ''"
              class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
              <DocumentCheckIcon class="h-4 w-4" /> Review & Process
            </button>

            <!-- Add Note — disabled until acknowledged -->
            <button
              :disabled="isPending"
              @click="!isPending && openModal('annotate')"
              :title="isPending ? 'Acknowledge receipt first' : ''"
              class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg disabled:opacity-40 disabled:cursor-not-allowed">
              <PencilSquareIcon class="h-4 w-4" /> Add Note
            </button>
          </div>

          <!-- Hint shown while status is Pending -->
          <p v-if="isPending" class="text-xs text-amber-600 flex items-center gap-1">
            <ExclamationTriangleIcon class="h-3.5 w-3.5 shrink-0" />
            Acknowledge receipt first to enable further actions.
          </p>
        </div>

        <!-- Mark Complete & File — admin or terminal/manual receiver -->
        <div v-if="canCompleteAndFile" class="mt-3 flex justify-end">
          <button @click="openModal('complete')"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50">
            <CheckCircleIcon class="h-4 w-4 text-emerald-500" /> Mark Complete & File
          </button>
        </div>
      </div>

      <!-- ── Attachments / Scans ──────────────────────────────────────────── -->
      <div v-if="document.attachments?.length" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
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
              <button v-if="att.has_preview" @click="openPreview(att)"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-medium">
                <EyeIcon class="h-3.5 w-3.5" /> Preview
              </button>
              <a v-if="att.gdrive_link" :href="att.gdrive_link" target="_blank" rel="noopener"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-600 rounded-lg text-xs font-medium">
                <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" /> Google Drive
              </a>
            </div>
          </li>
        </ul>
      </div>

      <!-- ── Routing Timeline ────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-5">Routing Timeline</h2>

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
                    <span v-if="r.receiver?.id === uid && ['Pending','Received'].includes(r.status)"
                      class="text-[10px] font-bold text-indigo-600 bg-indigo-100 px-1.5 py-0.5 rounded-full">YOU</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <ExclamationTriangleIcon v-if="r.is_overdue && ['Pending','Received'].includes(r.status)"
                      class="h-3.5 w-3.5 text-red-500" />
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold" :class="routingStatusBadgeClass(r)">
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
      </div>

    </div>

    <!-- ── Review Document Modal ─────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="reviewOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-black/40" @click="closeReviewModal" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] flex flex-col">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
            <h3 class="font-bold text-slate-800">Review Document</h3>
            <button @click="closeReviewModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <!-- Tabs -->
          <div class="flex border-b border-slate-100 shrink-0">
            <button
              @click="reviewTab = 'forward'"
              class="flex-1 py-2.5 text-sm font-medium transition-colors"
              :class="reviewTab === 'forward' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-slate-500 hover:text-slate-700'">
              Forward
            </button>
            <button
              @click="reviewTab = 'return'"
              class="flex-1 py-2.5 text-sm font-medium transition-colors"
              :class="reviewTab === 'return' ? 'border-b-2 border-red-500 text-red-600' : 'text-slate-500 hover:text-slate-700'">
              Return to Sender
            </button>
            <button
              v-if="canCompleteAsReceiver"
              @click="reviewTab = 'complete'"
              class="flex-1 py-2.5 text-sm font-medium transition-colors"
              :class="reviewTab === 'complete' ? 'border-b-2 border-emerald-500 text-emerald-600' : 'text-slate-500 hover:text-slate-700'">
              Complete
            </button>
          </div>

          <!-- Body -->
          <div class="overflow-y-auto px-6 py-5 space-y-4 flex-1">

            <!-- Action notes (shared across all tabs) -->
            <div class="space-y-3 pb-3 border-b border-slate-100">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Action Taken <span class="text-slate-400 font-normal">(optional)</span></label>
                <textarea v-model="reviewForm.action_taken" rows="2"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="What did you do with this document? e.g. Reviewed, signed, and initialled." />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Remarks <span class="text-slate-400 font-normal">(optional)</span></label>
                <textarea v-model="reviewForm.remarks" rows="2"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Additional notes…" />
              </div>
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
                      <p class="text-sm font-medium text-slate-800">Latest Action Taker <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold ml-1">Recommended</span></p>
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
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Reason <span class="text-red-500">*</span></label>
                <textarea v-model="reviewForm.return_reason" rows="3" required
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Explain why this document is being returned…" />
                <p v-if="reviewErrors.return_reason" class="text-xs text-red-500 mt-1">{{ reviewErrors.return_reason }}</p>
              </div>
            </template>

            <!-- Forward tab -->
            <template v-if="reviewTab === 'forward'">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Forward To <span class="text-red-500">*</span></label>
                <div class="relative mb-1">
                  <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
                  <input v-model="forwardSearch" type="text" placeholder="Search name…"
                    class="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <select v-model="reviewForm.forward_to" required size="5"
                  class="w-full rounded-lg border border-slate-200 px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option v-for="u in filteredUsers" :key="u.id" :value="u.id">{{ userDisplayName(u, users) }}</option>
                </select>
                <p v-if="reviewErrors.forward_to" class="text-xs text-red-500 mt-1">{{ reviewErrors.forward_to }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Instructions for Receiver</label>
                <textarea v-model="reviewForm.instructions" rows="2"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="What should the next person do?" />
              </div>
            </template>

            <!-- Complete tab -->
            <template v-if="reviewTab === 'complete'">
              <div class="flex items-start gap-3 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-800">
                <CheckCircleIcon class="h-5 w-5 text-emerald-500 shrink-0 mt-0.5" />
                <p>You are the final step in this routing chain. Completing will close the document process and notify the creator.</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Completion Notes (optional)</label>
                <textarea v-model="reviewForm.completion_notes" rows="3"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="e.g. Filed in Records. No further action required." />
              </div>
            </template>

          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 shrink-0">
            <button @click="closeReviewModal"
              class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button :disabled="reviewSubmitting" @click="doReview()"
              class="px-5 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-50 transition-colors"
              :class="{
                'bg-blue-600 hover:bg-blue-700':       reviewTab === 'forward',
                'bg-red-600 hover:bg-red-700':         reviewTab === 'return',
                'bg-emerald-600 hover:bg-emerald-700': reviewTab === 'complete',
              }">
              {{ reviewSubmitting ? 'Saving…' : { forward: 'Forward Document', return: 'Return Document', complete: 'Complete Document' }[reviewTab] }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Modals ──────────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-black/40" @click="closeModal" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800">
              {{ { annotate: 'Add Note', complete: 'Complete & File' }[modal] }}
            </h3>
            <button @click="closeModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>

          <div class="px-6 py-5 space-y-4">

            <!-- Annotate -->
            <template v-if="modal === 'annotate'">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Note / Remarks <span class="text-red-500">*</span></label>
                <textarea v-model="modalForm.remarks" rows="3" required
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Add a note without recording an action…" />
              </div>
            </template>

            <!-- Complete -->
            <template v-if="modal === 'complete'">
              <p class="text-sm text-slate-600">This will close the document and mark it as filed. All remaining routing steps will be resolved.</p>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Final Action / Notes (optional)</label>
                <textarea v-model="modalForm.action_taken" rows="2"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="e.g. Filed in Records. No further action required." />
              </div>
            </template>

          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeModal"
              class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button :disabled="submitting"
              @click="{ annotate: doAnnotate, complete: doComplete }[modal]()"
              class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
              {{ submitting ? 'Saving…' : { annotate: 'Save Note', complete: 'Complete & File' }[modal] }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Scan Preview Modal ──────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="previewAtt" class="fixed inset-0 z-[60] flex items-center justify-center px-4 bg-black/60">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col">
          <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-800">{{ previewAtt.file_name }}</p>
            <button @click="closePreview" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="flex-1 overflow-auto p-2">
            <div v-if="previewLoading" class="flex items-center justify-center h-64 text-slate-400 text-sm">
              Loading from Google Drive…
            </div>
            <iframe v-else-if="previewMime.includes('pdf') && previewUrl"
              :src="previewUrl" class="w-full h-[75vh] rounded-lg border border-slate-100" />
            <img v-else-if="previewUrl"
              :src="previewUrl" class="max-w-full mx-auto rounded-lg" alt="Document scan" />
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Edit Document Details Modal ─────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-black/40" @click="editOpen = false" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col">

          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between rounded-t-2xl">
            <div>
              <h3 class="font-bold text-slate-800">Edit Document Details</h3>
              <p class="text-xs text-slate-500 mt-0.5">{{ document.tracking_no }}</p>
            </div>
            <button @click="editOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <!-- Body -->
          <form @submit.prevent="submitEdit" class="overflow-y-auto px-6 py-5 space-y-4 flex-1">

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
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Subject <span class="text-red-500">*</span></label>
              <input v-model="editForm.subject" type="text" required
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p v-if="editErrors.subject" class="text-xs text-red-500 mt-1">{{ editErrors.subject }}</p>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
              <textarea v-model="editForm.description" rows="2"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Additional details…" />
              <p v-if="editErrors.description" class="text-xs text-red-500 mt-1">{{ editErrors.description }}</p>
            </div>

            <!-- Priority / Urgency -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Priority</label>
                <select v-model="editForm.priority"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option>Normal</option><option>Urgent</option><option>Rush</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Urgency</label>
                <select v-model="editForm.urgency"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option>Normal</option><option>Urgent</option><option>Very Urgent</option>
                </select>
              </div>
            </div>

            <!-- Deadline -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Deadline</label>
              <input v-model="editForm.deadline_at" type="datetime-local"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

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
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Source Office <span class="text-red-500">*</span></label>
                    <input v-model="editForm.source_office" type="text" required
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Sender Name</label>
                    <input v-model="editForm.sender_name" type="text"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Doc Date</label>
                    <input v-model="editForm.date_of_document" type="date"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Date Received</label>
                    <input v-model="editForm.date_received" type="date"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Ref. No.</label>
                    <input v-model="editForm.document_number" type="text"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                </div>
              </div>
            </template>

          </form>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 rounded-b-2xl bg-white">
            <button type="button" @click="editOpen = false"
              class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
              Cancel
            </button>
            <button @click="submitEdit" :disabled="editSubmitting"
              class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
              {{ editSubmitting ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
