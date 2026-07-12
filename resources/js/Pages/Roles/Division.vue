<template>
  <Head title="Divisions" />
  <AdminLayout title="Divisions Management">
    <div class="space-y-5">

      <AppPageHeader title="Divisions List" subtitle="Manage campus divisions and their chiefs.">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> New Division
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search divisions..."
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64"
        />
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="filteredDivisions.length === 0" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Division Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Acronym</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Chief</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Year Assigned</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Created At</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="division in filteredDivisions" :key="division.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ division.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ division.division_name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ division.acronym ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ division.divisionchief?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ division.year ?? '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(division.status)">{{ division.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(division.created_at).toLocaleDateString() }}</td>
          <td class="px-4 py-3">
            <div class="flex justify-center gap-1 items-center">
              <AppIconButton label="View division" @click="openModal('view', division)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit division" @click="openModal('edit', division)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Upload signature" @click="openUploadModal(division)">
                <ArrowUpOnSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete division" variant="danger" @click="deleteDivision(division)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="division in filteredDivisions" :key="division.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-500">#{{ division.id }}</p>
                <p class="font-semibold text-slate-800">{{ division.division_name }}</p>
                <p class="text-xs text-slate-500">{{ division.acronym ?? '—' }} &middot; Chief: {{ division.divisionchief?.name ?? '—' }}</p>
                <p class="text-xs text-slate-400">Year {{ division.year ?? '—' }} &middot; {{ new Date(division.created_at).toLocaleDateString() }}</p>
              </div>
              <AppBadge :color="statusColor(division.status)">{{ division.status }}</AppBadge>
            </div>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton label="View division" @click="openModal('view', division)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit division" @click="openModal('edit', division)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Upload signature" @click="openUploadModal(division)">
                <ArrowUpOnSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete division" variant="danger" @click="deleteDivision(division)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No divisions found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

      <!-- Division Modal -->
      <AppModal
        :show="showModal"
        :title="modalMode==='create' ? 'New Division' : modalMode==='edit' ? 'Edit Division' : 'View Division'"
        @close="closeModal"
      >
        <!-- VIEW MODE -->
        <div v-if="modalMode==='view' && selectedDivision" class="space-y-2 text-sm text-slate-700">
          <p>Division: <strong>{{ selectedDivision.division_name }}</strong></p>
          <p>Acronym: <strong>{{ selectedDivision.acronym ?? '—' }}</strong></p>
          <p>Chief: <strong>{{ selectedDivision.divisionchief?.name ?? '—' }}</strong></p>
          <p>Year Assigned: <strong>{{ selectedDivision.year ?? '—' }}</strong></p>
          <p class="flex items-center gap-2">
            Status:
            <AppBadge :color="statusColor(selectedDivision.status)">{{ selectedDivision.status }}</AppBadge>
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
        </form>

        <template v-if="modalMode !== 'view'" #footer>
          <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton @click.prevent="submitDivision">Save</AppButton>
        </template>
      </AppModal>
    </div>

    <!-- Upload Signature Modal -->
    <AppModal :show="showUploadModal" title="Upload Electronic Signature" @close="closeUploadModal">
      <div class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Signature Image (PNG/JPG/SVG)</label>
          <input type="file" accept="image/*" class="mt-2 text-sm text-slate-600" @change="onFileChange" />
        </div>

        <div v-if="previewUrl">
          <p class="text-xs text-slate-500 mb-1">Preview:</p>
          <img :src="previewUrl" alt="preview" class="h-24 mt-2 object-contain" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeUploadModal">Cancel</AppButton>
        <AppButton :loading="isUploading" @click="submitUpload">{{ isUploading ? 'Uploading…' : 'Upload' }}</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppTable from "@/Components/AppTable.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppModal from "@/Components/AppModal.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
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

// Badge color mapping for division status
function statusColor(status) {
  return status === 'active' ? 'green' : 'red'
}
</script>
