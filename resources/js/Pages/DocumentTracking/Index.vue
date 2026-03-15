<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import {
  PlusIcon,
  EyeIcon,
  PaperClipIcon,
  ExclamationTriangleIcon,
  LockClosedIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  documents:     Array,
  documentTypes: Array,
  users:         Array,
})

const page = usePage()
const authId = computed(() => page.props.auth?.user?.id)

// ─── Search & filter ─────────────────────────────────────────────────────────
const searchQuery  = ref('')
const activeTab    = ref('all') // all | mine | pending | overdue
const currentPage  = ref(1)
const perPage      = 10

watch(searchQuery, () => { currentPage.value = 1 })
watch(activeTab,   () => { currentPage.value = 1 })

const filteredAll = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return props.documents.filter(doc => {
    const matches = !q ||
      (doc.tracking_no    || '').toLowerCase().includes(q) ||
      (doc.subject        || '').toLowerCase().includes(q) ||
      (doc.document_type?.name || '').toLowerCase().includes(q) ||
      (doc.document_type?.code || '').toLowerCase().includes(q) ||
      (doc.creator?.name  || '').toLowerCase().includes(q) ||
      (doc.current_holder?.name || '').toLowerCase().includes(q) ||
      (doc.urgency        || '').toLowerCase().includes(q) ||
      (doc.overall_status || '').toLowerCase().includes(q)

    if (!matches) return false
    if (activeTab.value === 'mine')    return doc.creator?.id === authId.value
    if (activeTab.value === 'pending') return doc.routings?.some(r => r.receiver_id === authId.value && ['Pending','Received'].includes(r.status))
    if (activeTab.value === 'overdue') return doc.has_overdue
    return true
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredAll.value.length / perPage)))

const filteredDocs = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredAll.value.slice(start, start + perPage)
})

const myPendingCount = computed(() =>
  props.documents.filter(doc =>
    doc.routings?.some(r => r.receiver_id === authId.value && ['Pending','Received'].includes(r.status))
  ).length
)

const overdueCount = computed(() =>
  props.documents.filter(doc => doc.has_overdue).length
)

// ─── Create modal ─────────────────────────────────────────────────────────────
const showCreateModal = ref(false)

const form = useForm({
  document_type_id: '',
  subject:          '',
  description:      '',
  urgency:          'Normal',
  is_confidential:  false,
  receiver_id:      '',
  remarks:          '',
  attachments:      [],
})

const attachmentFiles = ref([])
const userSearch = ref('')

const filteredUsers = computed(() => {
  const q = userSearch.value.trim().toLowerCase()
  if (!q) return props.users.slice(0, 30)
  return props.users.filter(u =>
    (u.name || '').toLowerCase().includes(q) ||
    (u.position || '').toLowerCase().includes(q)
  ).slice(0, 30)
})

const selectedType = computed(() =>
  props.documentTypes.find(t => t.id == form.document_type_id)
)

const onFilesChange = (e) => {
  attachmentFiles.value = Array.from(e.target.files || [])
}

const openCreate = () => {
  form.reset()
  attachmentFiles.value = []
  userSearch.value = ''
  showCreateModal.value = true
}

const closeCreate = () => {
  showCreateModal.value = false
  form.reset()
  attachmentFiles.value = []
}

