<script setup>
import { Head, usePage, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'

const props = defineProps({ consultations: Array });
const page = usePage();
const form = useForm({ reason: '', contact: '' });

const submit = () => {
  form.post(route('consultations.store'), {
    onSuccess: () => { form.reset(); },
  });
};

const openSchedule = ref(null);
const scheduleForm = useForm({ scheduled_at: '', notes: '' });
const openFor = (c) => { openSchedule.value = c; scheduleForm.reset(); };
const submitSchedule = () => {
  if (!openSchedule.value) return;
  scheduleForm.put(route('consultations.update', openSchedule.value.id), {
    onSuccess: () => { openSchedule.value = null; scheduleForm.reset(); },
  });
};
</script>

<template>
  <Head title="Consultations" />
  <AdminLayout title="Consultations">
      <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">Consultations</h1>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="font-semibold mb-2">Request Consultation</h2>
        <form @submit.prevent="submit" class="space-y-3">
          <div>
            <label class="block text-sm font-medium">Reason</label>
            <textarea v-model="form.reason" class="mt-1 block w-full rounded border-gray-300" rows="3"></textarea>
            <p v-if="form.errors.reason" class="text-red-600 text-sm">{{ form.errors.reason }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium">Contact No.</label>
            <input v-model="form.contact" class="mt-1 block w-full rounded border-gray-300" />
            <p v-if="form.errors.contact" class="text-red-600 text-sm">{{ form.errors.contact }}</p>
          </div>
          <div>
            <button :disabled="form.processing" class="bg-blue-600 text-white px-4 py-2 rounded">{{ form.processing ? 'Submitting...' : 'Request' }}</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-4">My Consultations</h2>
        <table class="w-full table-auto text-sm">
          <thead class="bg-gray-100 text-gray-700"><tr><th class="px-3 py-2 text-left">#</th><th class="px-3 py-2 text-left">Requestor</th><th class="px-3 py-2 text-left">Reason</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Scheduled</th><th class="px-3 py-2 text-left">Action</th></tr></thead>
          <tbody>
            <tr v-for="c in props.consultations" :key="c.id" class="border-t">
              <td class="px-3 py-2">{{ c.id }}</td>
              <td class="px-3 py-2">{{ c.requestor }}</td>
              <td class="px-3 py-2">{{ c.reason }}</td>
              <td class="px-3 py-2">{{ c.status }}</td>
              <td class="px-3 py-2">{{ c.scheduled_at ? new Date(c.scheduled_at).toLocaleString() : '—' }}</td>
              <td class="px-3 py-2">
                <div class="flex gap-2">
                  <button v-if="['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name)" @click.prevent="openFor(c)" class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded">Schedule</button>
                </div>
              </td>
            </tr>
            <tr v-if="props.consultations.length === 0"><td colspan="6" class="px-3 py-6 text-center text-gray-500">No consultations found.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Schedule Modal -->
      <div v-if="openSchedule" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button class="absolute top-3 right-3" @click="openSchedule = null">✕</button>
          <h3 class="text-lg font-semibold mb-3">Schedule Consultation</h3>
          <form @submit.prevent="submitSchedule" class="space-y-3">
            <div>
              <label class="block text-sm font-medium">Date & Time</label>
              <input type="datetime-local" v-model="scheduleForm.scheduled_at" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="scheduleForm.errors.scheduled_at" class="text-red-600 text-sm">{{ scheduleForm.errors.scheduled_at }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium">Notes</label>
              <textarea v-model="scheduleForm.notes" class="mt-1 block w-full rounded border-gray-300" rows="3"></textarea>
            </div>
            <div class="flex gap-2">
              <button :disabled="scheduleForm.processing" class="bg-blue-600 text-white px-4 py-2 rounded">{{ scheduleForm.processing ? 'Saving...' : 'Save' }}</button>
              <button @click.prevent="openSchedule = null" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
