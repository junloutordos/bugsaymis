<script setup>
import { Head, usePage, useForm, router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { ref, computed } from "vue";
import { PlusIcon, PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/outline";
import Swal from 'sweetalert2'
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

const props = defineProps({ facilities: Array, buildings: Array });
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role?.name === 'Administrator')

const facilitiesList = ref(props.facilities || [])
const searchQuery = ref('')
const appliedSearchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filteredFacilities = computed(() => {
  const q = appliedSearchQuery.value.trim().toLowerCase()
  const results = facilitiesList.value.filter(f => (f.name || '').toLowerCase().includes(q) || (f.location || '').toLowerCase().includes(q))
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(facilitiesList.value.filter(f => {
  const q = appliedSearchQuery.value.trim().toLowerCase()
  return (f.name || '').toLowerCase().includes(q) || (f.location || '').toLowerCase().includes(q)
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
  const map = {
    'Available':          'green',
    'Reserved':           'blue',
    'Maintenance':        'amber',
    'Under Maintenance':  'amber',
  }
  return map[status] ?? 'slate'
}

const form = useForm({ name: '', location: '', capacity: '', description: '' });
const showForm = ref(false);
const editing = ref(null);

const openCreate = () => {
  editing.value = null;
  form.reset();
  showForm.value = true;
};

const openEdit = (f) => {
  editing.value = f;
  form.reset();
  form.name = f.name || '';
  form.location = f.location || '';
  form.capacity = f.capacity || null;
  form.description = f.description || '';
  showForm.value = true;
};

const submit = () => {
  if (editing.value) {
    form.put(route('facilities.update', editing.value.id), {
      onSuccess: () => {
        form.reset(); showForm.value = false; editing.value = null;
        Swal.fire({ icon: 'success', title: 'Facility updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
      },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errors).flat().join('\n') }) }
    });
  } else {
    form.post(route('facilities.store'), {
      onSuccess: () => {
        form.reset(); showForm.value = false;
        Swal.fire({ icon: 'success', title: 'Facility added', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
      },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to add', text: Object.values(errors).flat().join('\n') }) }
    });
  }
};

const destroy = async (f) => {
  if (!(await confirmDelete('This action cannot be undone.'))) return
  router.delete(route('facilities.destroy', f.id), {
    onSuccess: () => { Swal.fire({ icon: 'success', title: 'Facility deleted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
    onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errors || {}).flat().join('\n') }) }
  })
};
</script>

<template>
  <Head title="Facilities" />
  <AdminLayout title="Facilities">
    <div class="space-y-5">

      <AppPageHeader title="Facilities" subtitle="Manage venues and facilities">
        <template #actions>
          <AppButton v-if="isAdmin" @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Facility
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-full sm:w-72">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search facilities…"
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
      <AppTable :is-empty="!filteredFacilities.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Location</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Capacity</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="f in filteredFacilities" :key="f.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ f.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ f.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ f.location ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ f.capacity ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="statusColor(f.status)">{{ f.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center gap-1.5 justify-center">
              <AppIconButton v-if="isAdmin" label="Edit facility" @click="openEdit(f)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="isAdmin" label="Delete facility" variant="danger" @click="destroy(f)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="f in filteredFacilities" :key="f.id" class="p-4 space-y-2">
            <div class="flex justify-between items-start gap-2">
              <div>
                <p class="text-xs text-slate-500">ID: {{ f.id }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ f.name }}</p>
                <p class="text-xs text-slate-600 mt-1">Location: {{ f.location ?? '—' }}</p>
                <p class="text-xs text-slate-600">Capacity: {{ f.capacity ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(f.status)">{{ f.status ?? '—' }}</AppBadge>
            </div>
            <div v-if="isAdmin" class="flex gap-2 justify-end pt-1">
              <AppButton size="sm" variant="ghost" @click="openEdit(f)">Edit</AppButton>
              <AppButton size="sm" variant="danger" @click="destroy(f)">Delete</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No facilities found" />
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
    <AppModal :show="showForm" :title="editing ? 'Edit Facility' : 'New Facility'" @close="showForm = false">
      <div class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
          <input v-model="form.name" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <p v-if="form.errors.name" class="text-danger-600 text-xs mt-1">{{ form.errors.name }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Location</label>
          <select v-model="form.location" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option value="">Select building</option>
            <option v-for="b in props.buildings" :key="b.id" :value="b.name">{{ b.name }}</option>
          </select>
          <p v-if="form.errors.location" class="text-danger-600 text-xs mt-1">{{ form.errors.location }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Capacity</label>
          <input type="number" v-model.number="form.capacity" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <p v-if="form.errors.capacity" class="text-danger-600 text-xs mt-1">{{ form.errors.capacity }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
          <input v-model="form.description" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <p v-if="form.errors.description" class="text-danger-600 text-xs mt-1">{{ form.errors.description }}</p>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showForm = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="submit">Save</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
