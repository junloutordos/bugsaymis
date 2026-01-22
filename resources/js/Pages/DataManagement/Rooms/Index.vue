<template>
  <Head title="Rooms" />
  <AdminLayout title="Rooms">
    <div class="p-6">

      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Rooms</h1>
        <button @click.prevent="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">+ New Room</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Search -->
        <div class="mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search rooms..."
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div class="hidden sm:block overflow-x-auto">
          <table class="table-fixed w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Code</th>
                <th class="px-4 py-3 text-left">Building</th>
                <th class="px-4 py-3 text-left">Floor</th>
                <th class="px-4 py-3 text-left">Section</th>
                <th class="px-4 py-3 text-left">Occupant</th>
                <th class="px-4 py-3 text-left">Capacity</th>
                  <th class="px-4 py-3 text-left">Type</th>
                  <!--<th class="px-4 py-3 text-left">Gender</th>-->
                  <th class="px-4 py-3 text-left">Remarks</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="r in filteredRooms" :key="r.id">
                <td class="px-4 py-3">{{ r.id }}</td>
                <td class="px-4 py-3">{{ r.name }}</td>
                <td class="px-4 py-3">{{ r.code ?? '—' }}</td>
                <td class="px-4 py-3">{{ r.building?.name ?? '—' }}</td>
                <td class="px-4 py-3">{{ r.floor ?? '—' }}</td>
                <td class="px-4 py-3">{{ r.section_name ?? '—' }}</td>
                <td class="px-4 py-3">{{ r.office?.name ?? '—' }}</td>
                <td class="px-4 py-3">{{ r.capacity ?? '—' }}</td>
                <td class="px-4 py-3">{{ r.room_type ?? '—' }}</td>
                <!--<td class="px-4 py-3">{{ r.comfort_gender ?? '—' }}</td>-->
                <td class="px-4 py-3">{{ r.remarks ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-2 justify-center">
                    <button @click.prevent="openModal(r)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit">
                      <PencilSquareIcon class="h-5 w-5" />
                    </button>
                    <button @click.prevent="destroy(r)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
                      <TrashIcon class="h-5 w-5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRooms.length === 0">
                <td colspan="11" class="px-4 py-6 text-center text-gray-500">No rooms found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      <!-- Pagination -->
      <div class="flex justify-center items-center gap-2 mt-4">
        <button
          @click="currentPage--"
          :disabled="currentPage === 1"
          class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
        >
          Prev
        </button>
        <span>Page {{ currentPage }} of {{ totalPages }}</span>
        <button
          @click="currentPage++"
          :disabled="currentPage === totalPages"
          class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
        >
          Next
        </button>
      </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">{{ editingId ? 'Edit Room' : 'New Room' }}</h2>
          <form @submit.prevent="submitForm" class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Code</label>
              <input v-model="form.code" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Building</label>
              <select v-model="form.building_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                <option value="">Select building</option>
                <option v-for="b in props.buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <div v-if="selectedBuilding && selectedBuilding.number_of_floors > 0">
              <label class="block text-sm font-medium text-gray-700">Floor</label>
              <select v-model="form.floor" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                <option value="">Select floor</option>
                <option v-for="f in floorOptions" :key="f.value" :value="f.value">{{ f.label }}</option>
              </select>
            </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Room Type</label>
                <select v-model="form.room_type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
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
                <label class="block text-sm font-medium text-gray-700">Comfort Room For</label>
                <select v-model="form.comfort_gender" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                  <option value="">Select</option>
                  <option value="Female">Female</option>
                  <option value="Male">Male</option>
                  <option value="All Gender">All Gender</option>
                </select>
              </div>

              <div v-if="form.room_type === 'Classroom'">
                <label class="block text-sm font-medium text-gray-700">Section</label>
                <select v-model="form.section_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                  <option value="">Select section</option>
                  <option v-for="s in sectionsList" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Capacity</label>
                <input v-model.number="form.capacity" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
              </div>
              <div v-if="form.room_type === 'Admin'">
                <label class="block text-sm font-medium text-gray-700">Occupant (Office)</label>
                <select v-model="form.office_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                  <option value="">--</option>
                  <option v-for="o in props.offices" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
              </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Remarks</label>
              <textarea v-model="form.remarks" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" rows="3"></textarea>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, usePage, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const props = defineProps({ rooms: Array, buildings: Array, offices: Array })
const page = usePage()

const roomsList = ref(props.rooms || [])
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
    import('@inertiajs/vue3').then(({ router }) => {
      router.delete(route('rooms.destroy', r.id), {
        onSuccess: () => { window.location.reload() },
        onError: () => { alert('Failed to delete') }
      })
    })
  })
}
</script>
