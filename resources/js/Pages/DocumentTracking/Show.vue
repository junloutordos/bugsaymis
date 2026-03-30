<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import {
  PaperClipIcon,
  ArrowDownTrayIcon,
  LockClosedIcon,
  ExclamationTriangleIcon,
  ArrowRightIcon,
  ArrowUturnLeftIcon,
  ClockIcon,
  UserIcon,
  XMarkIcon,
  ChevronLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  document: Object,
  users:    Array,
})

const page   = usePage()
const authId = computed(() => page.props.auth?.user?.id)

// ─── Unified "Process Document" modal ─────────────────────────────────────────
const showProcessModal  = ref(false)
const processRoutingId  = ref(null)
const processForm       = useForm({ action_taken: '', remarks: '', next_step: 'forward', receiver_id: '' })
const processUserSearch = ref('')
const isSubmitting      = ref(false)

const filteredProcessUsers = computed(() => {
  const q = processUserSearch.value.trim().toLowerCase()
  if (!q) return props.users.slice(0, 30)
  return props.users.filter(u =>
    (u.name || '').toLowerCase().includes(q) ||
    (u.position || '').toLowerCase().includes(q)
  ).slice(0, 30)
})

const openProcessModal = (routingId) => {
  processRoutingId.value = routingId
  processForm.reset()
  processForm.next_step = 'forward'
  processUserSearch.value = ''
  showProcessModal.value = true
}

const submitProcess = () => {
  if (!processForm.action_taken.trim()) {
    Swal.fire({ icon: 'warning', title: 'Action taken is required.', text: 'Please describe what you did with this document.' })
    return
  }

  isSubmitting.value = true

  const onError = (e) => {
    isSubmitting.value = false
    Swal.fire({ icon: 'error', title: 'Failed', text: Object.values(e).flat().join('\n') })
  }

  // For File — POST to complete
  if (processForm.next_step === 'for_file') {
    useForm({ action_taken: processForm.action_taken })
      .post(route('document-tracking.complete', processRoutingId.value), {
        onSuccess: () => {
          isSubmitting.value = false
          showProcessModal.value = false
          Swal.fire({ icon: 'success', title: 'Document Completed', text: 'Marked as filed.', timer: 2000, showConfirmButton: false })
        },
        onError,
      })
    return
  }

  // Forward / Return to owner — POST to forward
  const receiverId = processForm.next_step === 'return_to_owner'
    ? props.document.creator?.id
    : processForm.receiver_id

  if (!receiverId) {
    isSubmitting.value = false
    Swal.fire({ icon: 'warning', title: 'Select the next receiver.' })
    return
  }

  const successTitle = processForm.next_step === 'return_to_owner'
    ? 'Returned to Owner!'
    : 'Document Forwarded!'

  useForm({ action_taken: processForm.action_taken, remarks: processForm.remarks, receiver_id: receiverId })
    .post(route('document-tracking.forward', processRoutingId.value), {
      onSuccess: () => {
        isSubmitting.value = false
        showProcessModal.value = false
        Swal.fire({ icon: 'success', title: successTitle, text: 'The next receiver has been notified.', timer: 2000, showConfirmButton: false })
      },
      onError,
    })
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
const urgencyClass = (urgency) => {
  if (urgency === 'Very Urgent') return 'bg-red-50 text-red-700'
  if (urgency === 'Urgent')      return 'bg-amber-50 text-amber-700'
  return 'bg-blue-50 text-blue-700'
}

const routingStatusClass = (status, isOverdue) => {
  if (isOverdue && ['Pending','Received'].includes(status)) return 'bg-red-50 text-red-700'
  if (status === 'Forwarded')    return 'bg-emerald-50 text-emerald-700'
  if (status === 'Action Taken') return 'bg-indigo-50 text-indigo-700'
  if (status === 'Received')     return 'bg-blue-50 text-blue-700'
  if (status === 'Returned')     return 'bg-red-50 text-red-600'
  return 'bg-amber-50 text-amber-700'
}

const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
}

