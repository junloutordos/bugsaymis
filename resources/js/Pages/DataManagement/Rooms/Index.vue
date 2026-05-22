<template>
  <Head title="Rooms" />
  <AdminLayout title="Rooms">
    <div>
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Rooms</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage campus rooms and spaces</p>
        </div>
        <button @click.prevent="openModal()"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Room
        </button>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search rooms..."
            class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Code</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Building</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Floor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Section</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Occupant</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Capacity</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
                <!--<th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Gender</th>-->
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Remarks</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in filteredRooms" :key="r.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ r.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.code ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.building?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.floor ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.section_name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.office?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.capacity ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.room_type ?? '—' }}</td>
                <!--<td class="px-4 py-3 text-sm text-slate-700">{{ r.comfort_gender ?? '—' }}</td>-->
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.remarks ?? '—' }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <button @click.prevent="openModal(r)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button @click.prevent="destroy(r)" :disabled="isDeleting"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Delete">
                      <TrashIcon class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRooms.length === 0">
                <td colspan="11" class="py-16 text-center text-slate-400 text-sm">No rooms found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile card list -->
        <div v-else class="p-4 space-y-3">
          <div v-for="r in filteredRooms" :key="r.id" class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-xs text-slate-400">ID: {{ r.id }}</div>
                <div class="text-sm font-semibold text-slate-800">{{ r.name }}</div>
                <div class="text-xs text-slate-500 mt-1">Code: {{ r.code ?? '—' }} · Building: {{ r.building?.name ?? '—' }}</div>
                <div class="text-xs text-slate-500">Floor: {{ r.floor ?? '—' }} · Section: {{ r.section_name ?? '—' }}</div>
                <div class="text-xs text-slate-500">Occupant: {{ r.office?.name ?? '—' }} · Capacity: {{ r.capacity ?? '—' }}</div>
                <div class="text-xs text-slate-500">Type: {{ r.room_type ?? '—' }}</div>
              </div>
              <div class="flex flex-col items-end gap-2">
                <button @click.prevent="openModal(r)"
                  class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors">Edit</button>
                <button @click.prevent="destroy(r)" :disabled="isDeleting"
                  class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Delete</button>
              </div>
            </div>
          </div>
          <div v-if="filteredRooms.length === 0" class="py-16 text-center text-slate-400 text-sm">No rooms found.</div>
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

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editingId ? 'Edit Room' : 'New Room' }}</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <form @submit.prevent="submitForm" class="px-6 py-5 space-y-3">
            <div>
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
              <label class="block text-xs font-medium text-slate-600 mb-1">Building</label>
              <select v-model="form.building_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select building</option>
                <option v-for="b in props.buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <div v-if="selectedBuilding && selectedBuilding.number_of_floors > 0">
              <label class="block text-xs font-medium text-slate-600 mb-1">Floor</label>
              <select v-model="form.floor"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select floor</option>
                <option v-for="f in floorOptions" :key="f.value" :value="f.value">{{ f.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Room Type</label>
              <select v-model="form.room_type"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select room type</option>
                <option value="Classroom">Classroom</option>
                <option value="Admin">Admin</option>
                <option value="Laboratory">Laboratory</option>
                <option value="Sports/Recreation">Sports/Recreation</option>
                <option value="Assembly">Assembly</option>
                <option value="Comfort Room">Comfort Room</option>
              </select>
            </div>

            <div v-if="form.room_type === 'Comfort Room'">
              <label class="block text-xs font-medium text-slate-600 mb-1">Comfort Room For</label>
              <select v-model="form.comfort_gender"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select</option>
                <option value="Female">Female</option>
                <option value="Male">Male</option>
                <option value="All Gender">All Gender</option>
              </select>
            </div>

            <div v-if="form.room_type === 'Classroom'">
              <label class="block text-xs font-medium text-slate-600 mb-1">Section</label>
              <select v-model="form.section_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select section</option>
                <option v-for="s in sectionsList" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Capacity</label>
              <input v-model.number="form.capacity" type="number" min="0"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div v-if="form.room_type === 'Admin'">
              <label class="block text-xs font-medium text-slate-600 mb-1">Occupant (Office)</label>
              <select v-model="form.office_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">--</option>
                <option v-for="o in props.offices" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
              <textarea v-model="form.remarks" rows="3"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
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
import { Head, usePage, useForm } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import { useSubmit } from '@/Composables/useSubmit'

const props = defineProps({ rooms: Array, buildings: Array, offices: Array })
const page = usePage()
const { isSubmitting: isDeleting, submit: submitDelete } = useSubmit()

const roomsList = ref(props.rooms || [])
// responsive: track window width to switch to card layout on small screens
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)
const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filteredRooms = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const results = roomsList.value.filter(r => (r.name || '').toLowerCase().includes(q) || (r.code || '').toLowerCase().includes(q) || (r.building?.name || '').toLowerCase().includes(q))
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(roomsList.value.filter(r => {
  const q = searchQuery.value.trim().toLowerCase()
  return (r.name || '').toLowerCase().includes(q) || (r.code || '').toLowerCase().includes(q) || (r.building?.name || '').toLowerCase().includes(q)
}).length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })

