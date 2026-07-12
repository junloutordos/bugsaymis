<template>
  <Head title="Buildings" />
  <AdminLayout title="Buildings">
    <div class="space-y-5">

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="px-4 py-3 rounded-lg bg-success-50 border border-success-100 text-success-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <AppPageHeader title="Buildings" subtitle="Manage campus buildings">
        <template #actions>
          <AppButton @click.prevent="openModal()">+ New Building</AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <input v-model="searchQuery" type="text" placeholder="Search buildings..."
          @keydown.enter.prevent="applyFilters"
          class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

        <template #actions>
          <AppButton size="sm" @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredBuildings.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Code</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">No of Rooms</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">No of Floors</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Occupants</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="b in filteredBuildings" :key="b.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ b.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ b.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ b.code ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ b.no_of_rooms ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ b.number_of_floors ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ b.occupants_count ?? 0 }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton label="View Remarks" @click.prevent="viewRemarks(b)">
                <EyeIcon class="h-4 w-4" />
              </AppIconButton>
              <AppIconButton label="Rooms" @click.prevent="openRooms(b)">
                <Squares2X2Icon class="h-4 w-4" />
              </AppIconButton>
              <AppIconButton label="Edit" @click.prevent="openModal(b)">
                <PencilSquareIcon class="h-4 w-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" :disabled="isDeleting" @click.prevent="destroy(b)">
                <TrashIcon class="h-4 w-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="b in filteredBuildings" :key="b.id" class="p-4 space-y-2">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-xs text-slate-400">ID: {{ b.id }}</div>
                <div class="text-sm font-semibold text-slate-800">{{ b.name }}</div>
                <div class="text-xs text-slate-500 mt-1">Code: {{ b.code ?? '—' }} · Rooms: {{ b.no_of_rooms ?? '—' }} · Floors: {{ b.number_of_floors ?? '—' }}</div>
                <div class="text-xs text-slate-500">Occupants: {{ b.occupants_count ?? 0 }}</div>
              </div>
              <div class="flex flex-col items-end gap-2">
                <AppButton size="sm" variant="secondary" @click.prevent="viewRemarks(b)">View</AppButton>
                <AppButton size="sm" @click.prevent="openModal(b)">Edit</AppButton>
                <AppButton size="sm" variant="danger" :disabled="isDeleting" @click.prevent="destroy(b)">Delete</AppButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No buildings found" />
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

      <!-- Remarks Modal -->
      <AppModal :show="showRemarksModal" title="Remarks" @close="closeRemarks">
        <div class="whitespace-pre-wrap text-sm text-slate-700">{{ currentRemarks }}</div>
        <template #footer>
          <AppButton @click="closeRemarks">Close</AppButton>
        </template>
      </AppModal>

      <!-- Rooms Modal -->
      <AppModal :show="showRoomsModal" :title="`Rooms — ${currentBuildingName}`" @close="closeRooms">
        <div v-if="currentRooms.length === 0" class="py-8 text-center text-slate-400 text-sm">No rooms found for this building.</div>
        <div v-else class="space-y-2">
          <div v-for="r in currentRooms" :key="r.id" class="border border-slate-100 rounded-lg p-3">
            <div class="flex justify-between items-center">
              <div>
                <div class="text-sm font-medium text-slate-800">{{ r.name }}</div>
                <div class="text-xs text-slate-500 mt-0.5">Code: {{ r.code ?? '—' }} · Floor: {{ r.floor ?? '—' }}</div>
              </div>
              <div class="text-xs text-slate-500">{{ r.office?.name ?? '—' }}</div>
            </div>
            <div class="text-xs text-slate-500 mt-1">Capacity: {{ r.capacity ?? '—' }} · Section: {{ r.section_name ?? '—' }}</div>
          </div>
        </div>
        <template #footer>
          <AppButton @click="closeRooms">Close</AppButton>
        </template>
      </AppModal>

      <!-- Create/Edit Modal -->
      <AppModal :show="showModal" :title="editingId ? 'Edit Building' : 'New Building'" size="3xl" @close="closeModal">
        <form @submit.prevent="submitForm">
          <div class="space-y-3 sm:space-y-0 sm:grid sm:grid-cols-2 sm:gap-4">
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-danger-600">*</span></label>
              <input v-model="form.name" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                required />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Code</label>
              <input v-model="form.code" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">No of Rooms</label>
              <input v-model="form.no_of_rooms" type="number" min="0"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Building Use</label>
              <select v-model="form.building_use" multiple
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="Classrooms">Classrooms</option>
                <option value="Laboratories">Laboratories</option>
                <option value="Admin">Admin</option>
                <option value="Sports/Recreatation">Sports/Recreatation</option>
                <option value="Assembly">Assembly</option>
                <option value="Mixed">Mixed</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Number of Floors</label>
              <input v-model="form.number_of_floors" type="number" min="0"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Year Constructed</label>
              <input v-model="form.year_constructed" type="number" min="1800" :max="new Date().getFullYear()+1"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Year Completed</label>
              <input v-model="form.year_completed" type="number" min="1800" :max="new Date().getFullYear()+1"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Amount</label>
              <input v-model="form.amount" type="number" min="0" step="0.01"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
              <textarea v-model="form.remarks" rows="3"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-4 mt-2 border-t border-slate-100">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit" :disabled="form.processing" :loading="form.processing">
              {{ form.processing ? 'Saving…' : 'Save' }}
            </AppButton>
          </div>
        </form>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, usePage, useForm, router as inertiaRouter } from '@inertiajs/vue3'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { PencilSquareIcon, TrashIcon, EyeIcon, Squares2X2Icon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import { useSubmit } from '@/Composables/useSubmit'

const props = defineProps({ buildings: Array })
const page = usePage()
const { isSubmitting: isDeleting, submit: submitDelete } = useSubmit()

// reactive list + pagination
const buildingsList = ref(props.buildings || [])
const searchQuery = ref('')
const appliedSearchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

// Responsive: track window width to switch to card layout on small screens
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)

