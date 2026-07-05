<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { PlusIcon, PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/outline";
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'

const props = defineProps({ vehicles: Array })
const page = usePage()

// reactive list + pagination
const vehiclesList = ref(props.vehicles || [])
const searchQuery = ref('')
const appliedSearchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filteredVehicles = computed(() => {
  const q = appliedSearchQuery.value.trim().toLowerCase()
  const results = vehiclesList.value.filter(v => (v.name || '').toLowerCase().includes(q) || (v.plate_number || '').toLowerCase().includes(q))
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(vehiclesList.value.filter(v => {
  const q = appliedSearchQuery.value.trim().toLowerCase()
  return (v.name || '').toLowerCase().includes(q) || (v.plate_number || '').toLowerCase().includes(q)
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

function statusColor(status) {
  return status === 'Under Repair' ? 'amber' : 'green'
}

const showModal = ref(false)
const editing = ref(null)

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

const destroy = async (id) => {
  if (!(await confirmDelete('This action cannot be undone.'))) return
  router.delete(route('vehicles.destroy', id), {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Vehicle deleted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
    },
    onError: (errors) => {
      Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errors || {}).flat().join('\n') })
    }
  })
}
</script>

<template>
  <Head title="Vehicles" />
  <AdminLayout title="Vehicles">
    <div class="space-y-5">

      <AppPageHeader title="Vehicles" subtitle="Manage the fleet of vehicles">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            Add Vehicle
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-full sm:w-72">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search vehicles…"
            @keydown.enter.prevent="applyFilters"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
        </div>
        <template #actions>
          <AppButton size="sm" @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredVehicles.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Plate Number</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Capacity</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="v in filteredVehicles" :key="v.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ v.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ v.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ v.plate_number ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ v.capacity ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="statusColor(v.status)">{{ v.status ?? 'Good Working' }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1.5">
              <AppIconButton label="Edit vehicle" @click="openEdit(v)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete vehicle" variant="danger" @click="destroy(v.id)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="v in filteredVehicles" :key="v.id" class="p-4 space-y-2">
            <div class="flex justify-between items-start gap-2">
              <div>
                <p class="text-xs text-slate-500">ID: {{ v.id }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ v.name }}</p>
                <p class="text-xs text-slate-600 mt-1">Plate: {{ v.plate_number ?? '—' }}</p>
                <p class="text-xs text-slate-600">Capacity: {{ v.capacity ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(v.status)">{{ v.status ?? 'Good Working' }}</AppBadge>
            </div>
            <div class="flex gap-2 justify-end pt-1">
              <AppButton size="sm" variant="ghost" @click="openEdit(v)">Edit</AppButton>
              <AppButton size="sm" variant="danger" @click="destroy(v.id)">Delete</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No vehicles added" />
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

    </div>

    <!-- Add / Edit Modal -->
    <AppModal :show="showModal" :title="editing ? 'Edit Vehicle' : 'Add Vehicle'" @close="showModal = false">
      <div class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
          <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <p v-if="form.errors.name" class="text-danger-600 text-xs mt-1">{{ form.errors.name }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Plate Number</label>
          <input v-model="form.plate_number" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <p v-if="form.errors.plate_number" class="text-danger-600 text-xs mt-1">{{ form.errors.plate_number }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Capacity</label>
          <input v-model.number="form.capacity" type="number" min="1" class="w-32 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <p v-if="form.errors.capacity" class="text-danger-600 text-xs mt-1">{{ form.errors.capacity }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="form.status" class="w-48 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option value="Good Working">Good Working</option>
            <option value="Under Repair">Under Repair</option>
          </select>
          <p v-if="form.errors.status" class="text-danger-600 text-xs mt-1">{{ form.errors.status }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
          <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="submit">Save</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