const showModal = ref(false)
const editingId = ref(null)

const form = useForm({ name: '', code: '', building_id: '', floor: '', office_id: '', capacity: '', remarks: '', room_type: '', comfort_gender: '', section_id: '' })

const selectedBuilding = computed(() => {
  if (!form.building_id) return null
  return (props.buildings || []).find(b => String(b.id) === String(form.building_id)) || null
})

const floorOptions = computed(() => {
  const b = selectedBuilding.value
  if (!b || !b.number_of_floors) return []
  const n = Number(b.number_of_floors) || 0
  const out = []
  for (let i = 1; i <= n; i++) {
    out.push({ value: i, label: ordinal(i) + ' Floor' })
  }
  return out
})


function ordinal(n) {
  const s = ['th','st','nd','rd']
  const v = n % 100
  return n + (s[(v-20)%10] || s[v] || s[0])
}

const sectionsList = ref([])

async function loadSections() {
  try {
    const res = await fetch('/sections?syid=12')
    if (!res.ok) return
    sectionsList.value = await res.json()
  } catch (e) {
    console.error('Failed to load sections', e)
  }
}

watch(() => form.room_type, (val) => {
  if (val === 'Classroom') loadSections()
  if (val !== 'Admin') form.office_id = ''
  if (val !== 'Classroom') form.section_id = ''
})

const openModal = (r = null) => {
  editingId.value = r ? r.id : null
  if (r) {
    form.reset()
    form.name = r.name
    form.code = r.code
    form.building_id = r.building_id
    form.floor = r.floor ?? ''
    form.office_id = r.office_id ?? ''
    form.capacity = r.capacity
    form.remarks = r.remarks
    form.room_type = r.room_type ?? ''
    form.comfort_gender = r.comfort_gender ?? ''
    form.section_id = r.section_id ?? ''
    if ((r.room_type || '') === 'Classroom') {
      loadSections()
      console.log('Opening edit modal for Classroom — loading sections')
    }
  } else {
    form.reset()
  }
  showModal.value = true
}

const closeModal = () => { showModal.value = false; editingId.value = null; form.reset() }

const submitForm = () => {
  if (editingId.value) {
    form.put(`/data-management/rooms/${editingId.value}`, {
      onSuccess: () => {
        closeModal()
        Swal.fire({ icon: 'success', title: 'Room updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
      },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errors).flat().join('\n') }) }
    })
  } else {
    form.post('/data-management/rooms', {
      onSuccess: () => {
        closeModal()
        Swal.fire({ icon: 'success', title: 'Room added', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
      },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to add', text: Object.values(errors).flat().join('\n') }) }
    })
  }
}

const destroy = (r) => {
  Swal.fire({
    title: 'Delete this room?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  }).then((res) => {
    if (!res.isConfirmed) return
    submitDelete.delete(route('rooms.destroy', r.id), {
      onSuccess: () => { window.location.reload() },
      onError: () => { alert('Failed to delete') }
    })
  })
}
</script>
