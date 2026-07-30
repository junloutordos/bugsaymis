<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { ChevronLeftIcon, ChevronRightIcon, ExclamationTriangleIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline'
import { WAT_LIMITS } from '@/Utils/ClassRecord/watUtils.js'

const props = defineProps({
  weekStart:   { type: String, required: true },
  tracker:     { type: Object, default: null },
  schoolYear:  { type: Object, default: null },
})

const selectedWeek = ref(props.weekStart)

function reload() {
  router.get(route('class-records.wat.my-tracker'), { week: selectedWeek.value }, { preserveScroll: true, preserveState: true })
}

function shiftWeek(deltaDays) {
  const d = new Date(selectedWeek.value + 'T00:00:00')
  d.setDate(d.getDate() + deltaDays)
  selectedWeek.value = d.toISOString().slice(0, 10)
  reload()
}

function dayName(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'long', month: 'short', day: 'numeric' })
}

const weekLabel = computed(() => {
  if (!props.tracker) return ''
  const opts = { month: 'long', day: 'numeric', year: 'numeric' }
  return `${new Date(props.tracker.week_start + 'T00:00:00').toLocaleDateString('en-PH', opts)} – ${new Date(props.tracker.week_end + 'T00:00:00').toLocaleDateString('en-PH', opts)}`
})

// Friday-before-the-week 12NN plotting deadline, mirrors WatRuleService::plottingDeadline()
const plottingDeadline = computed(() => {
  if (!props.tracker) return null
  const monday = new Date(props.tracker.week_start + 'T00:00:00')
  const friday = new Date(monday)
  friday.setDate(monday.getDate() - 3)
  friday.setHours(12, 0, 0, 0)
  return friday
})
const deadlinePassed = computed(() => plottingDeadline.value ? new Date() > plottingDeadline.value : false)

const underplannedSections = computed(() =>
  (props.tracker?.sections ?? []).filter(s => s.graded_count === 0)
)

function remainingColor(remaining) {
  if (remaining <= 0) return 'red'
  if (remaining <= 2) return 'amber'
  return 'green'
}
</script>