const formatSize = (bytes) => {
  if (!bytes) return ''
  if (bytes < 1024)        return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const fileIcon = (mime) => {
  if (!mime) return '📄'
  if (mime.includes('pdf'))                          return '📋'
  if (mime.includes('word') || mime.includes('doc')) return '📝'
  if (mime.includes('sheet') || mime.includes('excel') || mime.includes('xls')) return '📊'
  if (mime.includes('image'))                        return '🖼️'
  return '📄'
}
</script>

<template>
  <Head :title="`Document ${document.tracking_no}`" />
  <AdminLayout :title="`Document ${document.tracking_no}`">
    <div class="space-y-5">

      <!-- Back link -->
      <div>
        <button
          @click="router.visit(route('document-tracking.index'))"
          class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 transition-colors"
        >
          <ChevronLeftIcon class="h-4 w-4" /> Back to Document Tracking
        </button>
      </div>

      <!-- ─── Document Details Card ────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-mono text-base font-bold text-indigo-700 tracking-wide">{{ document.tracking_no }}</span>
              <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', urgencyClass(document.urgency)]">
                {{ document.urgency }}
              </span>
              <span v-if="document.is_confidential" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-purple-50 text-purple-700">
                <LockClosedIcon class="h-3 w-3" /> Confidential
              </span>
              <span
                :class="[
                  'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium',
                  document.overall_status === 'Completed' ? 'bg-emerald-50 text-emerald-700' :
                  document.overall_status === 'Cancelled' ? 'bg-slate-100 text-slate-600' :
                  'bg-blue-50 text-blue-700'
                ]"
              >{{ document.overall_status }}</span>
            </div>
            <h1 class="text-lg font-bold text-slate-800 mt-1.5">{{ document.subject }}</h1>
          </div>
          <div class="text-right text-xs text-slate-500 space-y-0.5">
            <div>Filed: {{ formatDate(document.created_at) }}</div>
            <div>By: <span class="font-semibold text-slate-700">{{ document.creator?.name }}</span></div>
          </div>
        </div>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <p class="text-xs font-medium text-slate-500 mb-0.5">Document Type</p>
            <p class="text-sm text-slate-800">{{ document.document_type?.name }} <span class="text-slate-400 text-xs">({{ document.document_type?.code }})</span></p>
          </div>
          <div>
            <p class="text-xs font-medium text-slate-500 mb-0.5">Current Holder</p>
            <p class="text-sm text-slate-800">{{ document.current_holder?.name ?? '— (Completed)' }}</p>
          </div>
          <div>
            <p class="text-xs font-medium text-slate-500 mb-0.5">Lead Time</p>
            <p class="text-sm text-slate-800">{{ document.document_type?.lead_time_hours }}h per ARTA/CSC</p>
          </div>
        </div>

        <div v-if="document.description" class="mt-4">
          <p class="text-xs font-medium text-slate-500 mb-1">Description / Particulars</p>
          <p class="text-sm text-slate-700 whitespace-pre-line">{{ document.description }}</p>
        </div>
      </div>

      <!-- ─── Attachments ──────────────────────────────────────────────────── -->
      <div v-if="document.attachments?.length" class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 sm:p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-1.5">
          <PaperClipIcon class="h-4 w-4 text-slate-400" />
          Attachments <span class="text-slate-400 font-normal">({{ document.attachments.length }})</span>
        </h2>
        <ul class="divide-y divide-slate-100">
          <li
            v-for="att in document.attachments"
            :key="att.id"
            class="flex items-center justify-between py-2.5 gap-3"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <span class="text-lg shrink-0">{{ fileIcon(att.mime_type) }}</span>
              <div class="min-w-0">
                <p class="text-sm font-medium text-slate-800 truncate">{{ att.file_name }}</p>
                <p class="text-xs text-slate-400">{{ formatSize(att.file_size) }} · {{ att.uploader?.name }}</p>
              </div>
            </div>
            <a
              :href="route('document-tracking.download', att.id)"
              class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-medium transition-colors"
            >
              <ArrowDownTrayIcon class="h-3.5 w-3.5" /> Download
            </a>
          </li>
        </ul>
      </div>

      <!-- ─── Routing Timeline ──────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 sm:p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-5">Routing History</h2>

        <div class="relative">
          <!-- Vertical connector line -->
          <div class="absolute left-5 top-0 bottom-0 w-px bg-slate-100 hidden sm:block"></div>

          <div class="space-y-4">
            <div
              v-for="routing in document.routings"
              :key="routing.id"
              class="relative flex gap-4 sm:gap-5"
            >
              <!-- Step circle -->
              <div class="shrink-0 z-10">
                <div :class="[
                  'w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold border-2',
                  routing.is_overdue && ['Pending','Received'].includes(routing.status)
                    ? 'bg-red-50 border-red-300 text-red-700'
                    : routing.status === 'Forwarded' || routing.status === 'Action Taken'
                    ? 'bg-emerald-50 border-emerald-300 text-emerald-700'
                    : 'bg-slate-50 border-slate-200 text-slate-500'
                ]">
                  {{ routing.sequence }}
                </div>
              </div>

              <!-- Routing card -->
              <div
                :class="[
                  'flex-1 rounded-xl border p-4 mb-1',
                  routing.is_overdue && ['Pending','Received'].includes(routing.status)
                    ? 'border-red-200 bg-red-50/50'
                    : 'border-slate-100 bg-white'
                ]"
              >
                <!-- Header row: sender → receiver + status -->
                <div class="flex items-center justify-between flex-wrap gap-2">
                  <div class="flex items-center gap-2 text-sm flex-wrap">
                    <span class="inline-flex items-center gap-1 text-slate-500">
                      <UserIcon class="h-3.5 w-3.5" />{{ routing.sender?.name ?? '—' }}
                    </span>
                    <ArrowRightIcon class="h-3.5 w-3.5 text-slate-300" />
                    <span class="font-semibold text-slate-800 inline-flex items-center gap-1">
                      <UserIcon class="h-3.5 w-3.5 text-indigo-400" />{{ routing.receiver?.name ?? '—' }}
                    </span>
                  </div>
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                      v-if="routing.is_overdue && ['Pending','Received'].includes(routing.status)"
                      class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-600 text-white"
                    >
                      <ExclamationTriangleIcon class="h-3 w-3" /> OVERDUE
                    </span>
                    <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', routingStatusClass(routing.status, routing.is_overdue)]">
                      {{ routing.status }}
                    </span>
                  </div>
                </div>

                <!-- Timestamps -->
                <div class="mt-2.5 grid grid-cols-1 sm:grid-cols-3 gap-1 text-xs text-slate-500">
                  <div class="flex items-center gap-1">
                    <ClockIcon class="h-3 w-3 shrink-0" />
                    <span>Due: <strong :class="routing.is_overdue ? 'text-red-600' : 'text-slate-700'">{{ formatDate(routing.due_at) }}</strong></span>
                  </div>
                  <div v-if="routing.received_at">Received: {{ formatDate(routing.received_at) }}</div>
                  <div v-if="routing.action_taken_at">Acted: {{ formatDate(routing.action_taken_at) }}</div>
                  <div v-if="routing.forwarded_at">Forwarded: {{ formatDate(routing.forwarded_at) }}</div>
                </div>

                <!-- Remarks from sender -->
                <div v-if="routing.remarks" class="mt-2.5 text-xs text-slate-600 bg-slate-50 rounded-lg p-2.5">
                  <strong>Remarks:</strong> {{ routing.remarks }}
                </div>

                <!-- Action taken -->
                <div v-if="routing.action_taken" class="mt-2 text-xs text-indigo-700 bg-indigo-50 rounded-lg p-2.5">
                  <strong>Action Taken:</strong> {{ routing.action_taken }}
                </div>

                <!-- Process Document button -->
                <div
                  v-if="routing.receiver_id === authId && ['Pending','Received'].includes(routing.status) && document.overall_status === 'In-Transit'"
                  class="mt-3"
                >
                  <button
                    @click="openProcessModal(routing.id)"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
                  >
                    Process Document
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ─── Process Document Modal ──────────────────────────────────────── -->
      <div v-if="showProcessModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white w-full sm:max-w-lg rounded-2xl shadow-xl flex flex-col max-h-[90vh]">

          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">Process Document</h3>
            <button @click="showProcessModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <!-- Scrollable body -->
          <div class="overflow-y-auto px-6 py-5 space-y-4">

            <!-- Action Taken -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">
                Action Taken <span class="text-red-500">*</span>
              </label>
              <textarea
                v-model="processForm.action_taken"
                rows="3"
                placeholder="Describe the action you took on this document..."
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
              ></textarea>
            </div>

            <!-- Remarks -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
              <textarea
                v-model="processForm.remarks"
                rows="2"
                placeholder="Optional remarks or instructions for the next receiver..."
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
              ></textarea>
            </div>

            <!-- Next Step -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">What to do next?</label>
              <div class="space-y-2">

                <!-- Forward to another user -->
                <label
                  class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                  :class="processForm.next_step === 'forward' ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50'"
                >
                  <input type="radio" v-model="processForm.next_step" value="forward" class="mt-0.5 accent-indigo-600" />
                  <div>
                    <p class="text-sm font-medium text-slate-800 flex items-center gap-1">
                      <ArrowRightIcon class="h-4 w-4 text-indigo-500" /> Forward to another user for action
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5">Route this document to a different person to take further action.</p>
                  </div>
                </label>

                <!-- User search (shown when forward is selected) -->
                <div v-if="processForm.next_step === 'forward'" class="pl-7 space-y-2">
                  <input
                    v-model="processUserSearch"
                    type="text"
                    placeholder="Search by name or position..."
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                  />
                  <select
                    v-model="processForm.receiver_id"
                    size="4"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                  >
                    <option v-for="u in filteredProcessUsers" :key="u.id" :value="u.id">
                      {{ u.name }}{{ u.position ? ' — ' + u.position : '' }}
                    </option>
                  </select>
                </div>

                <!-- Return to owner -->
                <label
                  class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                  :class="processForm.next_step === 'return_to_owner' ? 'border-amber-300 bg-amber-50' : 'border-slate-200 hover:bg-slate-50'"
                >
                  <input type="radio" v-model="processForm.next_step" value="return_to_owner" class="mt-0.5 accent-amber-500" />
                  <div>
                    <p class="text-sm font-medium text-slate-800 flex items-center gap-1">
                      <ArrowUturnLeftIcon class="h-4 w-4 text-amber-500" /> Return to document owner
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5">
                      Send back to <strong>{{ document.creator?.name }}</strong> (original sender).
                    </p>
                  </div>
                </label>

                <!-- For File / Complete -->
                <label
                  class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                  :class="processForm.next_step === 'for_file' ? 'border-slate-400 bg-slate-50' : 'border-slate-200 hover:bg-slate-50'"
                >
                  <input type="radio" v-model="processForm.next_step" value="for_file" class="mt-0.5 accent-slate-600" />
                  <div>
                    <p class="text-sm font-medium text-slate-800">Mark as For File (Complete)</p>
                    <p class="text-xs text-slate-500 mt-0.5">Close this document — no further routing needed. This cannot be undone.</p>
                  </div>
                </label>

              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button
              @click="showProcessModal = false"
              class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="submitProcess"
              :disabled="isSubmitting"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60"
            >
              {{ isSubmitting ? 'Submitting…' : 'Submit' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
