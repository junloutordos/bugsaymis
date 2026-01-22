<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { ref, computed, watch } from "vue";
import { PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/outline";
import Swal from 'sweetalert2'

const props = defineProps({ facilities: Array, buildings: Array });
const page = usePage();

const facilitiesList = ref(props.facilities || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

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
    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Facilities</h1>
        <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="showForm = !showForm" class="bg-blue-600 text-white px-3 py-1 rounded">New Facility</button>
      </div>

      <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="showForm = false">✕</button>
          <h2 class="text-xl font-semibold mb-4">New Facility</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Name</label>
              <input v-model="form.name" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Location</label>
              <select v-model="form.location" class="mt-1 block w-full rounded border-gray-300">
                <option value="">Select building</option>
                <option v-for="b in props.buildings" :key="b.id" :value="b.name">{{ b.name }}</option>
              </select>
              <p v-if="form.errors.location" class="text-red-600 text-sm mt-1">{{ form.errors.location }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Capacity</label>
              <input type="number" v-model.number="form.capacity" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.capacity" class="text-red-600 text-sm mt-1">{{ form.errors.capacity }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <input v-model="form.description" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.description" class="text-red-600 text-sm mt-1">{{ form.errors.description }}</p>
            </div>

            <div class="flex gap-2">
              <button @click.prevent="submit" :disabled="form.processing" class="bg-blue-600 text-white px-4 py-2 rounded disabled:opacity-60">Save</button>
              <button @click.prevent="showForm = false" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Search -->
        <div class="mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search facilities..."
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <table class="min-w-full border border-gray-200">
          <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
            <tr>
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Name</th>
              <th class="px-4 py-3 text-left">Location</th>
              <th class="px-4 py-3 text-left">Capacity</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="f in filteredFacilities" :key="f.id">
              <td class="px-4 py-3">{{ f.id }}</td>
              <td class="px-4 py-3">{{ f.name }}</td>
              <td class="px-4 py-3">{{ f.location ?? '—' }}</td>
              <td class="px-4 py-3">{{ f.capacity ?? '—' }}</td>
              <td class="px-4 py-3">{{ f.status }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center gap-2 justify-center">
                  <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="openEdit(f)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit">
                    <PencilSquareIcon class="w-5 h-5" />
                  </button>
                  <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="destroy(f)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredFacilities.length === 0">
              <td colspan="6" class="px-4 py-6 text-center text-gray-500">No facilities found.</td>
            </tr>
          </tbody>
        </table>
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
    </div>
  </AdminLayout>
</template>
