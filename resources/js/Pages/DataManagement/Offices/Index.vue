<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ offices: Array, divisions: Array });
const form = useForm({ id: null, name: '', division_id: null });
const showModal = ref(false);

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
    form.put(route('offices.update', form.id), { onSuccess: () => closeModal() });
  } else {
    form.post(route('offices.store'), { onSuccess: () => closeModal() });
  }
};

const remove = (office) => {
  if (!confirm('Delete this office/unit?')) return;
  import('@inertiajs/vue3').then(({ router }) => {
    router.delete(route('offices.destroy', office.id));
  });
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
            <tr v-for="o in offices" :key="o.id" class="border-t">
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
            <tr v-if="offices.length === 0">
              <td colspan="4" class="px-4 py-6 text-center text-gray-500">No offices defined.</td>
            </tr>
          </tbody>
        </table>
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
