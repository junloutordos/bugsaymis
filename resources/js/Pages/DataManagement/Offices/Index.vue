<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ offices: Array, divisions: Array });
const officesList = ref(props.offices || []);
const form = useForm({ id: null, name: '', division_id: null });
const showModal = ref(false);

// Search & pagination (client-side, mirror Users template)
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

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
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Offices / Units</h1>
        <button @click.prevent="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ New Office</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Search -->
        <div class="mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search offices..."
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <table class="w-full table-auto">
          <thead class="bg-gray-100 text-left">
            <tr>
              <th class="px-4 py-2">#</th>
              <th class="px-4 py-2">Name</th>
              <th class="px-4 py-2">Division</th>
              <th class="px-4 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in filteredOffices" :key="o.id" class="border-t">
              <td class="px-4 py-3">{{ o.id }}</td>
              <td class="px-4 py-3">{{ o.name }}</td>
              <td class="px-4 py-3">{{ o.division?.division_name ?? '—' }}</td>
              <td class="px-4 py-3">
                <button @click.prevent="openModal(o)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700 mr-2" title="Edit">
                  <PencilSquareIcon class="w-5 h-5" />
                </button>
                <button @click.prevent="remove(o)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
                  <TrashIcon class="w-5 h-5" />
                </button>
              </td>
            </tr>
            <tr v-if="filteredOffices.length === 0">
              <td colspan="4" class="px-4 py-6 text-center text-gray-500">No offices found.</td>
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

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">{{ form.id ? 'Edit Office' : 'New Office' }}</h2>
          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Name</label>
              <input v-model="form.name" type="text" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Division</label>
              <select v-model="form.division_id" class="mt-1 block w-full rounded border-gray-300">
                <option value="" disabled>Select division</option>
                <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.division_name }}</option>
              </select>
              <p v-if="form.errors.division_id" class="text-red-600 text-sm mt-1">{{ form.errors.division_id }}</p>
            </div>
            <div class="flex gap-2">
              <button :disabled="form.processing" type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">{{ form.processing ? 'Saving...' : 'Save' }}</button>
              <button @click.prevent="closeModal" type="button" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
