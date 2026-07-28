<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'

const props = defineProps({
  sections: { type: Array, required: true },
  sectionId: { type: [Number, String], required: true },
  students: { type: Array, required: true },
})

const form = useForm({
  section_id: props.sectionId,
  title: '',
  activity_date: new Date().toISOString().slice(0, 10),
  is_off_campus: false,
  rows: props.students.map(s => ({
    student_id: s.student_id,
    am_status: 'present',
    pm_status: 'present',
    remarks: '',
  })),
})

function markAllPresent() {
  form.rows.forEach(r => { r.am_status = 'present'; r.pm_status = 'present' })
}

function submit() {
  form.post(route('homeroom-attendance.activities.store'), { preserveScroll: true })
}
</script>

<template>
  <Head title="Log Activity Attendance" />
  <AdminLayout title="Log Activity Attendance">
    <AppPageHeader title="Log Activity Attendance" subtitle="Attendance During Activities Sheet" />

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-4 mb-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
      <AppInput v-model="form.title" label="Title of Activity" required :error="form.errors.title" />
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Date of Activity</label>
        <input type="date" v-model="form.activity_date"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <label class="flex items-end gap-2 text-sm text-slate-600 pb-2">
        <input type="checkbox" v-model="form.is_off_campus" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
        Off-campus activity
      </label>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
      <div class="flex justify-between items-center px-4 py-2 border-b border-slate-100">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Name of Students</span>
        <AppButton variant="secondary" size="sm" @click="markAllPresent">Mark All Present (AM &amp; PM)</AppButton>
      </div>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">AM</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">PM</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(row, i) in form.rows" :key="row.student_id">
            <td class="px-4 py-2 text-slate-700">{{ students[i]?.name }}</td>
            <td class="px-4 py-2 text-center">
              <button @click="row.am_status = row.am_status === 'present' ? 'absent' : 'present'"
                :class="row.am_status === 'present' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                class="min-w-[30px] h-7 px-2 rounded-md text-[11px] font-bold">
                {{ row.am_status === 'present' ? 'P' : 'A' }}
              </button>
            </td>
            <td class="px-4 py-2 text-center">
              <button @click="row.pm_status = row.pm_status === 'present' ? 'absent' : 'present'"
                :class="row.pm_status === 'present' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                class="min-w-[30px] h-7 px-2 rounded-md text-[11px] font-bold">
                {{ row.pm_status === 'present' ? 'P' : 'A' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex justify-end">
      <AppButton :loading="form.processing" @click="submit">Save Activity Attendance</AppButton>
    </div>
  </AdminLayout>
</template>
