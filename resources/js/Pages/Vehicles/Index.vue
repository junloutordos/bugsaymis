<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { PencilSquareIcon, TrashIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ vehicles: Array })
const page = usePage()

// reactive list + pagination
const vehiclesList = ref(props.vehicles || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filteredVehicles = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const results = vehiclesList.value.filter(v => (v.name || '').toLowerCase().includes(q) || (v.plate_number || '').toLowerCase().includes(q))
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(vehiclesList.value.filter(v => {
  const q = searchQuery.value.trim().toLowerCase()
  return (v.name || '').toLowerCase().includes(q) || (v.plate_number || '').toLowerCase().includes(q)
}).length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })

const showModal = ref(false)
const editing = ref(null)

// responsive: track window width to swap to card layout on small screens
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)
const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

const form = useForm({ name: '', plate_number: '', description: '', capacity: '', status: 'Good Working' })

const openCreate = () => { editing.value = null; form.reset(); showModal.value = true }
const openEdit = (v) => { editing.value = v; form.name = v.name; form.plate_number = v.plate_number ?? ''; form.description = v.description; form.capacity = v.capacity; form.status = v.status ?? 'Good Working'; showModal.value = true }

const submit = () => {
  if (editing.value) {
    form.put(route('vehicles.update', editing.value.id), {
      onSuccess: () => {
        showModal.value = false
        editing.value = null
        form.reset()
        Swal.fire({ icon: 'success', title: 'Vehicle updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
      },
      onError: (errors) => {
        console.error('Vehicle update errors', errors)
        Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errors).flat().join('\n') })
      }
    })
  } else {
    form.post(route('vehicles.store'), {
      onSuccess: () => {
        showModal.value = false
        form.reset()
        Swal.fire({ icon: 'success', title: 'Vehicle added', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
      },
      onError: (errors) => {
        console.error('Vehicle create errors', errors)
        Swal.fire({ icon: 'error', title: 'Failed to add', text: Object.values(errors).flat().join('\n') })
      }
    })
  }
}

const destroy = (id) => {
  Swal.fire({
    title: 'Delete vehicle?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (!result.isConfirmed) return
    router.delete(route('vehicles.destroy', id), {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Vehicle deleted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
      },
      onError: (errors) => {
        Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errors || {}).flat().join('\n') })
      }
    })
  })
}
</script>

<template>
  <Head title="Vehicles" />
  <AdminLayout title="Vehicles">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Vehicles</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage the fleet of vehicles</p>
        </div>
        <button @click="openCreate" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + Add Vehicle
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search vehicles…"
            class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
        </div>

        <!-- Desktop table -->
        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Plate Number</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Capacity</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="v in filteredVehicles" :key="v.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ v.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ v.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ v.plate_number ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ v.capacity ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span :class="v.status === 'Under Repair' ? 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700' : 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700'">
                    {{ v.status ?? 'Good Working' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1.5">
                    <button @click="openEdit(v)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click="destroy(v.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredVehicles.length===0">
                <td colspan="6" class="py-16 text-center text-slate-400 text-sm">No vehicles added.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile card list -->
        <div v-else class="p-4 space-y-3">
          <div v-for="v in filteredVehicles" :key="v.id" class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <div class="flex justify-between items-start">
              <div>
                <p class="text-xs text-slate-500">ID: {{ v.id }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ v.name }}</p>
                <p class="text-xs text-slate-600 mt-1">Plate: {{ v.plate_number ?? '—' }}</p>
                <p class="text-xs text-slate-600">Capacity: {{ v.capacity ?? '—' }}</p>
                <p class="text-xs text-slate-600">Status: {{ v.status ?? 'Good Working' }}</p>
              </div>
              <div class="flex flex-col items-end gap-2">
                <button @click="openEdit(v)" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Edit</button>
                <button @click="destroy(v.id)" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Delete</button>
              </div>
            </div>
          </div>
          <div v-if="filteredVehicles.length===0" class="py-16 text-center text-slate-400 text-sm">No vehicles added.</div>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-2">
            <button @click="currentPage--" :disabled="currentPage === 1"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Prev</button>
            <button @click="currentPage++" :disabled="currentPage === totalPages"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add / Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ editing ? 'Edit Vehicle' : 'Add Vehicle' }}</h2>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="showModal=false">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
            <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="form.errors.name" class="text-red-600 text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Plate Number</label>
            <input v-model="form.plate_number" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="form.errors.plate_number" class="text-red-600 text-xs mt-1">{{ form.errors.plate_number }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Capacity</label>
            <input v-model.number="form.capacity" type="number" min="1" class="w-32 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="form.errors.capacity" class="text-red-600 text-xs mt-1">{{ form.errors.capacity }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select v-model="form.status" class="w-48 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="Good Working">Good Working</option>
              <option value="Under Repair">Under Repair</option>
            </select>
            <p v-if="form.errors.status" class="text-red-600 text-xs mt-1">{{ form.errors.status }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click.prevent="showModal=false" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
          <button @click.prevent="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">Save</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
