<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const page = usePage();

const activities = page.props.activities ?? [];

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
  name: '',
  date: '',
  venue: '',
  participants: '',
  materials_no_cost: '',
  materials_with_cost: '',
  working_committee: '',
});

const openModal = (act = null) => {
  editing.value = act;
  if (act) {
    form.reset();
    form.name = act.name ?? '';
    form.date = act.date ?? '';
    form.venue = act.venue ?? '';
    form.participants = act.participants ?? '';
    form.materials_no_cost = act.materials_no_cost ?? '';
    form.materials_with_cost = act.materials_with_cost ?? '';
    form.working_committee = act.working_committee ?? '';
  } else {
    form.reset();
  }
  showModal.value = true;
}

const closeModal = () => { showModal.value = false; editing.value = null; form.reset(); }

const submit = () => {
  if (editing.value) {
    form.put(route('activities.update', editing.value.id), {
      onSuccess: () => { closeModal(); location.reload(); }
    });
  } else {
    form.post(route('activities.store'), {
      onSuccess: () => { closeModal(); location.reload(); }
    });
  }
}

const remove = (act) => {
  if (!confirm('Delete activity?')) return;
  window.axios.delete(route('activities.destroy', act.id)).then(() => location.reload()).catch(e => alert('Delete failed'));
}
</script>

<template>
  <Head title="Activity Planner" />
  <AdminLayout title="Activity Planner">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Activity Planner</h1>
        <button @click.prevent="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded">+ New Activity</button>
      </div>

      <div class="bg-white rounded-lg shadow p-4">
        <table class="w-full table-auto">
          <thead>
            <tr class="text-left text-sm text-gray-600">
              <th class="py-2">Name</th>
              <th class="py-2">Date</th>
              <th class="py-2">Venue</th>
              <th class="py-2">Working Committee</th>
              <th class="py-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in activities" :key="a.id" class="border-t">
              <td class="py-2">{{ a.name }}</td>
              <td class="py-2">{{ new Date(a.date).toLocaleDateString() }}</td>
              <td class="py-2">{{ a.venue ?? '—' }}</td>
              <td class="py-2">{{ a.working_committee ?? '—' }}</td>
              <td class="py-2">
                <button @click.prevent="openModal(a)" class="text-sm text-blue-600 mr-2">Edit</button>
                <button @click.prevent="remove(a)" class="text-sm text-red-600">Delete</button>
              </td>
            </tr>
            <tr v-if="activities.length === 0"><td colspan="5" class="py-6 text-center text-gray-500">No activities yet.</td></tr>
          </tbody>
        </table>
      </div>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white w-full max-w-2xl p-6 rounded shadow-lg">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">{{ editing ? 'Edit Activity' : 'New Activity' }}</h2>
            <button @click="closeModal" class="text-gray-500">✕</button>
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium">Activity Name</label>
              <input v-model="form.name" type="text" class="mt-1 block w-full rounded border-gray-300" />
            </div>

            <div>
              <label class="block text-sm font-medium">Date</label>
              <input v-model="form.date" type="date" class="mt-1 block rounded border-gray-300" />
            </div>

            <div>
              <label class="block text-sm font-medium">Venue</label>
              <input v-model="form.venue" type="text" class="mt-1 block w-full rounded border-gray-300" />
            </div>

            <div>
              <label class="block text-sm font-medium">Participants</label>
              <textarea v-model="form.participants" class="mt-1 block w-full rounded border-gray-300" rows="3"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium">Materials & Equipment Needed (No Cost)</label>
              <textarea v-model="form.materials_no_cost" class="mt-1 block w-full rounded border-gray-300" rows="3"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium">Materials & Equipment (with cost)</label>
              <textarea v-model="form.materials_with_cost" class="mt-1 block w-full rounded border-gray-300" rows="3"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium">Working Committee</label>
              <textarea v-model="form.working_committee" class="mt-1 block w-full rounded border-gray-300" rows="2"></textarea>
            </div>

            <div class="flex gap-2 mt-4">
              <button @click.prevent="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
              <button @click.prevent="closeModal" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
