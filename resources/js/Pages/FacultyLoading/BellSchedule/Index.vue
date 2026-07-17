<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import {
  ClockIcon, PlusIcon, TrashIcon, ArrowUturnLeftIcon, InformationCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  timetables:    { type: Array, default: () => [] },
  types:         { type: Array, default: () => [] },
  teachingTypes: { type: Array, default: () => [] },
})

// Local editable copy, keyed by timetable key.
const tables = ref(props.timetables.map(t => ({
  ...t,
  rows: t.rows.map(r => ({ ...r })),
})))

const activeKey = ref(tables.value[0]?.key ?? null)
const active = computed(() => tables.value.find(t => t.key === activeKey.value) ?? null)
const saving = ref(false)

const isTeaching = (type) => props.teachingTypes.includes(type)

function typeChipClass(type) {
  if (isTeaching(type)) return 'bg-indigo-50 text-indigo-700 border-indigo-200'
  return {
    FLAG:     'bg-rose-50 text-rose-700 border-rose-200',
    HOMEROOM: 'bg-violet-50 text-violet-700 border-violet-200',
    ADVISING: 'bg-violet-50 text-violet-700 border-violet-200',
    RECESS:   'bg-amber-50 text-amber-700 border-amber-200',
    LUNCH:    'bg-emerald-50 text-emerald-700 border-emerald-200',
    CONSULT:  'bg-sky-50 text-sky-700 border-sky-200',
    ACTIVITY: 'bg-teal-50 text-teal-700 border-teal-200',
    WELLNESS: 'bg-teal-50 text-teal-700 border-teal-200',
  }[type] ?? 'bg-slate-50 text-slate-600 border-slate-200'
}

// Client-side overlap detection for live feedback (server re-validates).
const overlaps = computed(() => {
  if (!active.value) return []
  const rows = [...active.value.rows]
    .map((r, i) => ({ ...r, i }))
    .sort((a, b) => (a.start || '').localeCompare(b.start || ''))
  const bad = new Set()
  for (let k = 1; k < rows.length; k++) {
    if (rows[k].start && rows[k - 1].end && rows[k].start < rows[k - 1].end) {
      bad.add(rows[k].i); bad.add(rows[k - 1].i)
    }
  }
  return bad
})

const hasError = computed(() => {
  if (!active.value) return true
  return overlaps.value.size > 0
    || active.value.rows.some(r => !r.start || !r.end || r.end <= r.start || !r.label?.trim())
})

function addRow() {
  const rows = active.value.rows
  const lastEnd = rows.length ? rows[rows.length - 1].end : '07:30'
  rows.push({ start: lastEnd, end: lastEnd, type: 'CLASS', label: 'Class' })
}

function removeRow(idx) {
  active.value.rows.splice(idx, 1)
}

function sortRows() {
  active.value.rows.sort((a, b) => (a.start || '').localeCompare(b.start || ''))
}

