<template>
  <Head title="Buildings" />
  <AdminLayout title="Buildings">
    <div>
      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="mb-4">
        <div class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">{{ page.props.flash.success }}</div>
      </div>

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Buildings</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage campus buildings</p>
        </div>
        <button @click.prevent="openModal()"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Building
        </button>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search buildings..."
            class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <div class="overflow-x-auto" v-if="!isMobile">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Code</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">No of Rooms</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">No of Floors</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Occupants</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="b in filteredBuildings" :key="b.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ b.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ b.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ b.code ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ b.no_of_rooms ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ b.number_of_floors ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ b.occupants_count ?? 0 }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <button @click.prevent="viewRemarks(b)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View Remarks">
                      <EyeIcon class="h-4 w-4" />
                    </button>
                    <button @click.prevent="openRooms(b)"
                      class="p-1.5 rounded-lg hover:bg-indigo-50 text-indigo-500 hover:text-indigo-700 transition-colors" title="Rooms">
                      <Squares2X2Icon class="h-4 w-4" />
                    </button>
                    <button @click.prevent="openModal(b)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button @click.prevent="destroy(b)" :disabled="isDeleting"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Delete">
                      <TrashIcon class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredBuildings.length === 0">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No buildings found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile card list -->
        <div v-else class="p-4 space-y-3">
          <div v-for="b in filteredBuildings" :key="b.id" class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-xs text-slate-400">ID: {{ b.id }}</div>
                <div class="text-sm font-semibold text-slate-800">{{ b.name }}</div>
                <div class="text-xs text-slate-500 mt-1">Code: {{ b.code ?? '—' }} · Rooms: {{ b.no_of_rooms ?? '—' }} · Floors: {{ b.number_of_floors ?? '—' }}</div>
                <div class="text-xs text-slate-500">Occupants: {{ b.occupants_count ?? 0 }}</div>
              </div>
              <div class="flex flex-col items-end gap-2">
                <button @click.prevent="viewRemarks(b)"
                  class="inline-flex items-center gap-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1 rounded-lg text-xs font-medium transition-colors">View</button>
                <button @click.prevent="openModal(b)"
                  class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors">Edit</button>
                <button @click.prevent="destroy(b)" :disabled="isDeleting"
                  class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Delete</button>
              </div>
            </div>
          </div>
          <div v-if="filteredBuildings.length === 0" class="py-16 text-center text-slate-400 text-sm">No buildings found.</div>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-2">
            <button @click="currentPage--" :disabled="currentPage === 1"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              Prev
            </button>
            <button @click="currentPage++" :disabled="currentPage === totalPages"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              Next
            </button>
          </div>
        </div>
      </div>

      <!-- Remarks Modal -->
      <div v-if="showRemarksModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">Remarks</h3>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeRemarks">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5 whitespace-pre-wrap text-sm text-slate-700">{{ currentRemarks }}</div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
            <button @click="closeRemarks"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Close
            </button>
          </div>
        </div>
      </div>

      <!-- Rooms Modal -->
      <div v-if="showRoomsModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[80vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">Rooms — {{ currentBuildingName }}</h3>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeRooms">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5">
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
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
            <button @click="closeRooms"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Close
            </button>
          </div>
        </div>
      </div>

      <!-- Create/Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg sm:max-w-2xl lg:max-w-3xl max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editingId ? 'Edit Building' : 'New Building' }}</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <form @submit.prevent="submitForm" class="px-6 py-5">
            <div class="space-y-3 sm:space-y-0 sm:grid sm:grid-cols-2 sm:gap-4">
              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-red-500">*</span></label>
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
              <button type="button" @click="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Cancel
              </button>
              <button type="submit" :disabled="form.processing"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                {{ form.processing ? 'Saving…' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, usePage, useForm, router as inertiaRouter } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PencilSquareIcon, TrashIcon, EyeIcon, Squares2X2Icon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import { useSubmit } from '@/Composables/useSubmit'

const props = defineProps({ buildings: Array })
const page = usePage()
const { isSubmitting: isDeleting, submit: submitDelete } = useSubmit()

// reactive list + pagination
const buildingsList = ref(props.buildings || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

// Responsive: track window width to switch to card layout on small screens
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)

const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

const filteredBuildings = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const results = buildingsList.value.filter(b => (b.name || '').toLowerCase().includes(q) || (b.code || '').toLowerCase().includes(q))
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(buildingsList.value.filter(b => {
  const q = searchQuery.value.trim().toLowerCase()
  return (b.name || '').toLowerCase().includes(q) || (b.code || '').toLowerCase().includes(q)
}).length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })

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
