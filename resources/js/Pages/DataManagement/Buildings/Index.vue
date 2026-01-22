<template>
  <Head title="Buildings" />
  <AdminLayout title="Buildings">
    <div class="p-6">
      <div v-if="page.props.flash?.success" class="mb-4">
        <div class="px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700">{{ page.props.flash.success }}</div>
      </div>

      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Buildings</h1>
        <button @click.prevent="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">+ New Building</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
            <!-- Search -->
            <div class="mb-4">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search buildings..."
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
                <th class="px-4 py-3 text-left">No of Rooms</th>
                <th class="px-4 py-3 text-left">No of Floors</th>
                <th class="px-4 py-3 text-left">Occupants</th>
                <th class="px-4 py-3 text-left">Remarks</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="b in filteredBuildings" :key="b.id">
                <td class="px-4 py-3">{{ b.id }}</td>
                <td class="px-4 py-3">{{ b.name }}</td>
                <td class="px-4 py-3">{{ b.code ?? '—' }}</td>
                <td class="px-4 py-3">{{ b.no_of_rooms ?? '—' }}</td>
                <td class="px-4 py-3">{{ b.number_of_floors ?? '—' }}</td>
                <td class="px-4 py-3">{{ b.occupants_count ?? 0 }}</td>
                <td class="px-4 py-3">{{ b.remarks ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-2 justify-center">
                    <button @click.prevent="openModal(b)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit">
                      <PencilSquareIcon class="h-5 w-5" />
                    </button>
                    <button @click.prevent="destroy(b)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
                      <TrashIcon class="h-5 w-5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredBuildings.length === 0">
                <td colspan="8" class="px-4 py-6 text-center text-gray-500">No buildings found.</td>
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
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg sm:max-w-2xl lg:max-w-3xl p-4 sm:p-6 relative max-h-[90vh] overflow-y-auto">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">{{ editingId ? 'Edit Building' : 'New Building' }}</h2>
          <form @submit.prevent="submitForm" class="space-y-3 sm:space-y-0 sm:grid sm:grid-cols-2 sm:gap-4">
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Code</label>
              <input v-model="form.code" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">No of Rooms</label>
              <input v-model="form.no_of_rooms" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Building Use</label>
              <select v-model="form.building_use" multiple class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                <option value="Classrooms">Classrooms</option>
                <option value="Laboratories">Laboratories</option>
                <option value="Admin">Admin</option>
                <option value="Sports/Recreatation">Sports/Recreatation</option>
                <option value="Assembly">Assembly</option>
                <option value="Mixed">Mixed</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Number of Floors</label>
              <input v-model="form.number_of_floors" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Year Constructed</label>
              <input v-model="form.year_constructed" type="number" min="1800" :max="new Date().getFullYear()+1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Year Completed</label>
              <input v-model="form.year_completed" type="number" min="1800" :max="new Date().getFullYear()+1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Amount</label>
              <input v-model="form.amount" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Remarks</label>
              <textarea v-model="form.remarks" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" rows="3"></textarea>
            </div>

            <div class="sm:col-span-2 flex justify-end space-x-3 pt-4">
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

const props = defineProps({ buildings: Array })
const page = usePage()

// reactive list + pagination
const buildingsList = ref(props.buildings || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

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
    import('@inertiajs/vue3').then(({ router }) => {
      router.delete(`/data-management/buildings/${b.id}`, {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Building deleted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
        onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errors || {}).flat().join('\n') }) }
      })
    })
  })
}
</script>
