<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
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
  // On a shared (e.g. PEHM) record: every subject on it ({id, name}) plus
  // which of them the CURRENT user owns. Empty sharedSubjects means "not a
  // shared record" — the subject switcher is hidden and every request omits
  // subject_id entirely, identical to pre-PEHM behavior.
  sharedSubjects:  { type: Array, default: () => [] },
  ownedSubjectIds: { type: Array, default: () => [] },
  isAdmin:         { type: Boolean, default: false },
})

// Defaults to the user's own subject if they own exactly one (the common
// case — a PE teacher just wants their own attendance tab); otherwise starts
// on the first subject in the list (admin/monitor browsing).
const selectedSubjectId = ref(
  props.ownedSubjectIds.length === 1 ? props.ownedSubjectIds[0] : (props.sharedSubjects[0]?.id ?? null)
)

const canEditSelectedSubject = computed(() => {
  if (!props.sharedSubjects.length) return true // not a shared record
  if (props.isAdmin) return true
  return props.ownedSubjectIds.includes(selectedSubjectId.value)
})

// ── Status vocabulary ────────────────────────────────────────────────────────
// Present/Absent/Tardy/Cut Class. Excused/Unexcused is no longer a status a
// subject teacher picks here — it's set by the Homeroom Adviser/Registrar's
// Class Admission Slip workflow and shown read-only below.
//
// Cut Class (CIM 3.6/3.6.2) is asserted directly by the subject teacher —
// they're the only one who can witness a student being on campus but
// skipping or leaving this specific class period without valid reason. It is
// NOT selectable on Homeroom Daily Attendance (the adviser can't observe it).
// As a secondary safety net, a mismatch (this record says Absent while the
// student's Homeroom Attendance for the same date says Present/Tardy) is
// still automatically flagged to the Registrar's admission-slip queue too,
// in case a teacher marks Absent without using the Cut Class status.

const STATUSES = [
  { value: 'present',   code: 'P',  label: 'Present',   cls: 'bg-emerald-100 text-emerald-700' },
  { value: 'absent',    code: 'A',  label: 'Absent',    cls: 'bg-red-100 text-red-700' },
  { value: 'tardy',     code: 'T',  label: 'Tardy',     cls: 'bg-amber-100 text-amber-700' },
  { value: 'cut_class', code: 'CC', label: 'Cut Class', cls: 'bg-orange-100 text-orange-700' },
]
const STATUS_BY_VALUE = Object.fromEntries(STATUSES.map(s => [s.value, s]))
// Cycle for cells that already have an explicit record: null (default/Present) → absent → tardy → back to null.
const STATUS_CYCLE = [null, ...STATUSES.map(status => status.value)]
// First click on a still-default cell — Present is already implied, so jump straight past it.
const DEFAULT_STATUS_CYCLE = STATUSES.map(status => status.value)

const EXCUSED_LABELS = { excused: 'Excused', unexcused: 'Unexcused' }

// ── State ────────────────────────────────────────────────────────────────────

const dates          = ref([])
const records        = ref({})   // key `${studentId}_${dateId}` → { status, excused, synced }
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

function cell(studentId, dateId) {
  return records.value[`${studentId}_${dateId}`] ?? null
}

