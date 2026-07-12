<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from "@/Components/AppPageHeader.vue";
import AppButton from "@/Components/AppButton.vue";
import AppTable from "@/Components/AppTable.vue";
import AppModal from "@/Components/AppModal.vue";
import EmptyState from "@/Components/EmptyState.vue";
import { confirmDelete } from "@/Composables/useConfirm.js";
import { PlusIcon } from "@heroicons/vue/24/outline";

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
  if (!(await confirmDelete('Delete this activity? This action cannot be undone.'))) return;
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
    <div class="space-y-5">

      <AppPageHeader title="Activity Planner" subtitle="Manage and track planned activities">
        <template #actions>
          <AppButton @click.prevent="openModal()">
            <PlusIcon class="h-4 w-4" />
            New Activity
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Table -->
      <AppTable :is-empty="activities.length === 0" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Venue</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Working Committee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="a in activities" :key="a.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ a.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(a.date).toLocaleDateString() }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ a.venue ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ a.working_committee ?? '—' }}</td>
          <td class="px-4 py-3">
            <div class="flex gap-1">
              <AppButton size="sm" variant="secondary" @click.prevent="openModal(a)">Edit</AppButton>
              <AppButton size="sm" variant="danger" :disabled="isDeleting" @click.prevent="remove(a)">Delete</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="a in activities" :key="a.id" class="p-4 space-y-2">
            <p class="font-medium text-slate-800">{{ a.name }}</p>
            <p class="text-xs text-slate-500">{{ new Date(a.date).toLocaleDateString() }}</p>
            <p class="text-xs text-slate-500">Venue: {{ a.venue ?? '—' }}</p>
            <p class="text-xs text-slate-500">Committee: {{ a.working_committee ?? '—' }}</p>
            <div class="flex gap-2 pt-1">
              <AppButton size="sm" variant="secondary" @click.prevent="openModal(a)">Edit</AppButton>
              <AppButton size="sm" variant="danger" :disabled="isDeleting" @click.prevent="remove(a)">Delete</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No activities yet." />
        </template>
      </AppTable>

      <!-- Modal -->
      <AppModal :show="showModal" :title="editing ? 'Edit Activity' : 'New Activity'" size="2xl" @close="closeModal">
        <div class="space-y-4">
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

        <template #footer>
          <AppButton variant="secondary" @click.prevent="closeModal">Cancel</AppButton>
          <AppButton :loading="form.processing" @click.prevent="submit">{{ form.processing ? 'Saving…' : 'Save' }}</AppButton>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>
