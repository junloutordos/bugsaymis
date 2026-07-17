<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  classRecordId: { type: Number, required: true },
  quarterNumber: { type: Number, required: true },
  quarterData:   { type: Object, default: null },
  isLocked:      { type: Boolean, default: false },
})

// ── Status vocabulary ────────────────────────────────────────────────────────

const STATUSES = [
  { value: 'present',           code: 'P',  label: 'Present',           cls: 'bg-emerald-100 text-emerald-700' },
  { value: 'absent',            code: 'A',  label: 'Absent',            cls: 'bg-red-100 text-red-700' },
  { value: 'late',              code: 'L',  label: 'Late/Tardy',        cls: 'bg-amber-100 text-amber-700' },
  { value: 'excused',           code: 'E',  label: 'Excused',           cls: 'bg-blue-100 text-blue-700' },
  { value: 'cut_class',         code: 'CC', label: 'Cut Class',         cls: 'bg-rose-100 text-rose-700' },
  { value: 'unexcused_absence', code: 'UA', label: 'Unexcused Absence', cls: 'bg-red-100 text-red-700' },
  { value: 'excused_absence',   code: 'EU', label: 'Excused Absence',   cls: 'bg-blue-100 text-blue-700' },
  { value: 'excused_tardy',     code: 'ET', label: 'Excused Tardy',     cls: 'bg-amber-100 text-amber-700' },
  { value: 'unexcused_tardy',   code: 'UT', label: 'Unexcused Tardy',   cls: 'bg-orange-100 text-orange-700' },
]
const STATUS_BY_VALUE = Object.fromEntries(STATUSES.map(s => [s.value, s]))

// Tally groups for the totals columns.
const ABSENCE_SET = new Set(['absent', 'unexcused_absence', 'excused_absence', 'cut_class'])
const TARDY_SET   = new Set(['late', 'excused_tardy', 'unexcused_tardy'])

const UNIFORMS = {
  complete:   { code: 'CU', label: 'Complete Uniform',   cls: 'bg-sky-100 text-sky-700' },
  incomplete: { code: 'IU', label: 'Incomplete Uniform', cls: 'bg-orange-100 text-orange-700' },
}
const UNIFORM_CYCLE = [null, 'complete', 'incomplete']

// ── State ────────────────────────────────────────────────────────────────────

const dates          = ref([])
const records        = ref({})   // key `${studentId}_${dateId}` → { status, uniform }
const pendingChanges = reactive(new Set())
const loading        = ref(false)
const saving         = ref(false)
const showAddDate    = ref(false)
const newDateInput   = ref('')
const addingDate     = ref(false)
const picker         = ref(null)  // { studentId, dateId, x, y }

const students = computed(() =>
  (props.quarterData?.students ?? []).filter(s => s.is_active !== false)
)

const hasPendingChanges = computed(() => pendingChanges.size > 0)

