<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppModal from '@/Components/AppModal.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  cycleId: { type: Number, required: true },
  canEdit: { type: Boolean, default: false },
})

// ── Status vocabulary ────────────────────────────────────────────────────────

const STATUSES = [
  { value: 'present', code: 'P',  label: 'Present', cls: 'bg-emerald-100 text-emerald-700' },
  { value: 'absent',  code: 'A',  label: 'Absent',  cls: 'bg-red-100 text-red-700' },
  { value: 'tardy',   code: 'T',  label: 'Tardy',   cls: 'bg-amber-100 text-amber-700' },
  { value: 'cutting', code: 'C',  label: 'Cutting', cls: 'bg-orange-100 text-orange-700' },
  { value: 'excused', code: 'E',  label: 'Excused', cls: 'bg-slate-200 text-slate-700' },
]
const STATUS_BY_VALUE = Object.fromEntries(STATUSES.map(s => [s.value, s]))
// Cycle for cells that already have an explicit record: null (default/Present) → absent → tardy → cutting → excused → back to null.
const STATUS_CYCLE = [null, ...STATUSES.map(s => s.value)]
// First click on a still-default cell — Present is already implied, so jump straight past it.
const DEFAULT_STATUS_CYCLE = STATUSES.map(s => s.value)

// ── State ────────────────────────────────────────────────────────────────────

const members       = ref([])   // roster is a given — always the full active-member list, never something the user creates
const dates          = ref([])
const records        = ref({})  // key `${membershipId}_${dateId}` → { status, remarks }
const pendingChanges = reactive(new Set())
const loading        = ref(false)
const saving         = ref(false)
const showAddDate    = ref(false)
const newDateInput   = ref('')
const addingDate     = ref(false)
const remarkCell     = ref(null)
const remarkDraft    = ref('')

function cell(membershipId, dateId) {
  return records.value[`${membershipId}_${dateId}`] ?? null
}