const submitCreate = () => {
  if (!form.document_type_id) { Swal.fire({ icon: 'warning', title: 'Select a document type' }); return }
  if (!form.subject.trim())   { Swal.fire({ icon: 'warning', title: 'Enter a subject' }); return }
  if (!form.receiver_id)      { Swal.fire({ icon: 'warning', title: 'Select a receiver' }); return }

  // Build FormData manually for file upload
  const fd = new FormData()
  fd.append('document_type_id', form.document_type_id)
  fd.append('subject',          form.subject)
  fd.append('description',      form.description || '')
  fd.append('urgency',          form.urgency)
  fd.append('is_confidential',  form.is_confidential ? '1' : '0')
  fd.append('receiver_id',      form.receiver_id)
  fd.append('remarks',          form.remarks || '')
  attachmentFiles.value.forEach(f => fd.append('attachments[]', f))

  form.transform(() => fd).post(route('document-tracking.store'), {
    forceFormData: true,
    onSuccess: () => {
      closeCreate()
      Swal.fire({ icon: 'success', title: 'Document created!', text: 'The receiver has been notified.', timer: 2000, showConfirmButton: false })
    },
    onError: (errors) => {
      Swal.fire({ icon: 'error', title: 'Failed', text: Object.values(errors).flat().join('\n') || 'Please check all fields.' })
    },
  })
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
const urgencyClass = (urgency) => {
  if (urgency === 'Very Urgent') return 'bg-red-100 text-red-700 border border-red-200'
  if (urgency === 'Urgent')      return 'bg-yellow-100 text-yellow-700 border border-yellow-200'
  return 'bg-blue-50 text-blue-700 border border-blue-100'
}

const statusClass = (status) => {
  if (status === 'Completed')  return 'bg-green-100 text-green-700'
  if (status === 'Cancelled')  return 'bg-gray-100 text-gray-600'
  return 'bg-indigo-100 text-indigo-700'
}

const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
  <Head title="Document Tracking" />
  <AdminLayout title="Document Tracking">
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">Document Tracking</h1>
        <button
          @click="openCreate"
          class="flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow text-sm"
        >
          <PlusIcon class="h-4 w-4" /> New Document
        </button>
      </div>

      <!-- Tab bar -->
      <div class="flex gap-2 mb-4 flex-wrap">
        <button
          v-for="tab in [
            { key: 'all',     label: 'All' },
            { key: 'mine',    label: 'Created by Me' },
            { key: 'pending', label: 'Pending My Action', count: myPendingCount },
            { key: 'overdue', label: 'Overdue', count: overdueCount },
          ]"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'px-3 py-1.5 rounded-full text-sm font-medium transition',
            activeTab === tab.key
              ? 'bg-indigo-600 text-white'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
          ]"
        >
          {{ tab.label }}
          <span v-if="tab.count" class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold"
            :class="activeTab === tab.key ? 'bg-white text-indigo-700' : 'bg-red-500 text-white'"
          >{{ tab.count }}</span>
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Search -->
        <div class="mb-4 flex items-center gap-2">
          <div class="relative flex-1 sm:max-w-sm">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search tracking no., subject, type..."
              class="w-full pl-9 rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            />
          </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="w-full border border-gray-200 text-sm">
            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
              <tr>
                <th class="px-4 py-3 text-left">Tracking #</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-left">Subject</th>
                <th class="px-4 py-3 text-left">Urgency</th>
                <th class="px-4 py-3 text-left">Created By</th>
                <th class="px-4 py-3 text-left">Current Holder</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Created</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="doc in filteredDocs" :key="doc.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-mono font-semibold text-indigo-700 whitespace-nowrap">
                  {{ doc.tracking_no }}
                  <span v-if="doc.has_overdue" class="ml-1 inline-block w-2 h-2 rounded-full bg-red-500" title="Overdue"></span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <span class="font-medium text-gray-800">{{ doc.document_type?.code }}</span>
                  <span class="text-gray-400 text-xs ml-1">{{ doc.document_type?.name }}</span>
                </td>
                <td class="px-4 py-3 max-w-xs">
                  <div class="flex items-center gap-1">
                    <LockClosedIcon v-if="doc.is_confidential" class="h-3.5 w-3.5 text-purple-500 shrink-0" title="Confidential" />
                    <span class="truncate">{{ doc.subject }}</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', urgencyClass(doc.urgency)]">
                    {{ doc.urgency }}
                  </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">{{ doc.creator?.name ?? '—' }}</td>
                <td class="px-4 py-3 whitespace-nowrap">{{ doc.current_holder?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', statusClass(doc.overall_status)]">
                    {{ doc.overall_status }}
                  </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ formatDate(doc.created_at) }}</td>
                <td class="px-4 py-3 text-center">
                  <button
                    @click="router.visit(route('document-tracking.show', doc.id))"
                    class="p-2 rounded-full bg-indigo-100 hover:bg-indigo-200 text-indigo-700"
                    title="View"
                  >
                    <EyeIcon class="h-4 w-4" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredDocs.length === 0">
                <td colspan="9" class="px-4 py-8 text-center text-gray-500">No documents found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden space-y-3">
          <div
            v-for="doc in filteredDocs"
            :key="doc.id"
            class="border rounded-xl p-3 bg-white shadow-sm"
            :class="doc.has_overdue ? 'border-red-300' : 'border-gray-200'"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="flex items-center gap-1 mb-0.5">
                  <span class="font-mono text-xs font-bold text-indigo-600">{{ doc.tracking_no }}</span>
                  <ExclamationTriangleIcon v-if="doc.has_overdue" class="h-3.5 w-3.5 text-red-500" title="Overdue" />
                  <LockClosedIcon v-if="doc.is_confidential" class="h-3.5 w-3.5 text-purple-500" title="Confidential" />
                </div>
                <p class="font-semibold text-gray-800 text-sm truncate">{{ doc.subject }}</p>
                <p class="text-xs text-gray-500">{{ doc.document_type?.name }}</p>
              </div>
              <div class="shrink-0 flex flex-col items-end gap-1">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', urgencyClass(doc.urgency)]">
                  {{ doc.urgency }}
                </span>
                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', statusClass(doc.overall_status)]">
                  {{ doc.overall_status }}
                </span>
              </div>
            </div>
            <div class="mt-2 text-xs text-gray-500 flex flex-wrap gap-x-3">
              <span>By: {{ doc.creator?.name ?? '—' }}</span>
              <span>Holder: {{ doc.current_holder?.name ?? '—' }}</span>
            </div>
            <div class="mt-2">
              <button
                @click="router.visit(route('document-tracking.show', doc.id))"
                class="w-full flex items-center justify-center gap-1 px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm"
              >
                <EyeIcon class="h-4 w-4" /> View Document
              </button>
            </div>
          </div>
          <div v-if="filteredDocs.length === 0" class="text-center text-gray-500 py-8">No documents found.</div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50 text-sm"
          >Prev</button>
          <span class="text-sm text-gray-600">Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50 text-sm"
          >Next</button>
        </div>
      </div>

      <!-- ─── Create Document Modal ──────────────────────────────────────────── -->
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white w-full h-full sm:h-auto sm:rounded-xl sm:max-w-2xl sm:shadow-xl p-4 sm:p-6 relative overflow-auto">
          <button @click="closeCreate" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 text-xl leading-none">✕</button>
          <h2 class="text-lg font-bold text-gray-800 mb-4">Create & Route Document</h2>

          <div class="space-y-4">
            <!-- Type + Subject row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Document Type <span class="text-red-500">*</span></label>
                <select v-model="form.document_type_id" class="w-full rounded-lg border-gray-300 text-sm">
                  <option value="">— Select Type —</option>
                  <option v-for="t in documentTypes" :key="t.id" :value="t.id">
                    [{{ t.code }}] {{ t.name }}
                  </option>
                </select>
                <p v-if="selectedType" class="mt-1 text-xs text-gray-500">
                  Lead time: <strong>{{ selectedType.lead_time_hours }}h</strong> per ARTA/CSC guidelines
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Urgency <span class="text-red-500">*</span></label>
                <select v-model="form.urgency" class="w-full rounded-lg border-gray-300 text-sm">
                  <option>Normal</option>
                  <option>Urgent</option>
                  <option>Very Urgent</option>
                </select>
              </div>
            </div>

            <!-- Subject -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
              <input
                v-model="form.subject"
                type="text"
                placeholder="e.g. Leave Application — Juan dela Cruz"
                class="w-full rounded-lg border-gray-300 text-sm"
              />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description / Particulars</label>
              <textarea
                v-model="form.description"
                rows="3"
                placeholder="Brief description of the document..."
                class="w-full rounded-lg border-gray-300 text-sm"
              ></textarea>
            </div>

            <!-- Receiver -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Route To (Receiver) <span class="text-red-500">*</span></label>
              <input
                v-model="userSearch"
                type="text"
                placeholder="Search by name or position..."
                class="w-full rounded-lg border-gray-300 text-sm mb-1"
              />
              <select v-model="form.receiver_id" size="4" class="w-full rounded-lg border-gray-300 text-sm">
                <option v-for="u in filteredUsers" :key="u.id" :value="u.id">
                  {{ u.name }}{{ u.position ? ' — ' + u.position : '' }}
                </option>
              </select>
            </div>

            <!-- Remarks -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Remarks / Instructions to Receiver</label>
              <textarea
                v-model="form.remarks"
                rows="2"
                placeholder="Any special instructions for the receiver..."
                class="w-full rounded-lg border-gray-300 text-sm"
              ></textarea>
            </div>

            <!-- Confidential + Attachments -->
            <div class="flex flex-col sm:flex-row gap-4">
              <div class="flex items-center gap-2">
                <input id="conf" type="checkbox" v-model="form.is_confidential" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                <label for="conf" class="text-sm text-gray-700 flex items-center gap-1">
                  <LockClosedIcon class="h-4 w-4 text-purple-500" /> Mark as Confidential
                </label>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <PaperClipIcon class="inline h-4 w-4 mr-1" />Attach Files
              </label>
              <input
                type="file"
                multiple
                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif"
                @change="onFilesChange"
                class="w-full text-sm text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700 file:text-sm hover:file:bg-indigo-100"
              />
              <p class="text-xs text-gray-400 mt-0.5">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG — max 10 MB each</p>
              <ul v-if="attachmentFiles.length" class="mt-1 space-y-0.5">
                <li v-for="f in attachmentFiles" :key="f.name" class="text-xs text-gray-600 flex items-center gap-1">
                  <PaperClipIcon class="h-3 w-3 shrink-0 text-gray-400" />{{ f.name }}
                </li>
              </ul>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-2 pt-2">
              <button @click="closeCreate" class="px-4 py-2 rounded-lg border text-sm">Cancel</button>
              <button
                @click="submitCreate"
                :disabled="form.processing"
                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-60 inline-flex items-center gap-1"
              >
                <span v-if="form.processing">Routing…</span>
                <span v-else>Create & Route</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
