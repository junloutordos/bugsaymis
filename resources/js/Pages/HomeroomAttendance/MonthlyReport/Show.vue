<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'

const props = defineProps({
  sections: { type: Array, required: true },
  sectionId: { type: [Number, String], required: true },
  year: { type: Number, required: true },
  month: { type: Number, required: true },
  report: { type: Object, default: null },
})

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']

const sectionModel = ref(String(props.sectionId))
const yearModel = ref(props.year)
const monthModel = ref(String(props.month))
const generating = ref(false)
const submitting = ref(false)

function goto() {
  router.get(route('homeroom-attendance.monthly-report.show'), {
    section: sectionModel.value, year: yearModel.value, month: monthModel.value,
  }, { preserveState: false })
}

const boys = computed(() => props.report?.lines.filter(l => (l.sex || '').toUpperCase() === 'M') ?? [])
const girls = computed(() => props.report?.lines.filter(l => (l.sex || '').toUpperCase() !== 'M') ?? [])

async function generate() {
  generating.value = true
  try {
    await axios.post(route('homeroom-attendance.monthly-report.generate'), {
      section_id: Number(sectionModel.value), year: yearModel.value, month: Number(monthModel.value),
    })
    router.reload()
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to generate report.', 'error')
  } finally {
    generating.value = false
  }
}

async function submit() {
  submitting.value = true
  try {
    await axios.post(route('homeroom-attendance.monthly-report.submit', props.report.id))
    router.reload()
    await Swal.fire({ icon: 'success', title: 'Submitted to Homeroom Unit Coordinator', timer: 1200, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to submit report.', 'error')
  } finally {
    submitting.value = false
  }
}

const statusVariant = { draft: 'slate', submitted: 'amber', approved: 'green' }
</script>

<template>
  <Head title="Record on Attendance and Punctuality" />
  <AdminLayout title="Record on Attendance and Punctuality">
    <AppPageHeader title="Record on Attendance and Punctuality" subtitle="Monthly attendance rollup submitted to the Homeroom Unit Coordinator">
      <template #actions>
        <a v-if="report" :href="route('homeroom-attendance.monthly-report.print', report.id)" target="_blank">
          <AppButton variant="secondary" size="sm">Print</AppButton>
        </a>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-4 mb-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
      <AppSelect v-model="sectionModel" label="Section" :show-blank="false" @update:modelValue="goto">
        <option v-for="s in sections" :key="s.id" :value="String(s.id)">Grade {{ s.level }} - {{ s.name }}</option>
      </AppSelect>
      <AppSelect v-model="monthModel" label="Month" :show-blank="false" @update:modelValue="goto">
        <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1)">{{ m }}</option>
      </AppSelect>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
        <input type="number" v-model.number="yearModel" @change="goto"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
    </div>

    <div v-if="!report" class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-8 text-center">
      <p class="text-slate-500 text-sm mb-4">No report yet for {{ MONTHS[month - 1] }} {{ year }}.</p>
      <AppButton :loading="generating" @click="generate">Generate from Logged Attendance</AppButton>
    </div>

    <template v-else>
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 text-sm text-slate-600">
          <AppBadge :color="statusVariant[report.status]">{{ report.status }}</AppBadge>
          <span>{{ report.school_days_count }} school days logged</span>
        </div>
        <div class="flex gap-2">
          <AppButton v-if="report.status === 'draft'" variant="secondary" size="sm" :loading="generating" @click="generate">
            Regenerate
          </AppButton>
          <AppButton v-if="report.status === 'draft'" size="sm" :loading="submitting" @click="submit">
            Submit to Coordinator
          </AppButton>
        </div>
      </div>

      <p v-if="report.coordinator_remarks" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4">
        <strong>Coordinator remarks:</strong> {{ report.coordinator_remarks }}
      </p>

      <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
              <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Days Present</th>
              <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Excused Abs.</th>
              <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Unexcused Abs.</th>
              <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Cutting</th>
              <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Tardy</th>
              <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Inc. Uniform</th>
              <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Remarks</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-for="(group, label) in { BOYS: boys, GIRLS: girls }" :key="label">
              <tr class="bg-slate-50/70">
                <td colspan="8" class="px-3 py-1.5 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ label }}</td>
              </tr>
              <tr v-for="line in group" :key="line.student_id">
                <td class="px-3 py-2 text-slate-700">{{ line.student_name }}</td>
                <td class="px-3 py-2 text-center font-mono">{{ line.days_present }}</td>
                <td class="px-3 py-2 text-center">{{ line.excused_absences || '—' }}</td>
                <td class="px-3 py-2 text-center" :class="line.unexcused_absences ? 'text-red-600 font-semibold' : ''">{{ line.unexcused_absences || '—' }}</td>
                <td class="px-3 py-2 text-center">{{ line.cutting_count || '—' }}</td>
                <td class="px-3 py-2 text-center">{{ line.tardy_count || '—' }}</td>
                <td class="px-3 py-2 text-center">{{ line.inc_uniform_count || '—' }}</td>
                <td class="px-3 py-2 text-slate-500">{{ line.remarks }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </template>
  </AdminLayout>
</template>
