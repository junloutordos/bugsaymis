<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import Swal from 'sweetalert2'
import {
  ChevronLeftIcon, ChevronRightIcon, ChevronDownIcon, CheckBadgeIcon, EyeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  weekStart:  { type: String, required: true },
  summary:    { type: Array, default: () => [] },
  limits:     { type: Object, required: true },
  schoolYear: { type: Object, default: null },
})

const selectedWeek = ref(props.weekStart)

function reload() {
  router.get(route('class-records.wat.review'), { week: selectedWeek.value }, { preserveScroll: true, preserveState: true })
}

function shiftWeek(deltaDays) {
  const d = new Date(selectedWeek.value + 'T00:00:00')
  d.setDate(d.getDate() + deltaDays)
  selectedWeek.value = d.toISOString().slice(0, 10)
  reload()
}

const weekLabel = computed(() => {
  const start = new Date(props.weekStart + 'T00:00:00')
  const end   = new Date(start)
  end.setDate(start.getDate() + 4)
  const opts = { month: 'long', day: 'numeric', year: 'numeric' }
  return `${start.toLocaleDateString('en-PH', opts)} – ${end.toLocaleDateString('en-PH', opts)}`
})

const reviewForm = useForm({ section_id: null, week_start: null, remarks: '' })

async function markReviewed(row) {
  const { value: remarks, isConfirmed } = await Swal.fire({
    title: `Review WAT — Grade ${row.level} ${row.name}`,
    input: 'textarea',
    inputLabel: 'Remarks (optional)',
    inputValue: row.review?.remarks ?? '',
    showCancelButton: true,
    confirmButtonText: row.review ? 'Update review' : 'Mark as reviewed',
    confirmButtonColor: '#4f46e5',
  })
  if (!isConfirmed) return

  reviewForm.section_id = row.id
  reviewForm.week_start = props.weekStart
  reviewForm.remarks    = remarks ?? ''
  reviewForm.post(route('class-records.wat.review.store'), { preserveScroll: true })
}

function viewSection(row) {
  router.get(route('class-records.wat.index'), { section: row.id, week: props.weekStart })
}

// ── Teacher-level compliance drill-down ─────────────────────────────────────
const expandedSectionId = ref(null)

function toggleTeacherBreakdown(row) {
  expandedSectionId.value = expandedSectionId.value === row.id ? null : row.id
}

const statusMeta = {
  plotted:         { label: 'Plotted',              color: 'green' },
  not_yet_due:      { label: 'Not yet due',           color: 'slate' },
  blocked_by_cap:   { label: 'Blocked — section maxed out', color: 'amber' },
  not_plotted:      { label: 'Not plotted',           color: 'red' },
}

function statusLabel(status) {
  return statusMeta[status]?.label ?? status
}

function statusColor(status) {
  return statusMeta[status]?.color ?? 'slate'
}

