<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import { badgeBase, statusBadgeClass, priorityBadgeClass, originBadgeClass, routingStatusBadgeClass } from '@/Composables/useStatusBadge.js'
import {
  ChevronLeftIcon, LockClosedIcon, ExclamationTriangleIcon,
  CheckCircleIcon, ClockIcon, ArrowRightIcon, ArrowUturnLeftIcon,
  PaperClipIcon, PencilSquareIcon, EyeIcon, DocumentCheckIcon,
  UserIcon, ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  document:      Object,
  users:         Array,
  isAdmin:       Boolean,
  currentUserId: Number,
})

const page = usePage()
const uid  = computed(() => props.currentUserId ?? page.props.auth?.user?.id)

// ── Active routing step for current user ──────────────────────────────────
// For external docs the initial step is sender=Records, receiver=Records, status=Received
// — Records can still Forward from that step.
const myActiveRouting = computed(() =>
  props.document.routings?.find(r =>
    r.receiver?.id === uid.value &&
    ['Pending', 'Received'].includes(r.status)
  )
)

// OCD users shown first in Forward dropdown for external docs
const sortedUsers = computed(() => {
  if (props.document.origin_type !== 'external') return props.users ?? []
  // Put users whose name suggests OCD/Campus Director first (hint only — no role data here)
  return props.users ?? []
})
const isCompleted = computed(() => props.document.overall_status === 'Completed')
const canComplete = computed(() =>
  props.isAdmin || myActiveRouting.value?.status === 'Action Taken' ||
  props.document.routings?.some(r => r.receiver?.id === uid.value && r.status === 'Action Taken')
)

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

function doReceive()  { submit('document-tracking.receive', myActiveRouting.value.id, {}) }
function doAnnotate() { submit('document-tracking.annotate', props.document.id, { remarks: modalForm.value.remarks }) }
function doAct()      { submit('document-tracking.act', myActiveRouting.value.id, { action_taken: modalForm.value.action_taken, remarks: modalForm.value.remarks }) }
function doForward()  { submit('document-tracking.forward',  myActiveRouting.value.id, { receiver_id: modalForm.value.receiver_id, instructions: modalForm.value.instructions, remarks: modalForm.value.remarks }) }
function doReturn()   { submit('document-tracking.return',   myActiveRouting.value.id, { return_reason: modalForm.value.return_reason }) }
function doComplete() { submit('document-tracking.complete', props.document.id, { action_taken: modalForm.value.action_taken }) }

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
        <div v-if="myActiveRouting && !isCompleted" class="mt-4 flex flex-wrap gap-2 pt-4 border-t border-slate-100">
          <button v-if="myActiveRouting.status === 'Pending'" @click="doReceive()"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">
            <CheckCircleIcon class="h-4 w-4" /> Acknowledge Receipt
          </button>
          <button @click="openModal('act')"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg">
            <DocumentCheckIcon class="h-4 w-4" /> Record Action
          </button>
          <button @click="openModal('forward')"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
            <ArrowRightIcon class="h-4 w-4" /> Forward
          </button>
          <button @click="openModal('return')"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">
            <ArrowUturnLeftIcon class="h-4 w-4" /> Return
          </button>
          <button @click="openModal('annotate')"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">
            <PencilSquareIcon class="h-4 w-4" /> Add Note
          </button>
        </div>

        <!-- Complete document (admin / OCD) -->
        <div v-if="!isCompleted && (isAdmin || canComplete)" class="mt-3 flex justify-end">
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

    <!-- ── Modals ──────────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-black/40" @click="closeModal" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800">
              {{ { act: 'Record Action', forward: 'Forward Document', return: 'Return Document', annotate: 'Add Note', complete: 'Complete & File' }[modal] }}
            </h3>
            <button @click="closeModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>

          <div class="px-6 py-5 space-y-4">

            <!-- Act -->
            <template v-if="modal === 'act'">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Action Taken <span class="text-red-500">*</span></label>
                <textarea v-model="modalForm.action_taken" rows="3" required
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Describe the action you took on this document…" />
                <p v-if="modalErrors.action_taken" class="text-xs text-red-500 mt-1">{{ modalErrors.action_taken }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Remarks (optional)</label>
                <textarea v-model="modalForm.remarks" rows="2"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Additional notes…" />
              </div>
            </template>

            <!-- Forward -->
            <template v-if="modal === 'forward'">
              <p v-if="document.origin_type === 'external'" class="text-xs bg-green-50 border border-green-200 text-green-700 rounded-lg px-3 py-2">
                External document — forward to OCD for review and routing instructions.
              </p>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Forward To <span class="text-red-500">*</span></label>
                <select v-model="modalForm.receiver_id" required
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="">Select recipient…</option>
                  <option v-for="u in sortedUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Instructions for Receiver</label>
                <textarea v-model="modalForm.instructions" rows="2"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="What should the next person do?" />
              </div>
            </template>

            <!-- Return -->
            <template v-if="modal === 'return'">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Return Reason <span class="text-red-500">*</span></label>
                <textarea v-model="modalForm.return_reason" rows="3" required
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Explain why the document is being returned…" />
              </div>
            </template>

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
              @click="{ act: doAct, forward: doForward, return: doReturn, annotate: doAnnotate, complete: doComplete }[modal]()"
              class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
              {{ submitting ? 'Saving…' : { act: 'Record Action', forward: 'Forward', return: 'Return', annotate: 'Save Note', complete: 'Complete & File' }[modal] }}
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

  </AdminLayout>
</template>
