<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { userDisplayName } from '@/Utils/userDisplay.js'
import {
  PlusIcon, ArrowUpTrayIcon, Cog6ToothIcon, MagnifyingGlassIcon,
  LockClosedIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  documents:       Array,
  documentTypes:   Array,
  users:           Array,
  canLogExternal:  Boolean,
  canSeeExternal:  Boolean,
  isAdmin:         Boolean,
})

const page          = usePage()
const currentUserId = computed(() => page.props.auth?.user?.id)

// ── Tabs ───────────────────────────────────────────────────────────────────
const activeTab = ref('all')
const tabs = computed(() => [
  { key: 'all',       label: 'All' },
  { key: 'mine',      label: 'For My Action' },
  ...(props.canSeeExternal ? [{ key: 'external', label: 'External Incoming' }] : []),
  { key: 'internal',  label: 'Internal' },
  { key: 'completed', label: 'Completed' },
])

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
// Returns an AppBadge color key (not a raw Tailwind class).
function overallBadge(doc) {
  if (doc.overall_status === 'Completed') return 'green'
  if (doc.overall_status === 'Returned')  return 'red'
  if (doc.routings?.some(r => r.is_overdue)) return 'red'
  if (doc.routings?.some(r => r.status === 'Pending')) return 'amber'
  return 'blue'
}
function overallLabel(doc) {
  if (doc.overall_status === 'Completed') return 'Completed'
  if (doc.overall_status === 'Returned')  return 'Returned'
  if (doc.routings?.some(r => r.is_overdue)) return 'Overdue'
  if (doc.routings?.some(r => r.status === 'Pending')) return 'Pending Action'
  return doc.overall_status
}
function priorityColor(priority) {
  const map = { Rush: 'red', Urgent: 'amber', Normal: 'slate' }
  return map[priority] ?? 'slate'
}
function originColor(originType) {
  return originType === 'external' ? 'green' : 'indigo'
}

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
  logOrigin.value            = origin
  form.value.origin_type     = origin
  form.value.document_type_id = ''   // reset so stale selection from opposite category is cleared
  errors.value               = {}
  showModal.value            = true
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

// Filter types by the current log origin
const availableTypes = computed(() =>
  (props.documentTypes ?? []).filter(t =>
    t.applicable_to === logOrigin.value || t.applicable_to === 'both'
  )
)

const selectedType = computed(() => availableTypes.value.find(t => t.id === +form.value.document_type_id))
// External docs never need a manual receiver — Records → OCD is auto-routed
const needsManualReceiver = computed(() =>
  logOrigin.value !== 'external' &&
  (!selectedType.value || selectedType.value.routing_type === 'manual' || !selectedType.value.routing_steps?.length)
)
</script>