// Effective (displayed) status — falls back to the implicit default
// (Present) when no explicit record has been saved yet.
function effectiveStatus(membershipId, dateId) {
  return cell(membershipId, dateId)?.status ?? 'present'
}
function isDefaulted(membershipId, dateId) {
  return !cell(membershipId, dateId)
}
function isExceptionStatus(membershipId, dateId) {
  return effectiveStatus(membershipId, dateId) !== 'present'
}
function attendanceRemark(membershipId, dateId) {
  return cell(membershipId, dateId)?.remarks ?? ''
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(route('alp.attendance.index', props.cycleId))
    members.value = data.members ?? []
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

// ── Attendance toggle ───────────────────────────────────────────────────────

function cycleAttendance(membershipId, dateId) {
  if (!props.canEdit) return
  const key  = `${membershipId}_${dateId}`
  const prev = records.value[key] ?? { status: null }

  let nextStatus
  if (isDefaulted(membershipId, dateId)) {
    nextStatus = DEFAULT_STATUS_CYCLE[0]
  } else {
    const idx = STATUS_CYCLE.indexOf(prev.status ?? null)
    nextStatus = STATUS_CYCLE[(idx + 1) % STATUS_CYCLE.length]
  }

  const nextRemarks = nextStatus === null || nextStatus === 'present' ? null : (prev.remarks ?? null)

  if (nextStatus === null) {
    delete records.value[key]
  } else {
    records.value[key] = { ...prev, status: nextStatus, remarks: nextRemarks }
  }
  pendingChanges.add(key)
}

function openRemarks(member, date) {
  if (!isExceptionStatus(member.membership_id, date.id)) return
  remarkCell.value = { member, date }
  remarkDraft.value = attendanceRemark(member.membership_id, date.id)
}
function closeRemarks() {
  remarkCell.value = null
  remarkDraft.value = ''
}
function applyRemark() {
  if (!remarkCell.value || !props.canEdit) return
  const { member, date } = remarkCell.value
  const key = `${member.membership_id}_${date.id}`
  const prev = records.value[key] ?? { status: effectiveStatus(member.membership_id, date.id) }
  const normalized = remarkDraft.value.trim() || null

  records.value[key] = { ...prev, remarks: normalized }
  pendingChanges.add(key)
  closeRemarks()
}

// ── Persistence ──────────────────────────────────────────────────────────────

// Submits every member x date cell's EFFECTIVE status (the implied default —
// Present — included), not just explicitly-touched ones, mirroring Class
// Record's AttendanceGrid.vue. A cell with no DB row still shows Present on
// screen; this guarantees the database matches what's displayed once saved.
async function saveAttendance() {
  if (!dates.value.length || !members.value.length) return
  saving.value = true

  const payload = []
  for (const m of members.value) {
    for (const d of dates.value) {
      payload.push({
        session_id: d.id,
        membership_id: m.membership_id,
        status: effectiveStatus(m.membership_id, d.id),
        remarks: isExceptionStatus(m.membership_id, d.id) ? (attendanceRemark(m.membership_id, d.id).trim() || null) : null,
      })
    }
  }

  try {
    await axios.post(route('alp.attendance.upsert', props.cycleId), { records: payload })
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
    await axios.post(route('alp.attendance.dates.store', props.cycleId), { date: newDateInput.value })
    newDateInput.value = ''
    showAddDate.value  = false
    // A new date backfills default "present" rows server-side — reload so
    // the grid reflects them (and any member added since the last load).
    await load()
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
    await axios.delete(route('alp.attendance.dates.destroy', [props.cycleId, dateObj.id]))
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

function formatDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
}

// ── Totals ───────────────────────────────────────────────────────────────────

function memberTotals(membershipId) {
  let present = 0, absences = 0, tardies = 0, cuts = 0, excused = 0
  for (const d of dates.value) {
    const status = effectiveStatus(membershipId, d.id)
    if (status === 'present') present++
    if (status === 'absent') absences++
    if (status === 'tardy') tardies++
    if (status === 'cutting') cuts++
    if (status === 'excused') excused++
  }
  const total = dates.value.length
  const pct = total > 0 ? Math.round((present / total) * 100) : null
  return { total, present, absences, tardies, cuts, excused, pct }
}

function presentCountForDate(dateId) {
  return members.value.filter(m => effectiveStatus(m.membership_id, dateId) === 'present').length
}

const hasPendingChanges = computed(() => pendingChanges.size > 0)
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
        Cells default to <span class="font-semibold">P</span> —
        click to cycle Present → Absent → Tardy → Cutting → Excused. For an exception, use the remark link to add optional context.
      </p>
      <div class="flex items-center gap-2 shrink-0">
        <template v-if="canEdit">
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
      <p>No attendance dates recorded yet.</p>
      <p v-if="canEdit" class="text-xs">Click "Add Date" above to log a session meeting — every active member will appear automatically.</p>
    </div>

    <!-- Grid -->
    <div v-else class="overflow-x-auto rounded-xl border border-slate-100">
      <table class="min-w-full text-xs border-collapse">
        <thead class="bg-slate-50">
          <tr>
            <th class="sticky left-0 z-10 bg-slate-50 px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-8 border-r border-slate-100">#</th>
            <th class="sticky left-8 z-10 bg-slate-50 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide min-w-[160px] border-r border-slate-200">Member</th>
            <th v-for="d in dates" :key="d.id"
              class="px-2 py-2.5 text-center text-xs font-semibold text-slate-500 border-r border-slate-200 group w-14">
              <div class="flex items-center justify-center gap-1">
                <span>{{ formatDate(d.date) }}</span>
                <button v-if="canEdit" @click="removeDate(d)"
                  class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-red-50 text-slate-300 hover:text-red-400 transition-all"
                  title="Remove this date">
                  <XMarkIcon class="h-3 w-3" />
                </button>
              </div>
            </th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-14 border-r border-slate-100">Sessions</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-red-400 uppercase tracking-wide w-14 border-r border-slate-100">Absences</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-amber-500 uppercase tracking-wide w-14 border-r border-slate-100">Tardies</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-orange-500 uppercase tracking-wide w-14 border-r border-slate-100">Cutting</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-14 border-r border-slate-100">Excused</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-16">Present %</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(member, idx) in members" :key="member.membership_id"
            :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40'">
            <td class="sticky left-0 z-10 px-3 py-2 text-slate-400 text-center border-r border-slate-100"
              :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50'">
              {{ idx + 1 }}
            </td>
            <td class="sticky left-8 z-10 px-4 py-2 font-medium text-slate-700 border-r border-slate-200 whitespace-nowrap"
              :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50'">
              {{ member.name }}
            </td>
            <td v-for="d in dates" :key="d.id" class="px-0.5 py-1.5 text-center border-r border-slate-100">
              <button
                @click="cycleAttendance(member.membership_id, d.id)"
                :class="[
                  'min-w-[30px] h-6 px-1 rounded-md text-[11px] font-bold transition-colors',
                  STATUS_BY_VALUE[effectiveStatus(member.membership_id, d.id)]?.cls,
                  isDefaulted(member.membership_id, d.id) ? 'opacity-60' : '',
                  pendingChanges.has(`${member.membership_id}_${d.id}`) ? 'ring-1 ring-offset-1 ring-indigo-400' : '',
                  canEdit ? 'cursor-pointer' : 'cursor-default',
                ]"
                :disabled="!canEdit"
                :title="!canEdit ? '' : (isDefaulted(member.membership_id, d.id) ? 'Defaults to Present — click to mark an exception' : STATUS_BY_VALUE[effectiveStatus(member.membership_id, d.id)]?.label)">
                {{ STATUS_BY_VALUE[effectiveStatus(member.membership_id, d.id)]?.code }}
              </button>
              <button v-if="isExceptionStatus(member.membership_id, d.id)"
                type="button"
                class="mx-auto mt-0.5 block text-[8px] font-semibold transition-colors"
                :class="attendanceRemark(member.membership_id, d.id) ? 'text-indigo-600' : 'text-slate-400 hover:text-indigo-600'"
                :title="attendanceRemark(member.membership_id, d.id) || 'Add an optional remark'"
                @click.stop="openRemarks(member, d)">
                {{ attendanceRemark(member.membership_id, d.id) ? 'Remark' : '+ Remark' }}
              </button>
            </td>
            <td class="px-3 py-2 text-center text-slate-500 border-r border-slate-100 font-mono">
              {{ memberTotals(member.membership_id).total }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="memberTotals(member.membership_id).absences > 0 ? 'text-red-600 font-semibold' : 'text-slate-300'">
              {{ memberTotals(member.membership_id).absences || '—' }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="memberTotals(member.membership_id).tardies > 0 ? 'text-amber-600' : 'text-slate-300'">
              {{ memberTotals(member.membership_id).tardies || '—' }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="memberTotals(member.membership_id).cuts > 0 ? 'text-orange-600 font-semibold' : 'text-slate-300'">
              {{ memberTotals(member.membership_id).cuts || '—' }}
            </td>
            <td class="px-3 py-2 text-center border-r border-slate-100 font-mono"
              :class="memberTotals(member.membership_id).excused > 0 ? 'text-slate-600 font-semibold' : 'text-slate-300'">
              {{ memberTotals(member.membership_id).excused || '—' }}
            </td>
            <td class="px-3 py-2 text-center font-mono font-semibold"
              :class="memberTotals(member.membership_id).pct !== null
                ? (memberTotals(member.membership_id).pct >= 80 ? 'text-emerald-600' : 'text-red-500')
                : 'text-slate-300'">
              {{ memberTotals(member.membership_id).pct !== null ? memberTotals(member.membership_id).pct + '%' : '—' }}
            </td>
          </tr>
        </tbody>
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
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-slate-500">
      <span v-for="s in STATUSES" :key="s.value" class="inline-flex items-center gap-1.5">
        <span :class="['inline-flex items-center justify-center min-w-[26px] h-5 px-1 rounded-md text-[11px] font-bold', s.cls]">
          {{ s.code }}
        </span>
        {{ s.label }}
      </span>
    </div>
  </template>

  <AppModal
    :show="!!remarkCell"
    title="Attendance Remark"
    :subtitle="remarkCell ? `${remarkCell.member.name} · ${formatDate(remarkCell.date.date)}` : ''"
    size="md"
    @close="closeRemarks">
    <label for="alp-attendance-remark" class="block text-sm font-medium text-slate-700">
      Remarks <span class="font-normal text-slate-400">(optional)</span>
    </label>
    <textarea
      id="alp-attendance-remark"
      v-model="remarkDraft"
      rows="4"
      maxlength="1000"
      :readonly="!canEdit"
      placeholder="Add relevant context for this absence, tardiness, or excuse…"
      class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 read-only:bg-slate-50" />
    <div class="mt-1 flex items-center justify-between text-xs text-slate-400">
      <span>Visible in this ALP's attendance records.</span>
      <span>{{ remarkDraft.length }}/1000</span>
    </div>

    <template #footer>
      <AppButton variant="secondary" @click="closeRemarks">
        {{ canEdit ? 'Cancel' : 'Close' }}
      </AppButton>
      <AppButton v-if="canEdit" @click="applyRemark">
        Apply Remark
      </AppButton>
    </template>
  </AppModal>
</template>
