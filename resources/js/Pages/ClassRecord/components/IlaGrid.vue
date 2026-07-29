<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon, XMarkIcon, BoltIcon, AcademicCapIcon, ArrowUturnLeftIcon, PencilIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  classRecordId: { type: Number, required: true },
  quarterNumber: { type: Number, required: true },
  quarterData:   { type: Object, default: null },
  isLocked:      { type: Boolean, default: false },
  leafCategories: { type: Array, default: () => [] },
})

// ── Status vocabulary ────────────────────────────────────────────────────────

const STATUSES = {
  compliant:     { code: 'C',  label: 'Compliant',     cls: 'bg-emerald-100 text-emerald-700' },
  non_compliant: { code: 'NC', label: 'Non-Compliant', cls: 'bg-red-100 text-red-700' },
}
// Cycle: unmarked → Compliant → Non-Compliant → unmarked. Unlike Attendance,
// there's no implied default here — an unmarked cell genuinely means "not
// yet checked", not "assumed compliant".
const STATUS_CYCLE = [null, 'compliant', 'non_compliant']

// ── State ────────────────────────────────────────────────────────────────────

const dates          = ref([])
const records        = ref({})   // key `${studentId}_${dateId}` → status | null
const pendingChanges = reactive(new Set())
const loading        = ref(false)
const saving         = ref(false)
const showAddDate    = ref(false)
const newDateInput   = ref('')
const addingDate     = ref(false)

const students = computed(() =>
  (props.quarterData?.students ?? []).filter(s => s.is_active !== false)
)

const hasPendingChanges = computed(() => pendingChanges.size > 0)

function cellStatus(studentId, dateId) {
  return records.value[`${studentId}_${dateId}`] ?? null
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(
      route('class-records.ila.index', { classRecord: props.classRecordId, q: props.quarterNumber })
    )
    dates.value   = data.dates ?? []
    records.value = data.records ?? {}
    pendingChanges.clear()
  } catch {
    // silent — empty state shown
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => props.quarterNumber, load)

// ── Status toggle ────────────────────────────────────────────────────────────

function cycleStatus(studentId, dateId) {
  if (props.isLocked) return
  const key  = `${studentId}_${dateId}`
  const idx  = STATUS_CYCLE.indexOf(cellStatus(studentId, dateId))
  const next = STATUS_CYCLE[(idx + 1) % STATUS_CYCLE.length]

  if (next === null) {
    delete records.value[key]
  } else {
    records.value[key] = next
  }
  pendingChanges.add(key)
}

// ── Persistence ──────────────────────────────────────────────────────────────