function fmtDt(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <Head title="WAT Review" />
  <AdminLayout title="WAT Review — All Sections">
    <div class="max-w-5xl mx-auto space-y-4">

      <AppPageHeader title="WAT Review — All Sections"
        subtitle="Weekly assessment compliance across every section, for the ACIDAA review.">
        <template #actions>
          <AppButton variant="secondary" @click="router.get(route('class-records.wat.index'), { week: selectedWeek })">
            <ChevronLeftIcon class="w-4 h-4" /> Back to Tracker
          </AppButton>
        </template>
      </AppPageHeader>

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
        <div class="flex-1"></div>
        <div class="text-right">
          <p class="text-sm font-semibold text-slate-700">{{ weekLabel }}</p>
          <p class="text-xs text-slate-400">SY {{ schoolYear?.name }} · limits: {{ limits.weekly_graded }} graded / {{ limits.weekly_major }} major per week · {{ limits.daily_graded }} graded / {{ limits.daily_major }} major per day</p>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50/80">
              <tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Section</th>
                <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Graded</th>
                <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Major</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Limit Flags</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Review Status</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <template v-for="row in summary" :key="row.id">
              <tr class="hover:bg-slate-50/40">
                <td class="px-4 py-2.5 font-medium text-slate-800">Grade {{ row.level }} — {{ row.name }}</td>
                <td class="px-4 py-2.5 text-center">
                  <span :class="row.graded_count > limits.weekly_graded ? 'text-danger-600 font-bold' : 'text-slate-600'">
                    {{ row.graded_count }}/{{ limits.weekly_graded }}
                  </span>
                </td>
                <td class="px-4 py-2.5 text-center">
                  <span :class="row.major_count > limits.weekly_major ? 'text-danger-600 font-bold' : 'text-slate-600'">
                    {{ row.major_count }}/{{ limits.weekly_major }}
                  </span>
                </td>
                <td class="px-4 py-2.5">
                  <div class="flex flex-wrap gap-1">
                    <AppBadge v-if="row.over_daily" color="red">Daily limit exceeded</AppBadge>
                    <AppBadge v-if="row.over_weekly" color="red">Weekly limit exceeded</AppBadge>
                    <span v-if="!row.over_daily && !row.over_weekly" class="text-xs text-slate-400">Within limits</span>
                  </div>
                </td>
                <td class="px-4 py-2.5">
                  <div v-if="row.review" class="text-xs">
                    <span class="inline-flex items-center gap-1 text-emerald-600 font-medium">
                      <CheckBadgeIcon class="w-4 h-4" /> Reviewed
                    </span>
                    <p class="text-slate-400">{{ row.review.reviewed_by?.name }}</p>
                  </div>
                  <span v-else class="text-xs text-slate-400">Pending</span>
                </td>
                <td class="px-4 py-2.5">
                  <div class="flex justify-end gap-1.5">
                    <AppButton size="sm" variant="secondary" @click="toggleTeacherBreakdown(row)"
                               :title="expandedSectionId === row.id ? 'Hide teacher breakdown' : 'Show teacher breakdown'">
                      <ChevronDownIcon class="w-4 h-4 transition-transform" :class="{ 'rotate-180': expandedSectionId === row.id }" />
                      Teachers
                    </AppButton>
                    <AppButton size="sm" variant="secondary" @click="viewSection(row)">
                      <EyeIcon class="w-4 h-4" />
                    </AppButton>
                    <AppButton size="sm" :variant="row.review ? 'secondary' : 'primary'" @click="markReviewed(row)">
                      {{ row.review ? 'Update' : 'Review' }}
                    </AppButton>
                  </div>
                </td>
              </tr>

              <!-- Teacher-level plotting compliance drill-down -->
              <tr v-if="expandedSectionId === row.id" class="bg-slate-50/60">
                <td colspan="6" class="px-4 py-4">
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-semibold text-slate-600 uppercase tracking-wide">
                      Teacher Plotting Compliance — Grade {{ row.level }} {{ row.name }}
                    </h4>
                    <p class="text-xs text-slate-400">
                      {{ row.teacher_breakdown.remaining.weekly_graded }} graded / {{ row.teacher_breakdown.remaining.weekly_major }} major slot(s) remaining this week
                    </p>
                  </div>

                  <div v-if="!row.teacher_breakdown.teachers.length" class="text-xs text-slate-400 py-4 text-center">
                    No subject teachers with a teaching load found for this section.
                  </div>

                  <div v-else class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                    <table class="min-w-full text-xs">
                      <thead class="bg-slate-50">
                        <tr>
                          <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">Teacher</th>
                          <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">Subject(s)</th>
                          <th class="px-3 py-2 text-center font-semibold text-slate-500 uppercase tracking-wide">Graded</th>
                          <th class="px-3 py-2 text-center font-semibold text-slate-500 uppercase tracking-wide">Major</th>
                          <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">Last Plotted</th>
                          <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in row.teacher_breakdown.teachers" :key="t.user_id">
                          <td class="px-3 py-2 font-medium text-slate-800">{{ t.teacher_name ?? '—' }}</td>
                          <td class="px-3 py-2 text-slate-600">{{ t.subjects.join(', ') || '—' }}</td>
                          <td class="px-3 py-2 text-center text-slate-600">{{ t.graded_count }}</td>
                          <td class="px-3 py-2 text-center text-slate-600">{{ t.major_count }}</td>
                          <td class="px-3 py-2 text-slate-500">{{ fmtDt(t.last_plotted_at) }}</td>
                          <td class="px-3 py-2">
                            <AppBadge :color="statusColor(t.status)">{{ statusLabel(t.status) }}</AppBadge>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </td>
              </tr>
              </template>
              <tr v-if="!summary.length">
                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">
                  No sections with class records this school year.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
