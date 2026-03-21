<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";
import { PencilSquareIcon, TrashIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import Swal from 'sweetalert2'

const props = defineProps({ facilities: Array, buildings: Array });
const page = usePage();

const facilitiesList = ref(props.facilities || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

// responsive: track window width to switch to card layout on small screens
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)
const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

const filteredFacilities = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const results = facilitiesList.value.filter(f => (f.name || '').toLowerCase().includes(q) || (f.location || '').toLowerCase().includes(q))
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(facilitiesList.value.filter(f => {
  const q = searchQuery.value.trim().toLowerCase()
  return (f.name || '').toLowerCase().includes(q) || (f.location || '').toLowerCase().includes(q)
}).length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })

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

const destroy = (f) => {
  Swal.fire({
    title: 'Delete this facility?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  }).then((res) => {
    if (!res.isConfirmed) return
    import('@inertiajs/vue3').then(({ router }) => {
      router.delete(route('facilities.destroy', f.id), {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Facility deleted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
        onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errors || {}).flat().join('\n') }) }
      })
    })
  })
};
</script>

<template>
  <Head title="Facilities" />
  <AdminLayout title="Facilities">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Facilities</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage venues and facilities</p>
        </div>
        <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="openCreate"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Facility
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search facilities…"
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
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Location</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Capacity</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="f in filteredFacilities" :key="f.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ f.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ f.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ f.location ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ f.capacity ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">{{ f.status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-1.5 justify-center">
                    <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="openEdit(f)"
                            class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="destroy(f)"
                            class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredFacilities.length === 0">
                <td colspan="6" class="py-16 text-center text-slate-400 text-sm">No facilities found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile card list -->
        <div v-else class="p-4 space-y-3">
          <div v-for="f in filteredFacilities" :key="f.id" class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <div class="flex justify-between items-start">
              <div>
                <p class="text-xs text-slate-500">ID: {{ f.id }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ f.name }}</p>
                <p class="text-xs text-slate-600 mt-1">Location: {{ f.location ?? '—' }}</p>
                <p class="text-xs text-slate-600">Capacity: {{ f.capacity ?? '—' }}</p>
                <p class="text-xs text-slate-600">Status: {{ f.status ?? '—' }}</p>
              </div>
              <div class="flex flex-col items-end gap-2">
                <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="openEdit(f)"
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Edit</button>
                <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="destroy(f)"
                        class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Delete</button>
              </div>
            </div>
          </div>
          <div v-if="filteredFacilities.length === 0" class="py-16 text-center text-slate-400 text-sm">No facilities found.</div>
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
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ editing ? 'Edit Facility' : 'New Facility' }}</h2>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="showForm = false">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
            <input v-model="form.name" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="form.errors.name" class="text-red-600 text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Location</label>
            <select v-model="form.location" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="">Select building</option>
              <option v-for="b in props.buildings" :key="b.id" :value="b.name">{{ b.name }}</option>
            </select>
            <p v-if="form.errors.location" class="text-red-600 text-xs mt-1">{{ form.errors.location }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Capacity</label>
            <input type="number" v-model.number="form.capacity" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="form.errors.capacity" class="text-red-600 text-xs mt-1">{{ form.errors.capacity }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <input v-model="form.description" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="form.errors.description" class="text-red-600 text-xs mt-1">{{ form.errors.description }}</p>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click.prevent="showForm = false" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
          <button @click.prevent="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">Save</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