const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

const filteredBuildings = computed(() => {
  const q = appliedSearchQuery.value.trim().toLowerCase()
  const results = buildingsList.value.filter(b => (b.name || '').toLowerCase().includes(q) || (b.code || '').toLowerCase().includes(q))
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(buildingsList.value.filter(b => {
  const q = appliedSearchQuery.value.trim().toLowerCase()
  return (b.name || '').toLowerCase().includes(q) || (b.code || '').toLowerCase().includes(q)
}).length / perPage)))

function applyFilters() {
  appliedSearchQuery.value = searchQuery.value
  currentPage.value = 1
}

function clearFilters() {
  searchQuery.value = ''
  appliedSearchQuery.value = ''
  currentPage.value = 1
}

const showModal = ref(false)
const editingId = ref(null)
const showRemarksModal = ref(false)
const currentRemarks = ref('')
const showRoomsModal = ref(false)
const currentRooms = ref([])
const currentBuildingName = ref('')

  const form = useForm({ name: '', code: '', no_of_rooms: '', remarks: '', building_use: [], number_of_floors: '', year_constructed: '', year_completed: '', amount: '' })

const openModal = (b = null) => {
  editingId.value = b ? b.id : null
    if (b) {
    form.reset()
    form.name = b.name
    form.code = b.code
    form.no_of_rooms = b.no_of_rooms
    form.remarks = b.remarks
    try { form.building_use = b.building_use ? JSON.parse(b.building_use) : [] } catch(e){ form.building_use = [] }
    form.number_of_floors = b.number_of_floors ?? ''
    form.year_constructed = b.year_constructed ?? ''
    form.year_completed = b.year_completed ?? ''
    form.amount = b.amount ?? ''
  } else {
    form.reset()
  }
  showModal.value = true
}

const closeModal = () => { showModal.value = false; editingId.value = null; form.reset() }

const submitForm = () => {
  if (editingId.value) {
    form.put(`/data-management/buildings/${editingId.value}`, {
      onSuccess: () => {
        closeModal();
        Swal.fire({ icon: 'success', title: 'Building updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
      },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errors).flat().join('\n') }) }
    })
  } else {
    form.post('/data-management/buildings', {
      onSuccess: () => {
        closeModal();
        Swal.fire({ icon: 'success', title: 'Building added', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
      },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to add', text: Object.values(errors).flat().join('\n') }) }
    })
  }
}

const destroy = (b) => {
  Swal.fire({
    title: 'Delete this building?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  }).then((res) => {
    if (!res.isConfirmed) return
    submitDelete.delete(`/data-management/buildings/${b.id}`, {
      onSuccess: () => { Swal.fire({ icon: 'success', title: 'Building deleted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errors || {}).flat().join('\n') }) }
    })
  })
}

const viewRemarks = (b) => {
  currentRemarks.value = b.remarks ?? '—'
  showRemarksModal.value = true
}

const closeRemarks = () => { showRemarksModal.value = false; currentRemarks.value = '' }

const openRooms = async (b) => {
  currentBuildingName.value = b.name
  try {
    const res = await fetch(`/data-management/rooms?building_id=${b.id}`, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to load rooms')
    const json = await res.json()
    // RoomController returns a collection of rooms; when using Inertia it returns full page HTML,
    // but our rooms route here returns JSON when requested via fetch. If not, fallback to empty list.
    currentRooms.value = Array.isArray(json) ? json : json.data || []
    showRoomsModal.value = true
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed to load rooms', text: e.message })
  }
}

const closeRooms = () => { showRoomsModal.value = false; currentRooms.value = []; currentBuildingName.value = '' }
</script>