<template>
  <Head title="My WAT Tracker" />
  <AdminLayout title="My WAT Tracker">
    <div class="max-w-6xl mx-auto space-y-4">

      <AppPageHeader title="Individual Faculty WAT Tracker"
        subtitle="Your own plotted assessments across every section and subject you teach, for the selected week." />

      <!-- Controls -->
      <div class="bg-white rounded-xl border border-slate-100 p-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Week of</label>
          <div class="flex items-center gap-1">
            <button @click="shiftWeek(-7)" class="p-2 rounded-lg hover:bg-slate-100" title="Previous week">
              <ChevronLeftIcon class="w-4 h-4 text-slate-500" />
            </button>
            <input v-model="selectedWeek" type="date" @change="reload"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <button @click="shiftWeek(7)" class="p-2 rounded-lg hover:bg-slate-100" title="Next week">
              <ChevronRightIcon class="w-4 h-4 text-slate-500" />
            </button>
          </div>
        </div>
      </div>

      <template v-if="tracker && tracker.sections.length">
        <!-- Week summary -->
        <div class="bg-white rounded-xl border border-slate-100 p-4">
          <div class="flex flex-wrap items-center gap-3">
            <div>
              <h2 class="text-sm font-semibold text-slate-800">{{ weekLabel }}</h2>
              <p class="text-xs text-slate-500">SY {{ schoolYear?.name }}</p>
            </div>
            <div class="flex-1"></div>
            <AppBadge color="slate">{{ tracker.totals.graded }} graded plotted this week</AppBadge>
            <AppBadge color="slate">{{ tracker.totals.major }} major plotted this week</AppBadge>
          </div>

          <div v-if="deadlinePassed && underplannedSections.length" class="mt-3 flex items-center gap-2 bg-amber-50 border border-amber-100 text-amber-700 rounded-lg px-3 py-2 text-xs">
            <ExclamationTriangleIcon class="w-4 h-4 shrink-0" />
            The plotting deadline for this week has passed and you have nothing plotted yet for:
            <span class="font-semibold">{{ underplannedSections.map(s => `Grade ${s.grade} — ${s.section_name}`).join(', ') }}</span>.
          </div>
          <div v-else-if="!deadlinePassed && underplannedSections.length" class="mt-3 flex items-center gap-2 bg-slate-50 border border-slate-100 text-slate-500 rounded-lg px-3 py-2 text-xs">
            <CalendarDaysIcon class="w-4 h-4 shrink-0" />
            Plotting deadline: {{ plottingDeadline?.toLocaleDateString('en-PH', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' }) }} at 12:00 NN.
            You have nothing plotted yet for:
            <span class="font-semibold">{{ underplannedSections.map(s => `Grade ${s.grade} — ${s.section_name}`).join(', ') }}</span>.
          </div>
        </div>

        <!-- Per-section room remaining -->
        <div class="bg-white rounded-xl border border-slate-100 p-4">
          <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Room Left Per Section (shared with co-teachers)</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
            <div v-for="s in tracker.sections" :key="s.section_id" class="rounded-lg border border-slate-100 p-3">
              <p class="text-sm font-semibold text-slate-800">Grade {{ s.grade }} — {{ s.section_name }}</p>
              <p class="text-xs text-slate-400 mb-2">{{ s.subjects.join(', ') }}</p>
              <div class="flex flex-wrap gap-1.5">
                <AppBadge :color="remainingColor(s.remaining.weekly_graded)">
                  {{ s.remaining.weekly_graded }} graded slot(s) left
                </AppBadge>
                <AppBadge :color="remainingColor(s.remaining.weekly_major)">
                  {{ s.remaining.weekly_major }} major slot(s) left
                </AppBadge>
              </div>
              <p class="text-[11px] text-slate-400 mt-1.5">
                You've plotted {{ s.graded_count }} graded ({{ s.major_count }} major) here this week ·
                section total {{ s.section_totals.graded }}/{{ WAT_LIMITS.weeklyGraded }}
              </p>
            </div>
          </div>
        </div>

        <!-- Day-by-day grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
          <div v-for="day in tracker.days" :key="day.date" class="bg-white rounded-xl border border-slate-100 p-3 flex flex-col">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-xs font-semibold text-slate-700">{{ dayName(day.date) }}</h3>
              <span class="text-[10px] font-bold text-slate-400">
                {{ day.graded_count }} graded · {{ day.major_count }} major
              </span>
            </div>

            <div v-if="!day.items.length" class="flex-1 flex items-center justify-center text-[11px] text-slate-300 py-6">
              Nothing plotted
            </div>

            <div v-else class="space-y-2">
              <div v-for="item in day.items" :key="`${item.section_id}-${item.id}`" class="rounded-lg border border-slate-100 bg-slate-50/60 p-2">
                <p class="text-xs font-semibold text-slate-800 leading-tight">{{ item.subject_name }}</p>
                <p class="text-[11px] text-slate-600 leading-tight mt-0.5">{{ item.title }}</p>
                <div class="flex flex-wrap gap-1 mt-1">
                  <span class="px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-medium">{{ item.type_label }}</span>
                  <span v-if="item.is_major" class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-semibold uppercase tracking-wide">Major</span>
                  <span v-if="!item.is_graded" class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px] font-semibold uppercase tracking-wide">Non-graded</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Grade {{ item.grade }} — {{ item.section_name }}</p>
              </div>
            </div>
          </div>
        </div>
      </template>

      <div v-else class="bg-white rounded-xl border border-slate-100 p-10 text-center text-sm text-slate-400">
        You have no class records this school year to track.
      </div>
    </div>
  </AdminLayout>
</template>