// Effective (displayed) status — falls back to the implicit default
// (Present) when no explicit record has been saved yet. Explicit records
// always take precedence, including an explicit "blank" once one has been
// set via the cycle (see cycleAttendance).
function effectiveStatus(studentId, dateId) {
  return cell(studentId, dateId)?.status ?? 'present'
}
function excusedStatus(studentId, dateId) {
  return cell(studentId, dateId)?.excused ?? 'n_a'
}
function isDefaulted(studentId, dateId) {
  return !cell(studentId, dateId)
}
// IU (Incomplete Uniform) is an independent flag, not a status — a student
// can be Present AND flagged IU on the same day. Synced one-directionally
// into Homeroom's own Inc. Uniform checkbox once saved.
function isIncompleteUniform(studentId, dateId) {
  return cell(studentId, dateId)?.incomplete_uniform ?? false
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(
      route('class-records.attendance.index', { classRecord: props.classRecordId, q: props.quarterNumber }),
      { params: selectedSubjectId.value ? { subject_id: selectedSubjectId.value } : {} }
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
watch(selectedSubjectId, load)

// ── Attendance toggle ────────────────────────────────────────────────────────

function cycleAttendance(studentId, dateId) {
  if (props.isLocked || !canEditSelectedSubject.value) return
  const key  = `${studentId}_${dateId}`
  const prev = records.value[key] ?? { status: null }

  let nextStatus
  if (isDefaulted(studentId, dateId)) {
    // First click on a still-default cell — skip re-selecting Present,
    // go straight to the first exception state.
    nextStatus = DEFAULT_STATUS_CYCLE[0]
  } else {
    const idx = STATUS_CYCLE.indexOf(prev.status ?? null)
    nextStatus = STATUS_CYCLE[(idx + 1) % STATUS_CYCLE.length]
  }

  if (nextStatus === null && !prev.incomplete_uniform) {
    // Nothing exceptional left to record for this cell — safe to drop the row.
    delete records.value[key]
  } else {
    records.value[key] = { ...prev, status: nextStatus }
  }
  pendingChanges.add(key)
}

// IU (Incomplete Uniform) toggles independently of the status cycle — a
// student can be Present and flagged IU on the same day. Kept as its own
// row even if status ends up null, since the flag alone is worth saving
// (and syncing to Homeroom).
function toggleIncompleteUniform(studentId, dateId) {
  if (props.isLocked || !canEditSelectedSubject.value) return
  const key  = `${studentId}_${dateId}`
  const prev = records.value[key] ?? { status: isDefaulted(studentId, dateId) ? null : effectiveStatus(studentId, dateId) }
  const nextFlag = !prev.incomplete_uniform

  if (nextFlag === false && (prev.status ?? null) === null) {
    delete records.value[key]
  } else {
    records.value[key] = { ...prev, incomplete_uniform: nextFlag }
  }
  pendingChanges.add(key)
}

// ── Persistence ──────────────────────────────────────────────────────────────

// Submits every student x date cell's EFFECTIVE status (the implied default —
// Present — included), not just explicitly-touched ones. Before this, an
// untouched cell had no row in the database at all despite visually showing
// Present — anything reading the table directly (exports, other reports)
// would see it as blank.
async function saveAttendance() {
  if (!dates.value.length || !students.value.length) return
  saving.value = true

  const payload = []
  for (const s of students.value) {
    for (const d of dates.value) {
      payload.push({
        student_id: s.id,
        date_id:    d.id,
        status:     effectiveStatus(s.id, d.id),
        incomplete_uniform: isIncompleteUniform(s.id, d.id),
      })
    }
  }

  try {
    await axios.post(
      route('class-records.attendance.upsert', { classRecord: props.classRecordId, q: props.quarterNumber }),
      { records: payload, subject_id: selectedSubjectId.value }
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
      { date: newDateInput.value, subject_id: selectedSubjectId.value }
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
  let present = 0, absences = 0, tardies = 0, cuts = 0, ius = 0
  for (const d of dates.value) {
    const status = effectiveStatus(studentId, d.id)
    if (status === 'present') present++
    if (status === 'absent') absences++
    if (status === 'tardy') tardies++
    if (status === 'cut_class') cuts++
    if (isIncompleteUniform(studentId, d.id)) ius++
  }
  const total = dates.value.length
  const pct = total > 0 ? Math.round((present / total) * 100) : null
  return { total, present, absences, tardies, cuts, ius, pct }
}

function presentCountForDate(dateId) {
  return students.value.filter(s => effectiveStatus(s.id, dateId) === 'present').length
}
</script>

<template>
  <!-- Loading -->
  <div v-if="loading" class="flex items-center justify-center py-12 text-slate-400 text-sm">
    Loading attendance…
  </div>

  <template v-else>
    <!-- Subject switcher: only shown on a shared (e.g. PEHM) record -->
    <div v-if="sharedSubjects.length" class="flex items-center gap-2 mb-3">
      <span class="text-xs font-medium text-slate-500">Subject:</span>
      <select v-model.number="selectedSubjectId"
        class="rounded-lg border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option v-for="subj in sharedSubjects" :key="subj.id" :value="subj.id">{{ subj.name }}</option>
      </select>
      <span v-if="!canEditSelectedSubject" class="text-[11px] text-slate-400 italic">
        Read-only — taught by a different teacher on this shared record.
      </span>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
      <p class="text-xs text-slate-500">
        Cells default to <span class="font-semibold">P</span> —
        click to cycle Present → Absent → Tardy → Cut Class. Excused/Unexcused is decided by the Homeroom Adviser/Registrar and shown read-only.
        Check <span class="font-semibold text-yellow-600">IU</span> below a cell to flag Incomplete Uniform — independent of the status, and synced to Homeroom's Inc. Uniform checkbox.
      </p>
      <div class="flex items-center gap-2 shrink-0">
        <template v-if="!isLocked && canEditSelectedSubject">
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

          <AppButton size="sm" :disabled="!dates.length || saving" @click="saveAttendance">
            {{ saving
              ? 'Saving…'
              : (hasPendingChanges ? `Save Attendance (${pendingChanges.size} unsaved change${pendingChanges.size > 1 ? 's' : ''})` : 'Save Attendance') }}
          </AppButton>
        </template>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!dates.length" class="flex flex-col items-center justify-center py-14 text-slate-400 text-sm gap-2">
      <p>No class dates recorded yet.</p>
      <p v-if="!isLocked && canEditSelectedSubject" class="text-xs">Click "Add Date" above to log a class meeting — or it fills in automatically once the Homeroom Adviser logs the day.</p>
    </div>

    <!-- Grid -->
    <div v-else class="overflow-x-auto rounded-xl border border-slate-100">
      <table class="min-w-full text-xs border-collapse">
        <thead class="bg-slate-50">
          <tr>
            <th class="sticky left-0 z-10 bg-slate-50 px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-8 border-r border-slate-100">#</th>
            <th class="sticky left-8 z-10 bg-slate-50 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide min-w-[160px] border-r border-slate-200">Student</th>
            <th v-for="d in dates" :key="d.id"
              class="px-2 py-2.5 text-center text-xs font-semibold text-slate-500 border-r border-slate-200 group w-14">
              <div class="flex items-center justify-center gap-1">
                <span>{{ formatDate(d.date) }}</span>
                <button v-if="!isLocked && canEditSelectedSubject" @click="removeDate(d)"
                  class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-red-50 text-slate-300 hover:text-red-400 transition-all"
                  title="Remove this date">
                  <XMarkIcon class="h-3 w-3" />
                </button>
              </div>
            </th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-14 border-r border-slate-100">Classes</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-red-400 uppercase tracking-wide w-14 border-r border-slate-100">Absences</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-amber-500 uppercase tracking-wide w-14 border-r border-slate-100">Tardies</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-orange-500 uppercase tracking-wide w-14 border-r border-slate-100">Cuts</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-yellow-600 uppercase tracking-wide w-14 border-r border-slate-100">IU</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-16">Present %</th>
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
            <!-- Date cells -->
            <td v-for="d in dates" :key="d.id" class="px-0.5 py-1.5 text-center border-r border-slate-100">
              <button
                @click="cycleAttendance(student.id, d.id)"
                :class="[
                  'min-w-[30px] h-6 px-1 rounded-md text-[11px] font-bold transition-colors',
                  STATUS_BY_VALUE[effectiveStatus(student.id, d.id)]?.cls,
                  isDefaulted(student.id, d.id) ? 'opacity-60' : '',
                  pendingChanges.has(`${student.id}_${d.id}`) ? 'ring-1 ring-offset-1 ring-indigo-400' : '',
                  isLocked ? 'cursor-default' : 'cursor-pointer',
                ]"
                :disabled="isLocked"
                :title="isLocked
                  ? ''
                  : (isDefaulted(student.id, d.id)
                    ? 'Defaults to Present — click to mark an exception'
                    : STATUS_BY_VALUE[effectiveStatus(student.id, d.id)]?.label)">
                {{ STATUS_BY_VALUE[effectiveStatus(student.id, d.id)]?.code }}
              </button>
              <div v-if="excusedStatus(student.id, d.id) !== 'n_a'"
                class="mt-0.5 text-[9px] font-semibold"
                :class="excusedStatus(student.id, d.id) === 'excused' ? 'text-emerald-600' : 'text-red-500'">
                {{ EXCUSED_LABELS[excusedStatus(student.id, d.id)] }}
              </div>
              <label v-if="!isLocked && canEditSelectedSubject" class="mt-0.5 flex items-center justify-center gap-0.5 cursor-pointer"
                title="Incomplete Uniform (IU) — flags independently of Present/Absent/Tardy/Cut Class; syncs to Homeroom's Inc. Uniform checkbox.">
                <input type="checkbox" :checked="isIncompleteUniform(student.id, d.id)" @change="toggleIncompleteUniform(student.id, d.id)"
                  class="h-3 w-3 rounded-sm border-slate-300 text-yellow-600 focus:ring-yellow-500" />
                <span class="text-[8px] font-semibold text-yellow-600">IU</span>
              </label>
              <div v-else-if="isIncompleteUniform(student.id, d.id)" class="mt-0.5 text-[9px] font-semibold text-yellow-600">
                IU
              </div>
            </td>
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
              :class="studentTotals(student.id).cuts > 0 ? 'text-orange-600 font-semibold' : 'text-slate-300'">
              {{ studentTotals(student.id).cuts || '—' }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="studentTotals(student.id).ius > 0 ? 'text-yellow-600 font-semibold' : 'text-slate-300'">
              {{ studentTotals(student.id).ius || '—' }}
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
            <td v-for="d in dates" :key="d.id"
              class="px-1 py-2 text-center text-xs font-semibold border-r border-slate-200 text-emerald-600">
              {{ presentCountForDate(d.id) }}
            </td>
            <td colspan="6" />
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Legend -->
    <div class="mt-3 space-y-1.5">
      <p class="text-xs text-slate-400">
        Unmarked cells default to <span class="font-semibold text-slate-500">Present</span> — click only to record an exception.
        Mark <span class="font-semibold text-orange-600">Cut Class</span> when a student was on campus but skipped or left this
        period without valid reason (CIM 3.6/3.6.2) — only you, the subject teacher, can witness this. As a backup, a student
        marked Absent here while Homeroom Attendance says Present/Tardy that day is still automatically flagged to the
        Registrar too, in case Cut Class wasn't used. Check <span class="font-semibold text-yellow-600">IU</span> to flag
        Incomplete Uniform for a specific date — it's synced to the Homeroom Adviser's Inc. Uniform checkbox for that day and
        never clears a box the adviser already checked themselves.
      </p>
      <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-slate-500">
        <span v-for="s in STATUSES" :key="s.value" class="inline-flex items-center gap-1.5">
          <span :class="['inline-flex items-center justify-center min-w-[26px] h-5 px-1 rounded-md text-[11px] font-bold', s.cls]">
            {{ s.code }}
          </span>
          {{ s.label }}
        </span>
      </div>
    </div>
  </template>
</template>