async function saveIla() {
  if (!pendingChanges.size) return
  saving.value = true

  const payload = [...pendingChanges].map(key => {
    const parts = key.split('_')
    return {
      student_id: Number(parts[0]),
      date_id:    Number(parts[1]),
      status:     records.value[key] ?? null,
    }
  })

  try {
    await axios.post(
      route('class-records.ila.upsert', { classRecord: props.classRecordId, q: props.quarterNumber }),
      { records: payload }
    )
    pendingChanges.clear()
    await Swal.fire({ icon: 'success', title: 'ILA saved!', timer: 1000, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to save ILA.', 'error')
  } finally {
    saving.value = false
  }
}

async function addDate() {
  if (!newDateInput.value) return
  addingDate.value = true
  try {
    const { data } = await axios.post(
      route('class-records.ila.store-date', { classRecord: props.classRecordId, q: props.quarterNumber }),
      { date: newDateInput.value }
    )
    dates.value.push(data)
    dates.value.sort((a, b) => a.date.localeCompare(b.date))
    newDateInput.value = ''
    showAddDate.value  = false
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to add date.', 'error')
  } finally {
    addingDate.value = false
  }
}

async function removeDate(dateObj) {
  const label = formatDate(dateObj.date)
  const confirmed = await confirmDelete(`Remove ${label}? All ILA records for this date will be permanently deleted.`)
  if (!confirmed) return

  try {
    await axios.delete(
      route('class-records.ila.destroy-date', {
        classRecord: props.classRecordId,
        q:           props.quarterNumber,
        ilaDate:     dateObj.id,
      })
    )
    dates.value = dates.value.filter(d => d.id !== dateObj.id)
    for (const key of Object.keys(records.value)) {
      if (key.endsWith(`_${dateObj.id}`)) delete records.value[key]
    }
    for (const key of [...pendingChanges]) {
      if (key.endsWith(`_${dateObj.id}`)) pendingChanges.delete(key)
    }
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to remove date.', 'error')
  }
}

async function editTitle(dateObj) {
  const { value: title } = await Swal.fire({
    title: `Activity name — ${formatDate(dateObj.date)}`,
    input: 'text',
    inputValue: dateObj.title ?? '',
    inputPlaceholder: 'e.g. Reading Comprehension Worksheet',
    showCancelButton: true,
    confirmButtonText: 'Save',
  })
  if (title === undefined) return

  try {
    const { data } = await axios.patch(
      route('class-records.ila.update-title', { classRecord: props.classRecordId, q: props.quarterNumber, ilaDate: dateObj.id }),
      { title: title.trim() || null }
    )
    dateObj.title = data.title
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to save the activity name.', 'error')
  }
}

function formatDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
}

// ── Grading ──────────────────────────────────────────────────────────────────
// Deciding to grade an ILA date is the same decision as plotting any other
// WAT assessment — it must be locked in no later than 12:00 NN of the Friday
// before its week. The server enforces the deadline and the daily/weekly
// caps; this just surfaces whatever message it returns.

async function gradeDate(dateObj) {
  if (!props.leafCategories.length) {
    Swal.fire('No grading category available', "This quarter's grading option has no category configured yet — ask an admin to add one before grading ILA sessions.", 'warning')
    return
  }

  const categoryOptions = props.leafCategories
    .map(c => `<option value="${c.id}">${c.name}</option>`).join('')

  const { value: formValues } = await Swal.fire({
    title: `Grade ILA — ${formatDate(dateObj.date)}`,
    html: `
      <div class="text-left text-sm space-y-1">
        <label class="block text-xs font-semibold text-slate-500 mt-1">Grading Category</label>
        <select id="ila-grade-category" class="swal2-select" style="width:100%;margin:0">${categoryOptions}</select>
        <label class="block text-xs font-semibold text-slate-500 mt-2">Title</label>
        <input id="ila-grade-title" class="swal2-input" style="width:100%;margin:0" value="${dateObj.title || `Independent Learning Activity — ${formatDate(dateObj.date)}`}" />
        <label class="block text-xs font-semibold text-slate-500 mt-2">Max Score</label>
        <input id="ila-grade-max" type="number" min="1" step="0.01" class="swal2-input" style="width:100%;margin:0" value="100" />
      </div>
    `,
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Grade this ILA',
    preConfirm: () => {
      const grading_category_id = document.getElementById('ila-grade-category').value
      const title     = document.getElementById('ila-grade-title').value.trim()
      const max_score  = document.getElementById('ila-grade-max').value
      if (!grading_category_id || !title || !max_score || Number(max_score) <= 0) {
        Swal.showValidationMessage('All fields are required.')
        return false
      }
      return { grading_category_id: Number(grading_category_id), title, max_score: Number(max_score) }
    },
  })
  if (!formValues) return

  try {
    await axios.post(
      route('class-records.ila.grade-date', { classRecord: props.classRecordId, q: props.quarterNumber, ilaDate: dateObj.id }),
      formValues
    )
    dateObj.is_graded = true
    await Swal.fire({
      icon: 'success', title: 'ILA graded!',
      text: 'Scores were seeded from the compliance marks already recorded — adjust individual scores in the Weekly Assessment Tracker if needed.',
      timer: 2600, showConfirmButton: false,
    })
  } catch (err) {
    Swal.fire('Cannot grade this ILA date', err.response?.data?.message ?? 'Failed to grade this ILA date.', 'error')
  }
}

async function ungradeDate(dateObj) {
  const confirmed = await confirmDelete(`Revert ${formatDate(dateObj.date)} to non-graded? Any scores entered for it will be removed.`)
  if (!confirmed) return

  try {
    await axios.delete(
      route('class-records.ila.ungrade-date', { classRecord: props.classRecordId, q: props.quarterNumber, ilaDate: dateObj.id })
    )
    dateObj.is_graded = false
  } catch (err) {
    Swal.fire('Cannot un-grade this ILA date', err.response?.data?.message ?? 'Failed to un-grade this ILA date.', 'error')
  }
}

// ── Totals ───────────────────────────────────────────────────────────────────

function studentTotals(studentId) {
  let compliant = 0, nonCompliant = 0
  for (const d of dates.value) {
    const status = cellStatus(studentId, d.id)
    if (status === 'compliant') compliant++
    if (status === 'non_compliant') nonCompliant++
  }
  return { compliant, nonCompliant, total: dates.value.length }
}
</script>

<template>
  <!-- Loading -->
  <div v-if="loading" class="flex items-center justify-center py-12 text-slate-400 text-sm">
    Loading ILA records…
  </div>

  <template v-else>
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
      <p class="text-xs text-slate-500">
        Dates marked <BoltIcon class="h-3 w-3 inline text-indigo-400" /> are auto-created from the class schedule's ILP period ·
        click a cell to cycle unmarked → <span class="font-semibold">C</span>ompliant → <span class="font-semibold">N</span>on-<span class="font-semibold">C</span>ompliant ·
        hover a date and click <AcademicCapIcon class="h-3 w-3 inline text-indigo-400" /> to grade it (must be decided by 12:00 NN the Friday before its week)
      </p>
      <div class="flex items-center gap-2 shrink-0">
        <template v-if="!isLocked">
          <div v-if="showAddDate" class="flex items-center gap-2">
            <input v-model="newDateInput" type="date"
              class="rounded-lg border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <AppButton size="sm" :disabled="!newDateInput || addingDate" @click="addDate">
              {{ addingDate ? 'Adding…' : 'Add' }}
            </AppButton>
            <AppIconButton label="Cancel add date" variant="secondary" size="sm"
              @click="showAddDate = false; newDateInput = ''">
              <XMarkIcon class="h-3.5 w-3.5" />
            </AppIconButton>
          </div>
          <AppButton v-else variant="secondary" size="sm" @click="showAddDate = true">
            <PlusIcon class="h-3.5 w-3.5" /> Add Date
          </AppButton>

          <AppButton size="sm" :disabled="!hasPendingChanges || saving" @click="saveIla">
            {{ saving ? 'Saving…' : (hasPendingChanges ? `Save (${pendingChanges.size} change${pendingChanges.size > 1 ? 's' : ''})` : 'Save ILA') }}
          </AppButton>
        </template>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!dates.length" class="flex flex-col items-center justify-center py-14 text-slate-400 text-sm gap-2">
      <p>No ILA dates yet.</p>
      <p class="text-xs">Dates auto-appear here once this subject's ILP period is scheduled — or click "Add Date" to log one manually.</p>
    </div>

    <!-- Grid -->
    <div v-else class="overflow-x-auto rounded-xl border border-slate-100">
      <table class="min-w-full text-xs border-collapse">
        <thead class="bg-slate-50">
          <tr>
            <th class="sticky left-0 z-10 bg-slate-50 px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-8 border-r border-slate-100">#</th>
            <th class="sticky left-8 z-10 bg-slate-50 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide min-w-[160px] border-r border-slate-200">Student</th>
            <th v-for="d in dates" :key="d.id"
              class="px-2 py-2.5 text-center text-xs font-semibold text-slate-500 border-r border-slate-200 group w-16">
              <div class="flex items-center justify-center gap-1">
                <BoltIcon v-if="d.is_auto_generated" class="h-3 w-3 text-indigo-400 shrink-0" />
                <span :title="d.title || ''">{{ formatDate(d.date) }}</span>
                <span v-if="d.is_graded" class="text-[9px] font-bold text-indigo-600 bg-indigo-50 rounded px-1 shrink-0" title="Scored in the Weekly Assessment Tracker">Graded</span>
                <button v-if="!isLocked && !d.is_graded" @click="editTitle(d)"
                  class="p-0.5 rounded hover:bg-indigo-50 shrink-0 transition-all"
                  :class="d.title ? 'text-indigo-500' : 'opacity-0 group-hover:opacity-100 text-slate-300 hover:text-indigo-500'"
                  :title="d.title ? `Activity: ${d.title} (click to edit)` : 'Set an activity name (shows on the WAT print)'">
                  <PencilIcon class="h-3 w-3" />
                </button>
                <button v-if="!isLocked && !d.is_graded" @click="gradeDate(d)"
                  class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-indigo-50 text-slate-300 hover:text-indigo-500 transition-all"
                  title="Grade this ILA (locks in like any WAT assessment — no later than 12:00 NN the Friday before its week)">
                  <AcademicCapIcon class="h-3 w-3" />
                </button>
                <button v-if="!isLocked && d.is_graded" @click="ungradeDate(d)"
                  class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-amber-50 text-slate-300 hover:text-amber-500 transition-all"
                  title="Revert to non-graded (only before the plotting deadline)">
                  <ArrowUturnLeftIcon class="h-3 w-3" />
                </button>
                <button v-if="!isLocked && !d.is_graded" @click="removeDate(d)"
                  class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-red-50 text-slate-300 hover:text-red-400 transition-all"
                  title="Remove this date">
                  <XMarkIcon class="h-3 w-3" />
                </button>
              </div>
            </th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-emerald-500 uppercase tracking-wide w-14 border-r border-slate-100">Compliant</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-red-400 uppercase tracking-wide w-14">Non-Compliant</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(student, idx) in students" :key="student.id"
            :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40'">
            <td class="sticky left-0 z-10 px-3 py-2 text-slate-400 text-center border-r border-slate-100"
              :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50'">
              {{ student.sequence_number }}
            </td>
            <td class="sticky left-8 z-10 px-4 py-2 font-medium text-slate-700 border-r border-slate-200 whitespace-nowrap"
              :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50'">
              {{ student.family_name }}, {{ student.given_name }}
              <span v-if="student.middle_initial"> {{ student.middle_initial }}.</span>
            </td>
            <td v-for="d in dates" :key="d.id" class="px-1 py-1.5 text-center border-r border-slate-100">
              <button
                @click="cycleStatus(student.id, d.id)"
                :class="[
                  'min-w-[30px] h-6 px-1 rounded-md text-[11px] font-bold transition-colors',
                  cellStatus(student.id, d.id) ? STATUSES[cellStatus(student.id, d.id)].cls : 'bg-slate-100 text-slate-300',
                  pendingChanges.has(`${student.id}_${d.id}`) ? 'ring-1 ring-offset-1 ring-indigo-400' : '',
                  isLocked ? 'cursor-default' : 'cursor-pointer',
                ]"
                :disabled="isLocked"
                :title="isLocked ? '' : (cellStatus(student.id, d.id) ? STATUSES[cellStatus(student.id, d.id)].label : 'Not yet marked — click to mark')">
                {{ cellStatus(student.id, d.id) ? STATUSES[cellStatus(student.id, d.id)].code : '—' }}
              </button>
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="studentTotals(student.id).compliant > 0 ? 'text-emerald-600 font-semibold' : 'text-slate-300'">
              {{ studentTotals(student.id).compliant || '—' }}
            </td>
            <td class="px-3 py-2 text-center font-mono"
              :class="studentTotals(student.id).nonCompliant > 0 ? 'text-red-600 font-semibold' : 'text-slate-300'">
              {{ studentTotals(student.id).nonCompliant || '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Legend -->
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-slate-500">
      <span v-for="(s, k) in STATUSES" :key="k" class="inline-flex items-center gap-1.5">
        <span :class="['inline-flex items-center justify-center min-w-[26px] h-5 px-1 rounded-md text-[11px] font-bold', s.cls]">
          {{ s.code }}
        </span>
        {{ s.label }}
      </span>
      <span class="inline-flex items-center gap-1.5">
        <span class="inline-flex items-center justify-center min-w-[26px] h-5 px-1 rounded-md text-[11px] font-bold bg-slate-100 text-slate-300">—</span>
        Not yet marked
      </span>
    </div>
  </template>
</template>
