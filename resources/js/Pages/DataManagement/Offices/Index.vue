<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ offices: Array, divisions: Array, users: Array });
const officesList = ref(props.offices || []);
const usersList = ref(props.users || []);
const form = useForm({ id: null, name: '', division_id: null, unit_head: null });
const showModal = ref(false);

// Search & pagination (client-side, mirror Users template)
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

// Responsive: track window width to switch to card layout on small screens
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)

const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

const filteredOffices = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  let results = officesList.value.filter(o =>
    o.name?.toLowerCase().includes(q) ||
    (o.division?.division_name || '').toLowerCase().includes(q)
  )
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil((officesList.value.filter(o => {
  const q = searchQuery.value.trim().toLowerCase()
  return o.name?.toLowerCase().includes(q) || (o.division?.division_name || '').toLowerCase().includes(q)
}).length) / perPage)))

// Reset page when search changes
watch(searchQuery, () => { currentPage.value = 1 })

const openModal = (office = null) => {
  if (office) {
    form.reset();
    form.id = office.id;
    form.name = office.name;
    form.division_id = office.division_id ?? null;
    form.unit_head = office.unit_head ?? null;
  } else {
    form.reset();
  }
  showModal.value = true;
};

const closeModal = () => { showModal.value = false; form.reset(); };

const submit = () => {
  if (form.id) {
    form.put(route('offices.update', form.id), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Office updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) }
    });
  } else {
    form.post(route('offices.store'), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Office added', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) }
    });
  }
};

const remove = (office) => {
  Swal.fire({
    title: 'Delete this office/unit?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  }).then((res) => {
    if (!res.isConfirmed) return
    import('@inertiajs/vue3').then(({ router }) => {
      router.delete(route('offices.destroy', office.id), {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Office deleted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
        onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errors || {}).flat().join('\n') }) }
      })
    })
  })
};
</script>

<template>
  <Head title="Offices" />
  <AdminLayout title="Data Management">
    <div>
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Offices / Units</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage offices and organizational units</p>
        </div>
        <button @click.prevent="openModal()"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Office
        </button>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search offices..."
            class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <div class="overflow-x-auto" v-if="!isMobile">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Division</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Unit Head</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="o in filteredOffices" :key="o.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ o.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ o.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ o.division?.division_name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ o.unitHeadUser?.name ?? o.unit_head_user?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <button @click.prevent="openModal(o)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click.prevent="remove(o)"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredOffices.length === 0">
                <td colspan="5" class="py-16 text-center text-slate-400 text-sm">No offices found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile card list -->
        <div v-else class="p-4 space-y-3">
          <div v-for="o in filteredOffices" :key="o.id" class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-xs text-slate-400">ID: {{ o.id }}</div>
                <div class="text-sm font-semibold text-slate-800">{{ o.name }}</div>
                <div class="text-xs text-slate-500 mt-1">Division: {{ o.division?.division_name ?? '—' }}</div>
                <div class="text-xs text-slate-500">Unit Head: {{ o.unitHeadUser?.name ?? o.unit_head_user?.name ?? '—' }}</div>
              </div>
              <div class="flex flex-col items-end gap-2">
                <button @click.prevent="openModal(o)"
                  class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors">Edit</button>
                <button @click.prevent="remove(o)"
                  class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors">Delete</button>
              </div>
            </div>
          </div>
          <div v-if="filteredOffices.length === 0" class="py-16 text-center text-slate-400 text-sm">No offices found.</div>
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
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ form.id ? 'Edit Office' : 'New Office' }}</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
              <input v-model="form.name" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Division</label>
              <select v-model="form.division_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="" disabled>Select division</option>
                <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.division_name }}</option>
              </select>
              <p v-if="form.errors.division_id" class="text-red-500 text-xs mt-1">{{ form.errors.division_id }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Unit Head</label>
              <select v-model="form.unit_head"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">-- No unit head --</option>
                <option v-for="u in usersList" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="form.errors.unit_head" class="text-red-500 text-xs mt-1">{{ form.errors.unit_head }}</p>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
              <button type="button" @click.prevent="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Cancel
              </button>
              <button :disabled="form.processing" type="submit"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                {{ form.processing ? 'Saving...' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