<template>
  <Head title="Document Tracking" />
  <AdminLayout title="Document Tracking">
    <div class="space-y-5">

      <AppPageHeader
        title="Document Tracking System"
        subtitle="Track internal and external document routing across all offices"
      >
        <template #actions>
          <AppButton
            v-if="isAdmin"
            as="link"
            variant="secondary"
            :href="route('document-tracking.types.index')"
          >
            <Cog6ToothIcon class="h-4 w-4" />
            Document Types
          </AppButton>
          <AppButton @click="openModal('internal')">
            <PlusIcon class="h-4 w-4" />
            Internal
          </AppButton>
          <AppButton v-if="canLogExternal" variant="success" @click="openModal('external')">
            <ArrowUpTrayIcon class="h-4 w-4" />
            Log External
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Tabs -->
      <div class="flex gap-0 border-b border-slate-200 overflow-x-auto">
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
      <AppFilterBar>
        <div class="relative min-w-[220px] flex-1">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <AppInput
            v-model="search"
            placeholder="Tracking no., subject, source office, sender..."
            class="[&_input]:pl-9"
          />
        </div>
        <AppSelect v-model="filterTypeId" placeholder="All Types" class="min-w-[160px]">
          <option v-for="t in availableTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
        </AppSelect>
        <AppSelect v-model="filterPriority" placeholder="All Priorities" class="min-w-[150px]">
          <option>Normal</option><option>Urgent</option><option>Rush</option>
        </AppSelect>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="displayed.length === 0" :skeleton-cols="8">
        <template #head>
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
        </template>

        <tr v-for="doc in displayed" :key="doc.id" class="hover:bg-slate-50/60 cursor-pointer"
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
            <AppBadge :color="originColor(doc.origin_type)" class="mr-1">
              {{ doc.origin_type === 'external' ? 'Ext' : 'Int' }}
            </AppBadge>
            <span class="text-xs text-slate-500">{{ doc.document_type?.name ?? '—' }}</span>
          </td>
          <td class="hidden lg:table-cell px-4 py-3">
            <AppBadge :color="priorityColor(doc.priority)">{{ doc.priority }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="overallBadge(doc)">{{ overallLabel(doc) }}</AppBadge>
          </td>
          <td class="hidden lg:table-cell px-4 py-3 text-xs text-slate-600">{{ doc.current_holder?.name ?? '—' }}</td>
          <td class="hidden md:table-cell px-4 py-3 text-xs text-slate-500">
            {{ fmtDate(doc.date_received ?? doc.created_at) }}
          </td>
          <td class="px-3 py-3 text-right">
            <span class="text-indigo-600 text-xs font-medium">View →</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="doc in displayed" :key="doc.id" class="p-4 space-y-2 cursor-pointer"
            @click="router.visit(route('document-tracking.show', doc.id))">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <span class="font-mono text-xs font-bold text-indigo-600">{{ doc.tracking_no }}</span>
                <div class="flex items-center gap-1 mt-0.5">
                  <LockClosedIcon v-if="doc.is_confidential" class="h-3.5 w-3.5 text-purple-500 shrink-0" />
                  <ExclamationTriangleIcon v-if="doc.routings?.some(r => r.is_overdue)" class="h-3.5 w-3.5 text-red-500 shrink-0" />
                  <p class="font-medium text-slate-800 text-sm truncate">{{ doc.subject }}</p>
                </div>
                <p v-if="doc.source_office" class="text-[11px] text-emerald-600 mt-0.5">{{ doc.source_office }}</p>
              </div>
              <AppBadge :color="overallBadge(doc)">{{ overallLabel(doc) }}</AppBadge>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
              <AppBadge :color="originColor(doc.origin_type)">{{ doc.origin_type === 'external' ? 'Ext' : 'Int' }}</AppBadge>
              <span class="text-xs text-slate-500">{{ doc.document_type?.name ?? '—' }}</span>
              <AppBadge :color="priorityColor(doc.priority)">{{ doc.priority }}</AppBadge>
            </div>
            <div class="flex items-center justify-between pt-1 text-xs text-slate-500">
              <span>{{ doc.current_holder?.name ?? '—' }} &middot; {{ fmtDate(doc.date_received ?? doc.created_at) }}</span>
              <span class="text-indigo-600 font-medium">View →</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No documents found" subtitle="Try adjusting your filters." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage" :total-pages="totalPages" :total="filtered.length"
            @prev="currentPage--" @next="currentPage++" @page="currentPage = $event" />
        </template>
      </AppTable>

    </div>

    <!-- Log / Create Modal -->
    <AppModal
      :show="showModal"
      size="2xl"
      :title="logOrigin === 'external' ? '📥 Log External Incoming Document' : '📄 Create Internal Document'"
      :subtitle="logOrigin === 'external'
        ? 'Record a document received from an external agency/office'
        : 'Route an internal document across offices or personnel'"
      @close="showModal = false"
    >
      <form @submit.prevent="submitForm" class="space-y-4">

        <!-- Document Type -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Document Type <span class="text-red-500">*</span></label>
          <select v-model="form.document_type_id" required
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">Select type…</option>
            <option v-for="t in availableTypes" :key="t.id" :value="t.id">[{{ t.code }}] {{ t.name }}</option>
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
            <option v-for="u in users" :key="u.id" :value="u.id">{{ userDisplayName(u, users) }}</option>
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

      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :variant="logOrigin === 'external' ? 'success' : 'primary'" :loading="submitting" @click="submitForm">
          {{ logOrigin === 'external' ? 'Log Document' : 'Create & Route' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
