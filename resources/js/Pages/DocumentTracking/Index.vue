<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { badgeBase, statusBadgeClass, priorityBadgeClass, originBadgeClass } from '@/Composables/useStatusBadge.js'
import FlashMessage from '@/Components/FlashMessage.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  PlusIcon, ArrowUpTrayIcon, Cog6ToothIcon, MagnifyingGlassIcon,
  LockClosedIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  documents:      Array,
  documentTypes:  Array,
  users:          Array,
  canLogExternal: Boolean,
  isAdmin:        Boolean,
})

const page          = usePage()
const currentUserId = computed(() => page.props.auth?.user?.id)

// ── Tabs ───────────────────────────────────────────────────────────────────
const activeTab = ref('all')
const tabs = [
  { key: 'all',       label: 'All' },
  { key: 'mine',      label: 'For My Action' },
  { key: 'external',  label: 'External Incoming' },
  { key: 'internal',  label: 'Internal' },
  { key: 'completed', label: 'Completed' },
]

const myActionCount = computed(() =>
  (props.documents ?? []).filter(d =>
    d.routings?.some(r => r.receiver?.id === currentUserId.value && ['Pending', 'Received'].includes(r.status))
  ).length
)

// ── Filters ────────────────────────────────────────────────────────────────
const search          = ref('')
const filterTypeId    = ref('')
const filterPriority  = ref('')
const currentPage     = ref(1)
const PER_PAGE        = 15

watch([search, filterTypeId, filterPriority, activeTab], () => { currentPage.value = 1 })

function matchesTab(doc) {
  if (activeTab.value === 'external')  return doc.origin_type === 'external'
  if (activeTab.value === 'internal')  return doc.origin_type === 'internal'
  if (activeTab.value === 'completed') return doc.overall_status === 'Completed'
  if (activeTab.value === 'mine')
    return doc.routings?.some(r => r.receiver?.id === currentUserId.value && ['Pending', 'Received'].includes(r.status))
  return true
}

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  return (props.documents ?? []).filter(doc => {
    if (!matchesTab(doc)) return false
    if (filterTypeId.value  && doc.document_type?.id !== +filterTypeId.value)  return false
    if (filterPriority.value && doc.priority !== filterPriority.value)          return false
    if (!q) return true
    return (doc.tracking_no    ?? '').toLowerCase().includes(q)
        || (doc.subject        ?? '').toLowerCase().includes(q)
        || (doc.source_office  ?? '').toLowerCase().includes(q)
        || (doc.sender_name    ?? '').toLowerCase().includes(q)
        || (doc.document_number?? '').toLowerCase().includes(q)
        || (doc.document_type?.name ?? '').toLowerCase().includes(q)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const s = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(s, s + PER_PAGE)
})

// ── Status helpers ─────────────────────────────────────────────────────────
function overallBadge(doc) {
  if (doc.overall_status === 'Completed') return 'bg-emerald-100 text-emerald-700'
  if (doc.overall_status === 'Returned')  return 'bg-red-100 text-red-700'
  if (doc.routings?.some(r => r.is_overdue)) return 'bg-red-100 text-red-700'
  if (doc.routings?.some(r => r.status === 'Pending')) return 'bg-amber-100 text-amber-700'
  return 'bg-blue-100 text-blue-700'
}
function overallLabel(doc) {
  if (doc.overall_status === 'Completed') return 'Completed'
  if (doc.overall_status === 'Returned')  return 'Returned'
  if (doc.routings?.some(r => r.is_overdue)) return 'Overdue'
  if (doc.routings?.some(r => r.status === 'Pending')) return 'Pending Action'
  return doc.overall_status
}
// priorityBadgeClass + originBadgeClass imported from useStatusBadge.js

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

// ── Modal ──────────────────────────────────────────────────────────────────
const showModal  = ref(false)
const logOrigin  = ref('external')
const submitting = ref(false)
const errors     = ref({})

const form = ref({
  origin_type: 'external', document_type_id: '', subject: '', description: '',
  priority: 'Normal', urgency: 'Normal', is_confidential: false,
  source_office: '', sender_name: '', date_of_document: '',
  date_received: new Date().toISOString().slice(0, 10),
  document_number: '', deadline_at: '', receiver_id: '', instructions: '',
  scan_base64: null, scan_filename: '', scan_mime: '',
})

function openModal(origin) {
  logOrigin.value       = origin
  form.value.origin_type = origin
  errors.value          = {}
  showModal.value       = true
}

function handleScan(e) {
  const file = e.target.files[0]
  if (!file) return
  form.value.scan_filename = file.name
  form.value.scan_mime     = file.type
  const reader = new FileReader()
  reader.onload = ev => { form.value.scan_base64 = ev.target.result }
  reader.readAsDataURL(file)
}