function cell(studentId, dateId) {
  return records.value[`${studentId}_${dateId}`] ?? null
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(
      route('class-records.attendance.index', { classRecord: props.classRecordId, q: props.quarterNumber })
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

// ── Status picker popover ────────────────────────────────────────────────────

function openPicker(e, studentId, dateId) {
  if (props.isLocked) return
  const rect = e.currentTarget.getBoundingClientRect()
  picker.value = {
    studentId, dateId,
    x: Math.min(rect.left, window.innerWidth - 250),
    y: Math.min(rect.bottom + 6, window.innerHeight - 350),
  }
}

function pickStatus(value) {
  const { studentId, dateId } = picker.value
  const key  = `${studentId}_${dateId}`
  const prev = records.value[key] ?? { status: null, uniform: null }
  const next = { ...prev, status: value }
  if (next.status === null && next.uniform === null) {
    delete records.value[key]
  } else {
    records.value[key] = next
  }
  pendingChanges.add(key)
  picker.value = null
}

function onWindowMousedown(e) {
  if (picker.value && !e.target.closest('[data-att-picker]')) picker.value = null
}
function onWindowKeydown(e) {
  if (e.key === 'Escape') picker.value = null
}
onMounted(() => {
  window.addEventListener('mousedown', onWindowMousedown, true)
  window.addEventListener('keydown', onWindowKeydown)
})
onBeforeUnmount(() => {
  window.removeEventListener('mousedown', onWindowMousedown, true)
  window.removeEventListener('keydown', onWindowKeydown)
})

// ── Uniform toggle (3 states — cycling stays comfortable) ────────────────────

function toggleUniform(studentId, dateId) {
  if (props.isLocked) return
  const key  = `${studentId}_${dateId}`
  const prev = records.value[key] ?? { status: null, uniform: null }
  const idx  = UNIFORM_CYCLE.indexOf(prev.uniform ?? null)
  const next = { ...prev, uniform: UNIFORM_CYCLE[(idx + 1) % UNIFORM_CYCLE.length] }
  if (next.status === null && next.uniform === null) {
    delete records.value[key]
  } else {
    records.value[key] = next
  }
  pendingChanges.add(key)
}

// ── Persistence ──────────────────────────────────────────────────────────────

async function saveAttendance() {
  if (!pendingChanges.size) return
  saving.value = true

  const payload = [...pendingChanges].map(key => {
    const parts = key.split('_')
    const value = records.value[key] ?? {}
    return {
      student_id: Number(parts[0]),
      date_id:    Number(parts[1]),
      status:     value.status ?? null,
      uniform:    value.uniform ?? null,
    }
  })

  try {
    await axios.post(
      route('class-records.attendance.upsert', { classRecord: props.classRecordId, q: props.quarterNumber }),
      { records: payload }
    )
    pendingChanges.clear()
    await Swal.fire({ icon: 'success', title: 'Attendance saved!', timer: 1000, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to save attendance.', 'error')
  } finally {
    saving.value = false
  }
}

async function addDate() {
  if (!newDateInput.value) return
  addingDate.value = true
  try {
    const { data } = await axios.post(
      route('class-records.attendance.store-dates', { classRecord: props.classRecordId, q: props.quarterNumber }),
      { date: newDateInput.value }
    )
    // Insert and keep sorted by date
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
  const confirmed = await confirmDelete(`Remove ${label}? All attendance records for this date will be permanently deleted.`)
  if (!confirmed) return

  try {
    await axios.delete(
      route('class-records.attendance.destroy-date', {
        classRecord:    props.classRecordId,
        q:              props.quarterNumber,
        attendanceDate: dateObj.id,
      })
    )
    dates.value = dates.value.filter(d => d.id !== dateObj.id)
    // Clean up local state
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

function formatDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
}

// ── Totals ───────────────────────────────────────────────────────────────────

function studentTotals(studentId) {
  let present = 0, absences = 0, tardies = 0, iu = 0, marked = 0
  for (const d of dates.value) {
    const value = cell(studentId, d.id)
    if (!value) continue
    if (value.status) {
      marked++
      if (value.status === 'present') present++
      if (ABSENCE_SET.has(value.status)) absences++
      if (TARDY_SET.has(value.status)) tardies++
    }
    if (value.uniform === 'incomplete') iu++
  }
  const pct = marked > 0 ? Math.round((present / marked) * 100) : null
  return { total: dates.value.length, present, absences, tardies, iu, pct }
}

function presentCountForDate(dateId) {
  return students.value.filter(s => cell(s.id, dateId)?.status === 'present').length
}
</script>

<template>
  <!-- Loading -->
  <div v-if="loading" class="flex items-center justify-center py-12 text-slate-400 text-sm">
    Loading attendance…
  </div>

  <template v-else>
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
      <p class="text-xs text-slate-500">
        Click an <span class="font-semibold">Att</span> cell to pick a status ·
        click a <span class="font-semibold">Unif</span> cell to cycle — → CU → IU
      </p>
      <div class="flex items-center gap-2 shrink-0">
        <template v-if="!isLocked">
          <!-- Add date inline input -->
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

          <AppButton size="sm" :disabled="!hasPendingChanges || saving" @click="saveAttendance">
            {{ saving ? 'Saving…' : (hasPendingChanges ? `Save (${pendingChanges.size} change${pendingChanges.size > 1 ? 's' : ''})` : 'Save Attendance') }}
          </AppButton>
        </template>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!dates.length" class="flex flex-col items-center justify-center py-14 text-slate-400 text-sm gap-2">
      <p>No class dates recorded yet.</p>
      <p v-if="!isLocked" class="text-xs">Click "Add Date" above to log a class meeting.</p>
    </div>

    <!-- Grid -->
    <div v-else class="overflow-x-auto rounded-xl border border-slate-100">
      <table class="min-w-full text-xs border-collapse">
        <thead class="bg-slate-50">
          <tr>
            <th rowspan="2" class="sticky left-0 z-10 bg-slate-50 px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-8 border-r border-slate-100">#</th>
            <th rowspan="2" class="sticky left-8 z-10 bg-slate-50 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide min-w-[160px] border-r border-slate-200">Student</th>
            <th v-for="d in dates" :key="d.id" colspan="2"
              class="px-2 pt-2 pb-1 text-center text-xs font-semibold text-slate-500 border-r border-slate-200 group">
              <div class="flex items-center justify-center gap-1">
                <span>{{ formatDate(d.date) }}</span>
                <button v-if="!isLocked" @click="removeDate(d)"
                  class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-red-50 text-slate-300 hover:text-red-400 transition-all"
                  title="Remove this date">
                  <XMarkIcon class="h-3 w-3" />
                </button>
              </div>
            </th>
            <th rowspan="2" class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-14 border-r border-slate-100">Classes</th>
            <th rowspan="2" class="px-3 py-2.5 text-center text-xs font-semibold text-red-400 uppercase tracking-wide w-14 border-r border-slate-100">Absences</th>
            <th rowspan="2" class="px-3 py-2.5 text-center text-xs font-semibold text-amber-500 uppercase tracking-wide w-14 border-r border-slate-100">Tardies</th>
            <th rowspan="2" class="px-3 py-2.5 text-center text-xs font-semibold text-orange-500 uppercase tracking-wide w-12 border-r border-slate-100" title="Incomplete Uniform days">IU</th>
            <th rowspan="2" class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-16">Present %</th>
          </tr>
          <tr>
            <template v-for="d in dates" :key="'sub' + d.id">
              <th class="px-1 pb-1.5 text-center text-[9px] font-semibold text-slate-400 uppercase tracking-wider w-9 border-r border-slate-100">Att</th>
              <th class="px-1 pb-1.5 text-center text-[9px] font-semibold text-slate-400 uppercase tracking-wider w-9 border-r border-slate-200">Unif</th>
            </template>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(student, idx) in students" :key="student.id"
            :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40'">
            <!-- # -->
            <td class="sticky left-0 z-10 px-3 py-2 text-slate-400 text-center border-r border-slate-100"
              :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50'">
              {{ student.sequence_number }}
            </td>
            <!-- Name -->
            <td class="sticky left-8 z-10 px-4 py-2 font-medium text-slate-700 border-r border-slate-200 whitespace-nowrap"
              :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50'">
              {{ student.family_name }}, {{ student.given_name }}
              <span v-if="student.middle_initial"> {{ student.middle_initial }}.</span>
            </td>
            <!-- Date cells: attendance + uniform -->
            <template v-for="d in dates" :key="d.id">
              <td class="px-0.5 py-1.5 text-center border-r border-slate-100">
                <button
                  data-att-picker
                  @click="openPicker($event, student.id, d.id)"
                  :class="[
                    'min-w-[30px] h-6 px-1 rounded-md text-[11px] font-bold transition-colors',
                    cell(student.id, d.id)?.status
                      ? STATUS_BY_VALUE[cell(student.id, d.id).status]?.cls
                      : 'text-slate-300 hover:bg-slate-100',
                    pendingChanges.has(`${student.id}_${d.id}`) ? 'ring-1 ring-offset-1 ring-indigo-400' : '',
                    isLocked ? 'cursor-default' : 'cursor-pointer',
                  ]"
                  :disabled="isLocked"
                  :title="cell(student.id, d.id)?.status
                    ? STATUS_BY_VALUE[cell(student.id, d.id).status]?.label
                    : (isLocked ? '' : 'Set attendance status')">
                  {{ cell(student.id, d.id)?.status ? STATUS_BY_VALUE[cell(student.id, d.id).status]?.code : '—' }}
                </button>
              </td>
              <td class="px-0.5 py-1.5 text-center border-r border-slate-200">
                <button
                  @click="toggleUniform(student.id, d.id)"
                  :class="[
                    'min-w-[30px] h-6 px-1 rounded-md text-[11px] font-bold transition-colors',
                    cell(student.id, d.id)?.uniform
                      ? UNIFORMS[cell(student.id, d.id).uniform].cls
                      : 'text-slate-300 hover:bg-slate-100',
                    pendingChanges.has(`${student.id}_${d.id}`) ? 'ring-1 ring-offset-1 ring-indigo-400' : '',
                    isLocked ? 'cursor-default' : 'cursor-pointer',
                  ]"
                  :disabled="isLocked"
                  :title="cell(student.id, d.id)?.uniform
                    ? UNIFORMS[cell(student.id, d.id).uniform].label
                    : (isLocked ? '' : 'Cycle uniform check: — → CU → IU')">
                  {{ cell(student.id, d.id)?.uniform ? UNIFORMS[cell(student.id, d.id).uniform].code : '—' }}
                </button>
              </td>
            </template>
            <!-- Totals -->
            <td class="px-3 py-2 text-center text-slate-500 border-r border-slate-100 font-mono">
              {{ studentTotals(student.id).total }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="studentTotals(student.id).absences > 0 ? 'text-red-600 font-semibold' : 'text-slate-300'">
              {{ studentTotals(student.id).absences || '—' }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="studentTotals(student.id).tardies > 0 ? 'text-amber-600' : 'text-slate-300'">
              {{ studentTotals(student.id).tardies || '—' }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="studentTotals(student.id).iu > 0 ? 'text-orange-600' : 'text-slate-300'">
              {{ studentTotals(student.id).iu || '—' }}
            </td>
            <td class="px-3 py-2 text-center font-mono font-semibold"
              :class="studentTotals(student.id).pct !== null
                ? (studentTotals(student.id).pct >= 80 ? 'text-emerald-600' : 'text-red-500')
                : 'text-slate-300'">
              {{ studentTotals(student.id).pct !== null ? studentTotals(student.id).pct + '%' : '—' }}
            </td>
          </tr>
        </tbody>
        <!-- Footer: present count per date -->
        <tfoot class="bg-slate-50 border-t border-slate-200">
          <tr>
            <td colspan="2"
              class="sticky left-0 z-10 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wide border-r border-slate-200">
              Present
            </td>
            <td v-for="d in dates" :key="d.id" colspan="2"
              class="px-1 py-2 text-center text-xs font-semibold border-r border-slate-200 text-emerald-600">
              {{ presentCountForDate(d.id) }}
            </td>
            <td colspan="5" />
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Legend -->
    <div class="mt-3 space-y-1.5">
      <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-slate-500">
        <span v-for="s in STATUSES" :key="s.value" class="inline-flex items-center gap-1.5">
          <span :class="['inline-flex items-center justify-center min-w-[26px] h-5 px-1 rounded-md text-[11px] font-bold', s.cls]">
            {{ s.code }}
          </span>
          {{ s.label }}
        </span>
      </div>
      <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-slate-500">
        <span v-for="(u, k) in UNIFORMS" :key="k" class="inline-flex items-center gap-1.5">
          <span :class="['inline-flex items-center justify-center min-w-[26px] h-5 px-1 rounded-md text-[11px] font-bold', u.cls]">
            {{ u.code }}
          </span>
          {{ u.label }}
        </span>
      </div>
    </div>

    <!-- Status picker popover -->
    <Teleport to="body">
      <div v-if="picker" data-att-picker
        class="fixed z-50 w-60 rounded-xl border border-slate-200 bg-white shadow-xl p-2"
        :style="{ left: picker.x + 'px', top: picker.y + 'px' }">
        <p class="px-1.5 pb-1.5 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Attendance status</p>
        <div class="grid grid-cols-1 gap-0.5">
          <button v-for="s in STATUSES" :key="s.value" type="button" @click="pickStatus(s.value)"
            class="flex items-center gap-2 rounded-lg px-1.5 py-1 text-left text-xs text-slate-600 hover:bg-slate-50">
            <span :class="['inline-flex items-center justify-center min-w-[26px] h-5 px-1 rounded-md text-[11px] font-bold', s.cls]">
              {{ s.code }}
            </span>
            {{ s.label }}
          </button>
          <button type="button" @click="pickStatus(null)"
            class="flex items-center gap-2 rounded-lg px-1.5 py-1 text-left text-xs text-slate-400 hover:bg-slate-50 border-t border-slate-100 mt-0.5 pt-1.5">
            <span class="inline-flex items-center justify-center min-w-[26px] h-5 px-1 rounded-md text-[11px] font-bold bg-slate-100 text-slate-400">—</span>
            Clear
          </button>
        </div>
      </div>
    </Teleport>
  </template>
</template>
