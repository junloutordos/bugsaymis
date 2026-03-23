<template>
  <Head :title="`201 File — ${employee.name}`" />
  <AdminLayout :title="`201 File — ${employee.name}`">
    <div class="space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">201 File — {{ employee.name }}</h1>
          <p class="text-sm text-slate-500 mt-0.5">
            {{ employee.position }}
            <span v-if="employee.employee_profile?.division" class="before:content-['·'] before:mx-1.5">
              {{ employee.employee_profile.division }}
            </span>
            <span v-if="employee.employee_profile?.employee_no" class="before:content-['·'] before:mx-1.5">
              EMP# {{ employee.employee_profile.employee_no }}
            </span>
          </p>
        </div>
        <button
          v-if="canManage"
          @click="uploadPanelOpen = !uploadPanelOpen"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm shrink-0"
        >
          <PlusIcon class="h-4 w-4" />
          Upload Document
        </button>
      </div>

      <!-- Flash Messages -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" />
        {{ $page.props.flash.error }}
      </div>

      <!-- Upload Panel -->
      <div v-if="uploadPanelOpen && canManage" class="bg-white rounded-xl border border-indigo-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Upload New Document</h2>
          <button @click="uploadPanelOpen = false" class="text-slate-400 hover:text-slate-600 transition-colors">
            <XMarkIcon class="h-4 w-4" />
          </button>
        </div>
        <form @submit.prevent="submitUpload" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Title -->
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Title <span class="text-red-500">*</span></label>
            <input
              v-model="form.title"
              type="text"
              maxlength="255"
              placeholder="e.g. Appointment Order No. 2025-001"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': form.errors.title }"
            />
            <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
          </div>

          <!-- Category -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Category <span class="text-red-500">*</span></label>
            <select
              v-model="form.category"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': form.errors.category }"
            >
              <option value="">Select category…</option>
              <option v-for="cat in categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
            </select>
            <p v-if="form.errors.category" class="text-xs text-red-500 mt-1">{{ form.errors.category }}</p>
          </div>

          <!-- Document Date -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Document Date</label>
            <input
              v-model="form.document_date"
              type="date"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': form.errors.document_date }"
            />
            <p v-if="form.errors.document_date" class="text-xs text-red-500 mt-1">{{ form.errors.document_date }}</p>
          </div>

          <!-- Description -->
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <textarea
              v-model="form.description"
              rows="2"
              maxlength="1000"
              placeholder="Optional notes about this document…"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
              :class="{ 'border-red-400': form.errors.description }"
            />
            <p v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</p>
          </div>

          <!-- File -->
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">File <span class="text-slate-400 font-normal">(PDF, JPG, PNG, DOC, DOCX — max 10 MB)</span></label>
            <input
              type="file"
              accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
              @change="handleFileChange"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium file:py-1 file:px-2"
              :class="{ 'border-red-400': form.errors.file }"
            />
            <p v-if="form.errors.file" class="text-xs text-red-500 mt-1">{{ form.errors.file }}</p>
          </div>

          <!-- Actions -->
          <div class="sm:col-span-2 flex gap-3 justify-end">
            <button
              type="button"
              @click="uploadPanelOpen = false; form.reset()"
              class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors"
            >
              <ArrowUpTrayIcon class="h-4 w-4" />
              {{ form.processing ? 'Uploading…' : 'Upload' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Category Tabs -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-4 pt-4 pb-0 border-b border-slate-100 overflow-x-auto">
          <nav class="flex gap-0.5 min-w-max">
            <button
              @click="activeTab = 'all'"
              :class="[
                'px-3 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap border-b-2',
                activeTab === 'all'
                  ? 'text-indigo-600 border-indigo-600 bg-indigo-50/50'
                  : 'text-slate-500 border-transparent hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              All
              <span class="ml-1.5 inline-flex items-center justify-center rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600">
                {{ documents.length }}
              </span>
            </button>
            <button
              v-for="cat in categories"
              :key="cat.value"
              @click="activeTab = cat.value"
              :class="[
                'px-3 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap border-b-2',
                activeTab === cat.value
                  ? 'text-indigo-600 border-indigo-600 bg-indigo-50/50'
                  : 'text-slate-500 border-transparent hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              {{ cat.label }}
              <span
                v-if="countByCategory(cat.value) > 0"
                class="ml-1.5 inline-flex items-center justify-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-xs font-semibold text-indigo-700"
              >
                {{ countByCategory(cat.value) }}
              </span>
            </button>
          </nav>
        </div>

        <!-- Document List -->
        <div class="p-4">

          <!-- Empty State -->
          <div v-if="filteredDocuments.length === 0" class="py-16 text-center">
            <FolderOpenIcon class="mx-auto h-10 w-10 text-slate-300 mb-3" />
            <p class="text-sm font-medium text-slate-500">No documents found</p>
            <p class="text-xs text-slate-400 mt-1">
              {{ activeTab === 'all' ? 'No documents have been uploaded yet.' : 'No documents in this category.' }}
            </p>
          </div>

          <!-- Document Items -->
          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="doc in filteredDocuments"
              :key="doc.id"
              class="flex items-start gap-4 py-3.5 group"
            >
              <!-- File Icon -->
              <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-lg bg-slate-100 text-slate-500 mt-0.5">
                <component :is="fileIcon(doc.mime_type)" class="h-5 w-5" />
              </div>

              <!-- Info -->
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-sm font-medium text-slate-800 truncate">{{ doc.title }}</span>
                  <span :class="categoryBadgeClass(doc.category)" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                    {{ categoryLabel(doc.category) }}
                  </span>
                </div>
                <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">
                  <span v-if="doc.document_date" class="text-xs text-slate-500">
                    <CalendarIcon class="inline h-3 w-3 mr-0.5 -mt-0.5" />
                    {{ formatDate(doc.document_date) }}
                  </span>
                  <span v-if="doc.original_filename" class="text-xs text-slate-400 truncate max-w-[200px]">
                    <PaperClipIcon class="inline h-3 w-3 mr-0.5 -mt-0.5" />
                    {{ doc.original_filename }}
                  </span>
                  <span v-if="doc.file_size" class="text-xs text-slate-400">
                    {{ formatFileSize(doc.file_size) }}
                  </span>
                  <span v-if="doc.uploaded_by" class="text-xs text-slate-400">
                    Uploaded by {{ doc.uploaded_by.name }}
                  </span>
                </div>
                <p v-if="doc.description" class="text-xs text-slate-500 mt-1 line-clamp-2">{{ doc.description }}</p>
              </div>

              <!-- Actions -->
              <div class="shrink-0 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                <a
                  v-if="doc.file_path"
                  :href="route('hr.employees.documents.download', doc.id)"
                  class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 border border-indigo-200 hover:border-indigo-400 rounded-lg px-2.5 py-1.5 transition-colors"
                >
                  <ArrowDownTrayIcon class="h-3.5 w-3.5" />
                  Download
                </a>
                <button
                  v-if="canManage"
                  @click="confirmDelete(doc)"
                  class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 border border-red-200 hover:border-red-400 rounded-lg px-2.5 py-1.5 transition-colors"
                >
                  <TrashIcon class="h-3.5 w-3.5" />
                  Delete
                </button>
              </div>
            </li>
          </ul>
        </div>
      </div>

    </div>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="deleteTarget = null">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
          <div class="flex items-start gap-3 mb-4">
            <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-red-50">
              <ExclamationTriangleIcon class="h-5 w-5 text-red-500" />
            </div>
            <div>
              <h3 class="text-base font-semibold text-slate-800">Delete Document?</h3>
              <p class="text-sm text-slate-500 mt-0.5">
                "<span class="font-medium">{{ deleteTarget?.title }}</span>" will be permanently removed.
              </p>
            </div>
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="deleteTarget = null" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button @click="executeDelete" class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
              Delete
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  PlusIcon,
  ArrowUpTrayIcon,
  ArrowDownTrayIcon,
  TrashIcon,
  XMarkIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
  FolderOpenIcon,
  DocumentIcon,
  DocumentTextIcon,
  PhotoIcon,
  PaperClipIcon,
  CalendarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  employee:   Object,
  documents:  Array,
  categories: Array,
  canManage:  Boolean,
})

