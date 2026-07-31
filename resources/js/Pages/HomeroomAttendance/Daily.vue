<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppButton from '@/Components/AppButton.vue'

const props = defineProps({
  sections: { type: Array, required: true },
  sectionId: { type: [Number, String], required: true },
  date: { type: String, required: true },
  roster: { type: Array, required: true },
  schoolYear: { type: Object, required: true },
  subjectSubmissions: { type: Array, default: () => [] },
})

// Cutting is intentionally not selectable here — it's derived by comparing
// this whole-day status against each subject's own Class Record attendance
// (see AdmissionSlipService::pending()), never hand-picked by the adviser.
const STATUSES = [
  { value: 'present', label: 'Present', code: 'P', cls: 'bg-emerald-100 text-emerald-700' },
  { value: 'absent',  label: 'Absent',  code: 'A', cls: 'bg-red-100 text-red-700' },
  { value: 'tardy',   label: 'Tardy',   code: 'T', cls: 'bg-amber-100 text-amber-700' },
]

const sectionModel = ref(String(props.sectionId))
const dateModel = ref(props.date)
const rows = ref(props.roster.map(r => ({ ...r })))
const saving = ref(false)

function goto() {
  router.get(route('homeroom-attendance.index'), {
    section: sectionModel.value,
    date: dateModel.value,
  }, { preserveState: false })
}

function setStatus(row, status) {
  row.status = status
}

const allPresentCount = computed(() => rows.value.filter(r => r.status === 'present').length)

function markAllPresent() {
  rows.value.forEach(r => { r.status = 'present' })
}

async function save() {
  saving.value = true
  try {
    await axios.post(route('homeroom-attendance.store'), {
      section_id: props.sectionId,
      date: dateModel.value,
      rows: rows.value.map(r => ({
        student_id: r.student_id,
        status: r.status,
        incomplete_uniform: r.incomplete_uniform,
        remarks: r.remarks,
      })),
    })
    await Swal.fire({ icon: 'success', title: 'Attendance saved', timer: 1000, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to save attendance.', 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Head title="Homeroom Attendance" />
  <AdminLayout title="Homeroom Attendance">
    <AppPageHeader
      title="Homeroom Attendance"
      :subtitle="`School Year ${schoolYear.name} — whole-day attendance for your advisory`"
    >
      <template #actions>
        <AppButton variant="secondary" size="sm" @click="markAllPresent">Mark All Present</AppButton>
        <AppButton size="sm" :loading="saving" @click="save">Save Attendance</AppButton>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-4 mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <AppSelect v-model="sectionModel" label="Section" :show-blank="false" @update:modelValue="goto">
          <option v-for="s in sections" :key="s.id" :value="String(s.id)">Grade {{ s.level }} - {{ s.name }}</option>
        </AppSelect>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
          <input type="date" v-model="dateModel" @change="goto"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div class="flex items-end text-sm text-slate-500">
          {{ allPresentCount }} / {{ rows.length }} marked present
        </div>
      </div>
    </div>

    <!-- Subject Attendance Status: who has (and hasn't) submitted Class
         Record Attendance for this section today. Informational only — the
         adviser cannot act on it here; it just tells them whether the Cut
         Class/IU signals below are complete yet. -->
    <div v-if="subjectSubmissions.length" class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-4 mb-4">
      <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
        Subject Attendance Status — {{ subjectSubmissions.filter(s => s.submitted).length }} / {{ subjectSubmissions.length }} submitted today
      </h3>
      <div class="flex flex-wrap gap-2">
        <span v-for="(s, idx) in subjectSubmissions" :key="idx"
          class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium"
          :class="s.submitted ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'">
          <span class="h-1.5 w-1.5 rounded-full" :class="s.submitted ? 'bg-emerald-500' : 'bg-amber-500'"></span>
          {{ s.subject_name }} — {{ s.teacher_name }}
          <span class="font-semibold">{{ s.submitted ? '✓ Submitted' : 'Not yet' }}</span>
        </span>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
            <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Inc. Uniform</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Remarks</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in rows" :key="row.student_id">
            <td class="px-4 py-2 text-slate-700">
              {{ row.name }}
              <span v-if="row.cut_class_subjects?.length"
                class="ml-1.5 inline-flex items-center rounded-md bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold text-orange-700"
                :title="`Marked Cut Class by: ${row.cut_class_subjects.join(', ')} (read-only — set on Class Record Attendance)`">
                CC
              </span>
              <span v-if="row.excused_cut_class_subjects?.length"
                class="ml-1.5 inline-flex items-center rounded-md bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700"
                :title="`Clinic-verified Excused Cutting Class: ${row.excused_cut_class_subjects.join(', ')} (read-only — verified by the subject teacher)`">
                ECC
              </span>
            </td>
            <td class="px-4 py-2">
              <div class="flex justify-center gap-1">
                <button v-for="s in STATUSES" :key="s.value"
                  @click="setStatus(row, s.value)"
                  :class="[
                    'min-w-[30px] h-7 px-1.5 rounded-md text-[11px] font-bold transition-colors',
                    row.status === s.value ? s.cls : 'bg-slate-50 text-slate-300 hover:bg-slate-100',
                  ]"
                  :title="s.label">
                  {{ s.code }}
                </button>
              </div>
            </td>
            <td class="px-4 py-2 text-center">
              <div class="flex items-center justify-center gap-1.5">
                <input type="checkbox" v-model="row.incomplete_uniform" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                <span v-if="row.iu_flagged_subjects?.length"
                  class="inline-flex items-center rounded-md bg-yellow-100 px-1.5 py-0.5 text-[10px] font-bold text-yellow-700"
                  :title="`Flagged by: ${row.iu_flagged_subjects.join(', ')} (synced from Class Record Attendance — you may still adjust it)`">
                  {{ row.iu_flagged_subjects.join(', ') }}
                </span>
              </div>
            </td>
            <td class="px-4 py-2">
              <input type="text" v-model="row.remarks" placeholder="Optional"
                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="mt-3 text-xs text-slate-400">
      Excused/Unexcused status for an Absent or Tardy entry is set later by the Registrar when a Class Admission Slip is issued.
      An orange <span class="font-semibold text-orange-600">CC</span> badge means a subject teacher has marked that student Cut Class
      today. A green <span class="font-semibold text-emerald-600">ECC</span> badge means the subject teacher verified a matching completed
      Health Services clinic slip, so no Registrar admission slip is required. Both are read-only here. A yellow subject badge next to Inc. Uniform means
      a subject teacher already flagged it there — the checkbox is pre-checked for you but you may still adjust it.
    </p>
  </AdminLayout>
</template>