function submitForm() {
  submitting.value = true
  errors.value     = {}
  router.post(route('document-tracking.store'), { ...form.value }, {
    onSuccess: () => { showModal.value = false },
    onError:   e  => { errors.value = e },
    onFinish:  ()  => { submitting.value = false },
    preserveScroll: true,
  })
}

const selectedType = computed(() => props.documentTypes?.find(t => t.id === +form.value.document_type_id))
// External docs never need a manual receiver — Records is always the initial holder
const needsManualReceiver = computed(() =>
  logOrigin.value !== 'external' &&
  (!selectedType.value || selectedType.value.routing_type === 'manual' || !selectedType.value.routing_steps?.length)
)
</script>

<template>
  <Head title="Document Tracking" />
  <AdminLayout title="Document Tracking">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
      <div>
        <h2 class="text-lg font-bold text-slate-800">Document Tracking System</h2>
        <p class="text-xs text-slate-500 mt-0.5">Track internal and external document routing across all offices</p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <a v-if="isAdmin" :href="route('document-tracking.types.index')"
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
          <Cog6ToothIcon class="h-4 w-4" /> Document Types
        </a>
        <button @click="openModal('internal')"
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">
          <PlusIcon class="h-4 w-4" /> Internal
        </button>
        <button v-if="canLogExternal" @click="openModal('external')"
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg">
          <ArrowUpTrayIcon class="h-4 w-4" /> Log External
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-slate-200 mb-4 overflow-x-auto">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors border-b-2"
        :class="activeTab === tab.key ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-700'">
        {{ tab.label }}
        <span v-if="tab.key === 'mine' && myActionCount > 0"
          class="ml-1.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
          {{ myActionCount > 9 ? '9+' : myActionCount }}
        </span>
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-2 mb-4">
      <div class="relative flex-1">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
        <input v-model="search" type="text" placeholder="Tracking no., subject, source office, sender…"
          class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <select v-model="filterTypeId"
        class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Types</option>
        <option v-for="t in documentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
      <select v-model="filterPriority"
        class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Priorities</option>
        <option>Normal</option><option>Urgent</option><option>Rush</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <EmptyState v-if="displayed.length === 0" title="No documents found" subtitle="Try adjusting your filters." />
      <table v-else class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Tracking No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Subject</th>
            <th class="hidden md:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Origin / Type</th>
            <th class="hidden lg:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Priority</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="hidden lg:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Holder</th>
            <th class="hidden md:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
            <th class="px-3 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="doc in displayed" :key="doc.id" class="hover:bg-slate-50 transition-colors cursor-pointer"
            @click="router.visit(route('document-tracking.show', doc.id))">
            <td class="px-4 py-3">
              <span class="font-mono text-xs font-bold text-indigo-600">{{ doc.tracking_no }}</span>
              <div v-if="doc.document_number" class="text-[11px] text-slate-400">Ref: {{ doc.document_number }}</div>
            </td>
            <td class="px-4 py-3 max-w-[220px]">
              <div class="flex items-center gap-1">
                <LockClosedIcon v-if="doc.is_confidential" class="h-3.5 w-3.5 text-purple-500 shrink-0" />
                <ExclamationTriangleIcon v-if="doc.routings?.some(r => r.is_overdue)" class="h-3.5 w-3.5 text-red-500 shrink-0" />
                <span class="font-medium text-slate-800 truncate text-xs">{{ doc.subject }}</span>
              </div>
              <div v-if="doc.source_office" class="text-[11px] text-emerald-600 mt-0.5">{{ doc.source_office }}</div>
            </td>
            <td class="hidden md:table-cell px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold mr-1"
                :class="originBadgeClass(doc.origin_type)">
                {{ doc.origin_type === 'external' ? 'Ext' : 'Int' }}
              </span>
              <span class="text-xs text-slate-500">{{ doc.document_type?.name ?? '—' }}</span>
            </td>
            <td class="hidden lg:table-cell px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                :class="priorityBadgeClass(doc.priority)">{{ doc.priority }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold" :class="overallBadge(doc)">
                {{ overallLabel(doc) }}
              </span>
            </td>
            <td class="hidden lg:table-cell px-4 py-3 text-xs text-slate-600">{{ doc.current_holder?.name ?? '—' }}</td>
            <td class="hidden md:table-cell px-4 py-3 text-xs text-slate-500">
              {{ fmtDate(doc.date_received ?? doc.created_at) }}
            </td>
            <td class="px-3 py-3 text-right">
              <span class="text-indigo-600 text-xs font-medium">View →</span>
            </td>
          </tr>
        </tbody>
      </table>

      <PaginationControl
        :current-page="currentPage" :total-pages="totalPages" :total="filtered.length"
        @prev="currentPage--" @next="currentPage++" />
    </div>

    <!-- Log / Create Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-black/40" @click="showModal = false" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col">

          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between rounded-t-2xl">
            <div>
              <h3 class="font-bold text-slate-800">
                {{ logOrigin === 'external' ? '📥 Log External Incoming Document' : '📄 Create Internal Document' }}
              </h3>
              <p class="text-xs text-slate-500 mt-0.5">
                {{ logOrigin === 'external'
                  ? 'Record a document received from an external agency/office'
                  : 'Route an internal document across offices or personnel' }}
              </p>
            </div>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold leading-none">✕</button>
          </div>

          <!-- Body -->
          <form @submit.prevent="submitForm" class="overflow-y-auto px-6 py-5 space-y-4 flex-1">

            <!-- Document Type -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Document Type <span class="text-red-500">*</span></label>
              <select v-model="form.document_type_id" required
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Select type…</option>
                <option v-for="t in documentTypes" :key="t.id" :value="t.id">[{{ t.code }}] {{ t.name }}</option>
              </select>
              <p v-if="selectedType" class="text-xs text-slate-500 mt-1">
                Routing: <strong class="capitalize">{{ selectedType.routing_type }}</strong>
                · Lead time: {{ selectedType.lead_time_hours }}h
                <span v-if="selectedType.routing_steps?.length > 0">
                  · {{ selectedType.routing_steps.length }} auto-configured step(s)
                </span>
              </p>
              <p v-if="errors.document_type_id" class="text-xs text-red-500 mt-1">{{ errors.document_type_id }}</p>
            </div>

            <!-- Subject -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Subject <span class="text-red-500">*</span></label>
              <input v-model="form.subject" type="text" required
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Brief subject / title of the document" />
              <p v-if="errors.subject" class="text-xs text-red-500 mt-1">{{ errors.subject }}</p>
            </div>

            <!-- External fields -->
            <template v-if="logOrigin === 'external'">
              <div class="bg-green-50 border border-green-200 rounded-xl p-4 space-y-3">
                <p class="text-xs font-semibold text-green-700 uppercase tracking-wide">External Document Details</p>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Source Office <span class="text-red-500">*</span></label>
                    <input v-model="form.source_office" type="text" required
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="e.g. DepEd Region XIII" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Sender Name</label>
                    <input v-model="form.sender_name" type="text"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="Signing official" />
                  </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Doc Date</label>
                    <input v-model="form.date_of_document" type="date"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Date Received</label>
                    <input v-model="form.date_received" type="date"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Ref. No.</label>
                    <input v-model="form.document_number" type="text"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="Control no." />
                  </div>
                </div>
              </div>
            </template>

            <!-- Priority / Urgency -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Priority</label>
                <select v-model="form.priority"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option>Normal</option><option>Urgent</option><option>Rush</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Urgency</label>
                <select v-model="form.urgency"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option>Normal</option><option>Urgent</option><option>Very Urgent</option>
                </select>
              </div>
            </div>

            <!-- Manual receiver -->
            <div v-if="needsManualReceiver">
              <label class="block text-sm font-medium text-slate-700 mb-1">Route To <span class="text-red-500">*</span></label>
              <select v-model="form.receiver_id"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Select recipient…</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>

            <!-- Instructions -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Instructions / Routing Notes</label>
              <textarea v-model="form.instructions" rows="2"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Instructions for the first receiver or routing notes…" />
            </div>

            <!-- Deadline -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Deadline (optional)</label>
              <input v-model="form.deadline_at" type="datetime-local"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <!-- Scan / Attachment -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Scan / Attachment</label>
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleScan"
                class="w-full text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
              <p class="text-xs text-slate-400 mt-1">PDF, JPG, PNG · Max 20 MB · Saved to Google Drive Records folder</p>
              <p v-if="form.scan_filename" class="text-xs text-emerald-600 mt-1">✓ {{ form.scan_filename }}</p>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Description (optional)</label>
              <textarea v-model="form.description" rows="2"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Additional details about the document…" />
            </div>

            <!-- Confidential -->
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.is_confidential" class="rounded border-slate-300 text-indigo-600" />
              <span class="text-sm text-slate-700 flex items-center gap-1">
                <LockClosedIcon class="h-3.5 w-3.5 text-purple-500" /> Mark as confidential
              </span>
            </label>

          </form>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 rounded-b-2xl bg-white">
            <button type="button" @click="showModal = false"
              class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
              Cancel
            </button>
            <button @click="submitForm" :disabled="submitting"
              class="px-5 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-50"
              :class="logOrigin === 'external' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-indigo-600 hover:bg-indigo-700'">
              {{ submitting ? 'Logging…' : (logOrigin === 'external' ? 'Log Document' : 'Create & Route') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
