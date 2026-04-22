<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const page = usePage();

const activities = page.props.activities ?? [];

const showModal = ref(false);
const editing = ref(null);
const isDeleting = ref(false);

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

const remove = async (act) => {
  if (!confirm('Delete activity?')) return;
  if (isDeleting.value) return;
  isDeleting.value = true;
  try {
    await window.axios.delete(route('activities.destroy', act.id));
    location.reload();
  } catch (e) {
    alert('Delete failed');
  } finally {
    isDeleting.value = false;
  }
}
</script>

<template>
  <Head title="Activity Planner" />
  <AdminLayout title="Activity Planner">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Activity Planner</h1>
          <p class="text-sm text-slate-500">Manage and track planned activities</p>
        </div>
        <button @click.prevent="openModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Activity
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Venue</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Working Committee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="a in activities" :key="a.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(a.date).toLocaleDateString() }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.venue ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.working_committee ?? '—' }}</td>
                <td class="px-4 py-3">
                  <div class="flex gap-1">
                    <button @click.prevent="openModal(a)" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm">Edit</button>
                    <button @click.prevent="remove(a)" :disabled="isDeleting" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">Delete</button>
                  </div>
                </td>
              </tr>
              <tr v-if="activities.length === 0">
                <td colspan="5" class="py-16 text-center text-slate-400 text-sm">No activities yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editing ? 'Edit Activity' : 'New Activity' }}</h2>
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Activity Name</label>
              <input v-model="form.name" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
              <input v-model="form.date" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Venue</label>
              <input v-model="form.venue" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Participants</label>
              <textarea v-model="form.participants" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" rows="3"></textarea>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Materials & Equipment Needed (No Cost)</label>
              <textarea v-model="form.materials_no_cost" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" rows="3"></textarea>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Materials & Equipment (with cost)</label>
              <textarea v-model="form.materials_with_cost" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" rows="3"></textarea>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Working Committee</label>
              <textarea v-model="form.working_committee" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" rows="2"></textarea>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click.prevent="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click.prevent="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">{{ form.processing ? 'Saving…' : 'Save' }}</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
