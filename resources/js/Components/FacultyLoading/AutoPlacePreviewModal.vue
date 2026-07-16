<template>
  <AppModal :show="show" title="Auto-Place Remaining" size="xl"
    body-class="px-6 py-4 space-y-4" @close="$emit('close')">

    <!-- Summary strip -->
    <div v-if="summary" class="flex flex-wrap items-center gap-2 text-sm">
      <span class="inline-flex items-center gap-1.5 bg-indigo-50 text-indigo-700 border border-indigo-100 rounded-lg px-3 py-1.5 font-medium">
        <CheckCircleIcon class="h-4 w-4" /> {{ proposals.length }} session(s) placeable
      </span>
      <span v-if="failedSessions > 0"
        class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 border border-amber-100 rounded-lg px-3 py-1.5 font-medium">
        <ExclamationTriangleIcon class="h-4 w-4" /> {{ failedSessions }} session(s) cannot be placed
      </span>
    </div>

    <!-- Commit conflicts (calendar changed between preview and save) -->
    <div v-if="commitErrors.length" class="space-y-1.5">
      <div v-for="err in commitErrors" :key="err"
        class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-3 py-2 text-xs flex items-start gap-1.5">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0 mt-0.5" /> {{ err }}
      </div>
      <button type="button" class="text-sm text-indigo-600 hover:underline font-medium" @click="$emit('refresh')">
        Refresh preview
      </button>
    </div>

    <!-- Proposed placements -->
    <div v-if="proposals.length" class="border border-slate-200 rounded-lg overflow-hidden">
      <div class="max-h-72 overflow-y-auto overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 sticky top-0">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Subject</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Section</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Faculty</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Schedule</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="(p, i) in proposals" :key="i" class="hover:bg-slate-50/60">
              <td class="px-3 py-2">
                <span class="font-semibold text-slate-800">{{ p._subject_code }}</span>
                <span v-if="p.session_type === 'ilp'"
                  class="ml-1.5 inline-flex text-[10px] font-bold uppercase bg-violet-100 text-violet-700 rounded px-1.5 py-0.5">ILP</span>
              </td>
              <td class="px-3 py-2 text-slate-600">{{ p._section_name }}</td>
              <td class="px-3 py-2 text-slate-600">{{ p._faculty_name }}</td>
              <td class="px-3 py-2 text-slate-600 whitespace-nowrap">
                {{ p.day_of_week }} · {{ fmtTime(p.start_time) }}–{{ fmtTime(p.end_time) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <p v-else class="text-sm text-slate-500">
      No sessions could be auto-placed — see the reasons below.
    </p>

    <!-- Failures with reasons -->
    <div v-if="failures.length" class="space-y-1.5">
      <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Cannot be placed</p>
      <div v-for="f in failures" :key="f.load_assignment_id"
        class="flex items-start justify-between gap-3 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
        <div class="text-xs text-amber-800">
          <p class="font-semibold">
            {{ f.subject_code }} · {{ f.section_name }} · {{ f.faculty_name }}
            <span class="font-normal">({{ f.sessions_unplaced }} session(s))</span>
          </p>
          <p class="mt-0.5">{{ f.reason }}</p>
        </div>
        <AppButton v-if="f.can_reassign" variant="secondary" size="sm" class="shrink-0"
          @click="$emit('reassign', f)">
          <ArrowsRightLeftIcon class="h-3.5 w-3.5" /> Reassign
        </AppButton>
      </div>
    </div>

    <template #footer>
      <AppButton variant="secondary" @click="$emit('close')">Cancel</AppButton>
      <AppButton :loading="committing" :disabled="proposals.length === 0 || committing"
        @click="$emit('confirm')">
        Save {{ proposals.length }} as Tentative
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue'
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'
import {
  ArrowsRightLeftIcon, CheckCircleIcon, ExclamationCircleIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show:         { type: Boolean, default: false },
  proposals:    { type: Array,   default: () => [] },
  failures:     { type: Array,   default: () => [] },
  summary:      { type: Object,  default: null },
  committing:   { type: Boolean, default: false },
  commitErrors: { type: Array,   default: () => [] },
})

defineEmits(['confirm', 'close', 'reassign', 'refresh'])

const failedSessions = computed(() =>
  props.failures.reduce((sum, f) => sum + (f.sessions_unplaced ?? 0), 0))

function fmtTime(t) {
  if (!t) return '—'
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  return `${hour % 12 || 12}:${m} ${hour >= 12 ? 'PM' : 'AM'}`
}
</script>
