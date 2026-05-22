<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon, ArrowUpOnSquareIcon } from "@heroicons/vue/24/outline"
import { useDivisions } from "@/Composables/useDivisions.js"
import { storageUrl } from "@/Composables/useStorage.js"
import { ref } from 'vue'
import { useSubmit } from "@/Composables/useSubmit"

// Props from backend (DivisionsController@index)
const props = defineProps({
  divisions: Array,
  users: Array, // 👈 all possible chiefs
})

// Composable: all divisions logic
const {
  divisionsList,
  showModal,
  modalMode,
  selectedDivision,
  searchQuery,
  currentPage,
  totalPages,
  filteredDivisions,
  form,
  openModal,
  closeModal,
  submitDivision,
  deleteDivision,
} = useDivisions(props)

const { isSubmitting: isUploading, submit: submitSignature } = useSubmit()

// Upload signature modal state
const showUploadModal = ref(false)
const uploadDivision = ref(null)
const uploadFile = ref(null)
const previewUrl = ref(null)

const openUploadModal = (division) => {
  uploadDivision.value = division
  showUploadModal.value = true
  uploadFile.value = null
  previewUrl.value = storageUrl(division.signature_path)
}

const closeUploadModal = () => {
  showUploadModal.value = false
  uploadDivision.value = null
  uploadFile.value = null
  previewUrl.value = null
}

const onFileChange = (e) => {
  const f = e.target.files[0]
  if (!f) return
  uploadFile.value = f
  previewUrl.value = URL.createObjectURL(f)
}

const submitUpload = async () => {
  if (!uploadFile.value || !uploadDivision.value) return
  const reader = new FileReader()
  reader.onload = async (ev) => {
    try {
      await axios.post(`/users-divisions/${uploadDivision.value.id}/upload-signature`, {
        signature_base64: ev.target.result,
      })
      closeUploadModal()
      window.location.reload()
    } catch (err) {
      alert(err.response?.data?.message ?? 'Failed to upload signature')
    }
  }
  reader.readAsDataURL(uploadFile.value)
}
</script>

<template>
  <Head title="Divisions" />
  <AdminLayout title="Divisions Management">
    <div>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Divisions List</h1>
        <button
          @click="openModal('create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
        >
          <PlusIcon class="w-4 h-4" /> New Division
        </button>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search divisions..."
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64"
        />
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Division Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Acronym</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Chief</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Year Assigned</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Created At</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="division in filteredDivisions" :key="division.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ division.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ division.division_name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ division.acronym ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ division.divisionchief?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ division.year ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                    :class="division.status==='active' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'"
                  >
                    {{ division.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(division.created_at).toLocaleDateString() }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <button @click="openModal('view', division)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                      <EyeIcon class="w-4 h-4"/>
                    </button>
                    <button @click="openModal('edit', division)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                      <PencilSquareIcon class="w-4 h-4"/>
                    </button>
                    <button @click="openUploadModal(division)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Upload signature">
                      <ArrowUpOnSquareIcon class="w-4 h-4"/>
                    </button>
                    <button @click="deleteDivision(division)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors">
                      <TrashIcon class="w-4 h-4"/>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredDivisions.length===0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">
                  No divisions found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button
            @click="currentPage--"
            :disabled="currentPage===1"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40"
          >
            Prev
          </button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage===totalPages"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>

      <!-- Division Modal -->
      <div
        v-if="showModal"
        class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4"
      >
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode==='create' ? 'New Division' : modalMode==='edit' ? 'Edit Division' : 'View Division' }}
            </h2>
            <button
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
              @click="closeModal"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="px-6 py-5">
            <!-- VIEW MODE -->
            <div v-if="modalMode==='view' && selectedDivision" class="space-y-2 text-sm text-slate-700">
              <p>Division: <strong>{{ selectedDivision.division_name }}</strong></p>
              <p>Acronym: <strong>{{ selectedDivision.acronym ?? '—' }}</strong></p>
              <p>Chief: <strong>{{ selectedDivision.divisionchief?.name ?? '—' }}</strong></p>
              <p>Year Assigned: <strong>{{ selectedDivision.year ?? '—' }}</strong></p>
              <p>Status:
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                  :class="selectedDivision.status==='active' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'"
                >
                  {{ selectedDivision.status }}
                </span>
              </p>
              <p>Created At: <strong>{{ new Date(selectedDivision.created_at).toLocaleString() }}</strong></p>
            </div>

            <!-- CREATE / EDIT FORM -->
            <form v-else @submit.prevent="submitDivision" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Division Name</label>
                <input
                  v-model="form.division_name"
                  type="text"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  required
                />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Acronym (optional)</label>
                <input
                  v-model="form.acronym"
                  type="text"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  placeholder="e.g. FAD"
                  maxlength="20"
                />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Division Chief (optional)</label>
                <select
                  v-model="form.division_chief_id"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                >
                  <option :value="null">— None —</option>
                  <option v-for="user in props.users" :key="user.id" :value="user.id">
                    {{ user.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Year Assigned</label>
                <input
                  v-model="form.year"
                  type="number"
                  min="1900"
                  max="2100"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  placeholder="e.g. 2023"
                />
              </div>

              <!-- Status -->
              <div v-if="modalMode === 'edit'">
                <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select
                  v-model="form.status"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                >
                  <option value="active">Active</option>
                  <option value="not_active">Not Active</option>
                </select>
              </div>
              <input v-else type="hidden" v-model="form.status" value="active" />

              <div class="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  @click="closeModal"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
                >
                  Save
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Signature Modal -->
    <div v-if="showUploadModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-base font-semibold text-slate-800">Upload Electronic Signature</h3>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeUploadModal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Signature Image (PNG/JPG/SVG)</label>
            <input type="file" accept="image/*" class="mt-2 text-sm text-slate-600" @change="onFileChange" />
          </div>

          <div v-if="previewUrl">
            <p class="text-xs text-slate-500 mb-1">Preview:</p>
            <img :src="previewUrl" alt="preview" class="h-24 mt-2 object-contain" />
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click="closeUploadModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
          <button @click="submitUpload" :disabled="isUploading" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">{{ isUploading ? 'Uploading…' : 'Upload' }}</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