async function save() {
  if (hasError.value || saving.value) return
  saving.value = true
  try {
    sortRows()
    const { data } = await axios.post(route('faculty-loading.bell-schedule.update'), {
      timetable_key: active.value.key,
      rows: active.value.rows,
    })
    active.value.is_overridden = true
    await Swal.fire({ icon: 'success', title: 'Saved', text: data.message, timer: 1600, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Could not save', err.response?.data?.message ?? 'Please review the blocks and try again.', 'error')
  } finally {
    saving.value = false
  }
}

async function resetToDefault() {
  const confirmed = await Swal.fire({
    title: 'Reset to built-in schedule?',
    text: `This discards custom edits for "${active.value.label}" and restores the original times.`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Reset',
  })
  if (!confirmed.isConfirmed) return
  try {
    const { data } = await axios.post(route('faculty-loading.bell-schedule.reset'), {
      timetable_key: active.value.key,
    })
    active.value.rows = data.rows.map(r => ({ ...r }))
    active.value.is_overridden = false
    await Swal.fire({ icon: 'success', title: 'Reset', text: data.message, timer: 1400, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Reset failed.', 'error')
  }
}
</script>

<template>
  <Head title="Bell Schedule" />
  <AdminLayout title="Bell Schedule">
    <div class="space-y-5">

      <AppPageHeader title="Bell Schedule"
        subtitle="Edit the fixed daily blocks — flag ceremony, recess, lunch, consultation / home bound, homeroom — per grade group and day pattern." />

      <div class="flex items-start gap-3 rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-800">
        <InformationCircleIcon class="h-5 w-5 mt-0.5 shrink-0 text-sky-500" />
        <p>
          Changes apply to <strong>future schedule generation and the calendar’s blocked bands only</strong> —
          schedules already plotted are never moved. Blocks marked <strong>Class</strong> are teachable periods;
          everything else is time the scheduler keeps free.
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
        <!-- Timetable selector -->
        <AppCard :padded="false" class="lg:col-span-1 overflow-hidden self-start">
          <div class="px-4 py-3 border-b border-slate-100">
            <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Timetables</h2>
          </div>
          <nav class="divide-y divide-slate-50">
            <button v-for="t in tables" :key="t.key" type="button" @click="activeKey = t.key"
              :class="['w-full flex items-center justify-between gap-2 px-4 py-2.5 text-left text-sm transition-colors',
                activeKey === t.key ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50']">
              {{ t.label }}
              <AppBadge v-if="t.is_overridden" color="amber">Edited</AppBadge>
            </button>
          </nav>
        </AppCard>

        <!-- Editor -->
        <AppCard v-if="active" :padded="false" class="lg:col-span-3 overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                <ClockIcon class="h-4 w-4 text-indigo-500" /> {{ active.label }}
              </h2>
              <p class="text-xs text-slate-500 mt-0.5">
                {{ active.rows.length }} blocks · times are 24-hour (HH:MM)
              </p>
            </div>
            <div class="flex items-center gap-2">
              <AppButton variant="secondary" size="sm" :disabled="!active.is_overridden" @click="resetToDefault">
                <ArrowUturnLeftIcon class="h-3.5 w-3.5" /> Reset to default
              </AppButton>
              <AppButton size="sm" :loading="saving" :disabled="hasError || saving" @click="save">
                Save changes
              </AppButton>
            </div>
          </div>

          <div v-if="overlaps.size" class="px-5 py-2 bg-red-50 border-b border-red-100 text-xs text-red-600">
            Some blocks overlap — adjust the highlighted times before saving.
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
                  <th class="px-4 py-2.5 text-left w-28">Start</th>
                  <th class="px-4 py-2.5 text-left w-28">End</th>
                  <th class="px-4 py-2.5 text-left w-40">Type</th>
                  <th class="px-4 py-2.5 text-left">Label</th>
                  <th class="px-2 py-2.5 w-10"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="(row, idx) in active.rows" :key="idx"
                  :class="overlaps.has(idx) ? 'bg-red-50/50' : 'hover:bg-slate-50/50'">
                  <td class="px-4 py-2">
                    <input v-model="row.start" type="time"
                      class="rounded-lg border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </td>
                  <td class="px-4 py-2">
                    <input v-model="row.end" type="time"
                      :class="['rounded-lg border px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500',
                        row.end && row.end <= row.start ? 'border-red-300' : 'border-slate-200']" />
                  </td>
                  <td class="px-4 py-2">
                    <select v-model="row.type"
                      :class="['rounded-lg border px-2 py-1 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500', typeChipClass(row.type)]">
                      <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                    </select>
                  </td>
                  <td class="px-4 py-2">
                    <input v-model="row.label" type="text" maxlength="60" placeholder="e.g. Recess"
                      class="w-full rounded-lg border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </td>
                  <td class="px-2 py-2 text-center">
                    <button type="button" @click="removeRow(idx)"
                      class="p-1 rounded hover:bg-red-50 text-slate-300 hover:text-red-500" title="Remove block">
                      <TrashIcon class="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="px-5 py-3 border-t border-slate-100">
            <button type="button" @click="addRow"
              class="inline-flex items-center gap-1.5 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
              <PlusIcon class="h-3.5 w-3.5" /> Add block
            </button>
          </div>
        </AppCard>
      </div>
    </div>
  </AdminLayout>
</template>
