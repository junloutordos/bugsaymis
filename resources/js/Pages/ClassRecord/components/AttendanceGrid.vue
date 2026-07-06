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
})

const STATUS_CYCLE = [null, 'present', 'absent', 'late', 'excused']
const STATUS_LABEL = { present: 'P', absent: 'A', late: 'L', excused: 'E' }
const STATUS_CELL_CLASS = {
  present: 'bg-emerald-100 text-emerald-700',
  absent:  'bg-red-100 text-red-700',
  late:    'bg-amber-100 text-amber-700',
  excused: 'bg-blue-100 text-blue-700',
}

const dates          = ref([])
const records        = ref({})
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

function toggleStatus(studentId, dateId) {
  if (props.isLocked) return
  const key     = `${studentId}_${dateId}`
  const current = records.value[key] ?? null
  const idx     = STATUS_CYCLE.indexOf(current)
  const next    = STATUS_CYCLE[(idx + 1) % STATUS_CYCLE.length]
  if (next === null) {
    delete records.value[key]
  } else {
    records.value[key] = next
  }
  pendingChanges.add(key)
}

async function saveAttendance() {
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

function studentTotals(studentId) {
  const total   = dates.value.length
  const present = dates.value.filter(d => records.value[`${studentId}_${d.id}`] === 'present').length
  const absent  = dates.value.filter(d => records.value[`${studentId}_${d.id}`] === 'absent').length
  const late    = dates.value.filter(d => records.value[`${studentId}_${d.id}`] === 'late').length
  const excused = dates.value.filter(d => records.value[`${studentId}_${d.id}`] === 'excused').length
  const marked  = present + absent + late + excused
  const pct     = marked > 0 ? Math.round((present / marked) * 100) : null
  return { total, present, absent, late, excused, pct }
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
        Click a cell to cycle status: — → P → A → L → E → —
      </p>
      <div class="flex items-center gap-2 shrink-0">
        <template v-if="!isLocked">
          <!-- Add date inline input -->
          <div v-if="showAddDate" class="flex items-center gap-2">
            <input v-model="newDateInput" type="date"
              class="rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
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
            <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 w-8 border-r border-slate-100">#</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 min-w-[160px] border-r border-slate-100">Student</th>
            <th v-for="d in dates" :key="d.id"
              class="px-2 py-2 text-center text-xs font-semibold text-slate-500 w-14 border-r border-slate-100 group">
              <div class="flex flex-col items-center gap-0.5">
                <span>{{ formatDate(d.date) }}</span>
                <button v-if="!isLocked" @click="removeDate(d)"
                  class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-red-50 text-slate-300 hover:text-red-400 transition-all"
                  title="Remove this date">
                  <XMarkIcon class="h-3 w-3" />
                </button>
                <span v-else class="h-4" />
              </div>
            </th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-16 border-r border-slate-100">Classes</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-red-400 w-12 border-r border-slate-100">Absent</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-amber-500 w-12 border-r border-slate-100">Late</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-blue-400 w-12 border-r border-slate-100">Excused</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-14">Present %</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(student, idx) in students" :key="student.id"
            :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40'">
            <!-- # -->
            <td class="px-3 py-2 text-slate-400 text-center border-r border-slate-100">
              {{ student.sequence_number }}
            </td>
            <!-- Name -->
            <td class="px-4 py-2 font-medium text-slate-700 border-r border-slate-100 whitespace-nowrap">
              {{ student.family_name }}, {{ student.given_name }}
              <span v-if="student.middle_initial"> {{ student.middle_initial }}.</span>
            </td>
            <!-- Date cells -->
            <td v-for="d in dates" :key="d.id"
              class="px-1 py-1.5 text-center border-r border-slate-100">
              <button
                @click="toggleStatus(student.id, d.id)"
                :class="[
                  'w-8 h-6 rounded text-xs font-bold transition-colors',
                  records[`${student.id}_${d.id}`]
                    ? STATUS_CELL_CLASS[records[`${student.id}_${d.id}`]]
                    : 'text-slate-300 hover:bg-slate-100',
                  pendingChanges.has(`${student.id}_${d.id}`) ? 'ring-1 ring-offset-1 ring-indigo-400' : '',
                  isLocked ? 'cursor-default' : 'cursor-pointer',
                ]"
                :disabled="isLocked"
                :title="isLocked ? '' : 'Click to change status'">
                {{ records[`${student.id}_${d.id}`] ? STATUS_LABEL[records[`${student.id}_${d.id}`]] : '—' }}
              </button>
            </td>
            <!-- Totals -->
            <td class="px-3 py-2 text-center text-slate-500 border-r border-slate-100 font-mono">
              {{ studentTotals(student.id).total }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="studentTotals(student.id).absent > 0 ? 'text-red-600 font-semibold' : 'text-slate-300'">
              {{ studentTotals(student.id).absent || '—' }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="studentTotals(student.id).late > 0 ? 'text-amber-600' : 'text-slate-300'">
              {{ studentTotals(student.id).late || '—' }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="studentTotals(student.id).excused > 0 ? 'text-blue-600' : 'text-slate-300'">
              {{ studentTotals(student.id).excused || '—' }}
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
              class="px-4 py-2 text-xs font-semibold text-slate-500 border-r border-slate-100">
              Present
            </td>
            <td v-for="d in dates" :key="d.id"
              class="px-1 py-2 text-center text-xs font-semibold border-r border-slate-100 text-emerald-600">
              {{ students.filter(s => records[`${s.id}_${d.id}`] === 'present').length }}
            </td>
            <td colspan="5" />
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Legend -->
    <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500">
      <span v-for="[status, label, cls] in [
        ['present', 'Present',     'bg-emerald-100 text-emerald-700'],
        ['absent',  'Absent',      'bg-red-100 text-red-700'],
        ['late',    'Late/Tardy',  'bg-amber-100 text-amber-700'],
        ['excused', 'Excused',     'bg-blue-100 text-blue-700'],
      ]" :key="status" class="inline-flex items-center gap-1.5">
        <span :class="['inline-flex items-center justify-center w-6 h-5 rounded text-xs font-bold', cls]">
          {{ STATUS_LABEL[status] }}
        </span>
        {{ label }}
      </span>
    </div>
  </template>
</template>
