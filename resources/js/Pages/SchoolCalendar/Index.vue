<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'
import { TrashIcon } from '@heroicons/vue/24/outline'
import { confirmDelete } from '@/Composables/useConfirm.js'

const props = defineProps({
  year: { type: Number, required: true },
  events: { type: Array, required: true },
})

const yearModel = ref(props.year)

function goto() {
  router.get(route('academic-calendar.index'), { year: yearModel.value }, { preserveState: false })
}

const form = useForm({
  date: '',
  event_type: 'holiday',
  grade_level: '',
  title: '',
})

function submit() {
  form.transform(data => ({ ...data, grade_level: data.grade_level || null }))
    .post(route('academic-calendar.store'), {
      preserveScroll: true,
      onSuccess: () => form.reset('date', 'title', 'grade_level'),
    })
}

async function remove(event) {
  const confirmed = await confirmDelete(`Remove "${event.title}" (${event.date})?`)
  if (!confirmed) return
  router.delete(route('academic-calendar.destroy', event.id), { preserveScroll: true })
}

function formatDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <Head title="Academic Calendar" />
  <AdminLayout title="Academic Calendar">
    <AppPageHeader title="Academic Calendar" subtitle="Flag holidays and class suspensions — used to keep the schedule-driven attendance sync and school-days counts accurate" />

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-4 mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
        <AppInput v-model="form.date" type="date" label="Date" required :error="form.errors.date" />
        <AppSelect v-model="form.event_type" label="Type" :show-blank="false">
          <option value="holiday">Holiday</option>
          <option value="suspension">Class Suspension</option>
        </AppSelect>
        <AppSelect v-model="form.grade_level" label="Scope">
          <option value="">Campus-wide</option>
          <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="String(g)">Grade {{ g }} only</option>
        </AppSelect>
        <AppInput v-model="form.title" label="Title / Reason" required :error="form.errors.title" placeholder="e.g. Typhoon Auring" />
        <AppButton :loading="form.processing" @click="submit">Add</AppButton>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-4 mb-4 flex items-center gap-3">
      <label class="text-xs font-medium text-slate-600">Year</label>
      <input type="number" v-model.number="yearModel" @change="goto"
        class="w-32 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Scope</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="!events.length">
            <td colspan="5" class="px-4 py-8 text-center text-slate-400 text-sm">No holidays or suspensions flagged for {{ year }}.</td>
          </tr>
          <tr v-for="event in events" :key="event.id">
            <td class="px-4 py-2 text-slate-700">{{ formatDate(event.date) }}</td>
            <td class="px-4 py-2 capitalize">{{ event.event_type }}</td>
            <td class="px-4 py-2 text-slate-500">{{ event.grade_level ? `Grade ${event.grade_level}` : 'Campus-wide' }}</td>
            <td class="px-4 py-2 text-slate-500">{{ event.title }}</td>
            <td class="px-4 py-2 text-right">
              <button @click="remove(event)" class="text-slate-300 hover:text-red-500">
                <TrashIcon class="h-4 w-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