// ── Tab State ─────────────────────────────────────────────────────────────
const activeTab = ref('all')

const filteredDocuments = computed(() => {
  if (activeTab.value === 'all') return props.documents
  return props.documents.filter(d => d.category === activeTab.value)
})

function countByCategory (value) {
  return props.documents.filter(d => d.category === value).length
}

// ── Upload Form ───────────────────────────────────────────────────────────
const uploadPanelOpen = ref(false)

const form = useForm({
  title:         '',
  category:      '',
  document_date: '',
  description:   '',
  file:          null,
})

function handleFileChange (event) {
  form.file = event.target.files[0] ?? null
}

function submitUpload () {
  form.post(route('hr.employees.documents.store', props.employee.id), {
    forceFormData: true,
    onSuccess: () => {
      uploadPanelOpen.value = false
      form.reset()
    },
  })
}

// ── Delete ────────────────────────────────────────────────────────────────
const deleteTarget = ref(null)

function confirmDelete (doc) {
  deleteTarget.value = doc
}

function executeDelete () {
  if (! deleteTarget.value) return
  router.delete(route('hr.employees.documents.destroy', deleteTarget.value.id), {
    onFinish: () => { deleteTarget.value = null },
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────
function formatDate (dateStr) {
  if (! dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatFileSize (bytes) {
  if (! bytes) return ''
  if (bytes < 1024)        return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function fileIcon (mimeType) {
  if (! mimeType) return DocumentIcon
  if (mimeType.startsWith('image/')) return PhotoIcon
  if (mimeType === 'application/pdf' || mimeType.includes('text')) return DocumentTextIcon
  return DocumentIcon
}

function categoryLabel (value) {
  return props.categories.find(c => c.value === value)?.label ?? value
}

const categoryColors = {
  appointment:    'bg-blue-50 text-blue-700',
  pds:            'bg-purple-50 text-purple-700',
  service_record: 'bg-teal-50 text-teal-700',
  performance:    'bg-amber-50 text-amber-700',
  eligibility:    'bg-cyan-50 text-cyan-700',
  training:       'bg-green-50 text-green-700',
  medical:        'bg-rose-50 text-rose-700',
  leave:          'bg-orange-50 text-orange-700',
  other:          'bg-slate-100 text-slate-600',
}

function categoryBadgeClass (value) {
  return categoryColors[value] ?? 'bg-slate-100 text-slate-600'
}
</script>
