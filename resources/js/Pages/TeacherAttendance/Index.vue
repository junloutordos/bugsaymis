<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppTable from '@/Components/AppTable.vue'

const props = defineProps({
  todaySchedules: Array,
  history:        Object,
  classrooms:     Array,
  teachers:       Array,
  filters:        Object,
  scopedUnit:     Object,
})

const activeTab = ref('today')

// ── Filters ──────────────────────────────────────────────────────────────────
const filterDate       = ref(props.filters?.date       ?? '')
const filterTeacherId  = ref(props.filters?.teacher_id  ? Number(props.filters.teacher_id) : '')
const filterClassroom  = ref(props.filters?.classroom_id ? Number(props.filters.classroom_id) : '')
const filterStatus     = ref(props.filters?.status     ?? 'all')

function applyFilters() {
  router.get(route('teacher-attendance.index'), {
    date:         filterDate.value      || undefined,
    teacher_id:   filterTeacherId.value || undefined,
    classroom_id: filterClassroom.value || undefined,
    status:       filterStatus.value !== 'all' ? filterStatus.value : undefined,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  filterDate.value      = ''
  filterTeacherId.value = ''
  filterClassroom.value = ''
  filterStatus.value    = 'all'
  applyFilters()
}

// ── Today stats ───────────────────────────────────────────────────────────────
const todayStats = computed(() => {
  const s = (props.todaySchedules ?? []).filter(r => r.tap_status !== 'not_held')
  return {
    total:    s.length,
    onTime:   s.filter(r => r.tap_status === 'on_time').length,
    late:     s.filter(r => r.tap_status === 'late').length,
    noTap:    s.filter(r => r.tap_status === 'no_tap').length,
    absent:   s.filter(r => r.tap_status === 'absent').length,
    upcoming: s.filter(r => r.tap_status === 'upcoming').length,
  }
})

// ── Status helpers ────────────────────────────────────────────────────────────
const STATUS_CONFIG = {
  on_time:   { label: 'On Time',            bg: 'bg-emerald-100', text: 'text-emerald-700', dot: 'bg-emerald-500' },
  late:      { label: 'Late',               bg: 'bg-amber-100',   text: 'text-amber-700',   dot: 'bg-amber-500'   },
  no_tap:    { label: 'No Tap',             bg: 'bg-red-100',     text: 'text-red-700',     dot: 'bg-red-500'     },
  absent:    { label: 'Absent',             bg: 'bg-red-100',     text: 'text-red-700',     dot: 'bg-red-600'     },
  upcoming:  { label: 'Upcoming',           bg: 'bg-slate-100',   text: 'text-slate-600',   dot: 'bg-slate-400'   },
  no_match:  { label: 'No Match',           bg: 'bg-slate-100',   text: 'text-slate-600',   dot: 'bg-slate-400'   },
  not_held:  { label: 'Not Held — Adjusted', bg: 'bg-indigo-100', text: 'text-indigo-700',  dot: 'bg-indigo-400'  },
}

function statusBadge(status) {
  return STATUS_CONFIG[status] ?? { label: status, bg: 'bg-slate-100', text: 'text-slate-600', dot: 'bg-slate-400' }
}

function formatTime(t) {
  if (!t) return '—'
  const [h, m] = t.split(':')
  const hour   = parseInt(h)
  const ampm   = hour >= 12 ? 'PM' : 'AM'
  const h12    = hour % 12 || 12
  return `${h12}:${m} ${ampm}`
}

function exportHistory() {
  const params = new URLSearchParams()
  if (filterDate.value)      params.set('date',         filterDate.value)
  if (filterTeacherId.value) params.set('teacher_id',   filterTeacherId.value)
  if (filterClassroom.value) params.set('classroom_id', filterClassroom.value)
  if (filterStatus.value !== 'all') params.set('status', filterStatus.value)
  window.location.href = route('teacher-attendance.export') + '?' + params.toString()
}
</script>

<template>
  <Head title="Teacher Attendance" />
  <AdminLayout title="Teacher Attendance">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Teacher Attendance</h1>
        <p v-if="scopedUnit" class="text-sm text-slate-500 mt-0.5">
          Academic Unit: <span class="font-medium text-slate-700">{{ scopedUnit.name }}</span>
        </p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 border-b border-slate-200">
      <button
        v-for="tab in ['today', 'history']"
        :key="tab"
        @click="activeTab = tab"
        :class="[
          'px-4 py-2 text-sm font-medium capitalize transition-colors',
          activeTab === tab
            ? 'border-b-2 border-indigo-600 text-indigo-600'
            : 'text-slate-500 hover:text-slate-700',
        ]"
      >
        {{ tab === 'today' ? "Today's Classes" : 'History' }}
      </button>
    </div>

    <!-- ── TODAY TAB ────────────────────────────────────────────────────────── -->
    <template v-if="activeTab === 'today'">

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        <AppCard class="p-4 text-center">
          <p class="text-2xl font-bold text-slate-800">{{ todayStats.total }}</p>
          <p class="text-xs text-slate-500 mt-1">Total Classes</p>
        </AppCard>
        <AppCard class="p-4 text-center">
          <p class="text-2xl font-bold text-emerald-600">{{ todayStats.onTime }}</p>
          <p class="text-xs text-slate-500 mt-1">On Time</p>
        </AppCard>
        <AppCard class="p-4 text-center">
          <p class="text-2xl font-bold text-amber-500">{{ todayStats.late }}</p>
          <p class="text-xs text-slate-500 mt-1">Late</p>
        </AppCard>
        <AppCard class="p-4 text-center">
          <p class="text-2xl font-bold text-red-500">{{ todayStats.noTap + todayStats.absent }}</p>
          <p class="text-xs text-slate-500 mt-1">No Tap / Absent</p>
        </AppCard>
        <AppCard class="p-4 text-center">
          <p class="text-2xl font-bold text-slate-400">{{ todayStats.upcoming }}</p>
          <p class="text-xs text-slate-500 mt-1">Upcoming</p>
        </AppCard>
      </div>

      <!-- Today schedule grid -->
      <AppCard>
        <div v-if="todaySchedules.length === 0" class="py-16 text-center text-slate-400 text-sm">
          No classes scheduled for today.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                <th class="text-left py-3 px-4">Time</th>
                <th class="text-left py-3 px-4">Teacher</th>
                <th class="text-left py-3 px-4">Subject</th>
                <th class="text-left py-3 px-4">Section</th>
                <th class="text-left py-3 px-4">Room</th>
                <th class="text-left py-3 px-4">Status</th>
                <th class="text-left py-3 px-4">Tapped At</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in todaySchedules"
                :key="row.id"
                class="border-b border-slate-50 hover:bg-slate-50 transition-colors"
              >
                <td class="py-3 px-4 whitespace-nowrap text-slate-600 font-mono text-xs">
                  {{ formatTime(row.start_time) }}<br>
                  <span class="text-slate-400">{{ formatTime(row.end_time) }}</span>
                  <span v-if="row.is_adjusted_day" class="block mt-1 text-indigo-600 font-sans not-italic">Adjusted</span>
                </td>
                <td class="py-3 px-4 font-medium text-slate-800">{{ row.faculty.name }}</td>
                <td class="py-3 px-4">
                  <span class="text-slate-800">{{ row.subject.name }}</span>
                  <span class="text-slate-400 text-xs ml-1">{{ row.subject.code }}</span>
                </td>
                <td class="py-3 px-4 text-slate-600">{{ row.section.name }}</td>
                <td class="py-3 px-4 text-slate-600">{{ row.classroom.code }}</td>
                <td class="py-3 px-4">
                  <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(row.tap_status).bg, statusBadge(row.tap_status).text]">
                    <span :class="['w-1.5 h-1.5 rounded-full', statusBadge(row.tap_status).dot]" />
                    {{ statusBadge(row.tap_status).label }}
                  </span>
                </td>
                <td class="py-3 px-4 text-slate-500 font-mono text-xs">
                  {{ row.tapped_at ?? '—' }}
                  <span v-if="row.late_minutes > 0" class="text-amber-500 ml-1">+{{ row.late_minutes }}m</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

    </template>

    <!-- ── HISTORY TAB ──────────────────────────────────────────────────────── -->
    <template v-else>

      <!-- Filters -->
      <AppCard class="mb-4">
        <div class="flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs text-slate-500 mb-1 font-medium">Date</label>
            <input
              v-model="filterDate"
              type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-xs text-slate-500 mb-1 font-medium">Teacher</label>
            <select
              v-model="filterTeacherId"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">All Teachers</option>
              <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-slate-500 mb-1 font-medium">Room</label>
            <select
              v-model="filterClassroom"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">All Rooms</option>
              <option v-for="c in classrooms" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-slate-500 mb-1 font-medium">Status</label>
            <select
              v-model="filterStatus"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="all">All</option>
              <option value="on_time">On Time</option>
              <option value="late">Late</option>
              <option value="no_match">No Match</option>
            </select>
          </div>

          <div class="flex gap-2">
            <button
              @click="applyFilters"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
            >
              Filter
            </button>
            <button
              @click="clearFilters"
              class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium"
            >
              Clear
            </button>
            <button
              @click="exportHistory"
              class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
            >
              Export Excel
            </button>
          </div>
        </div>
      </AppCard>

      <!-- History table -->
      <AppCard>
        <div v-if="history.data.length === 0" class="py-16 text-center text-slate-400 text-sm">
          No tap records found for the selected filters.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                <th class="text-left py-3 px-4">Tapped At</th>
                <th class="text-left py-3 px-4">Teacher</th>
                <th class="text-left py-3 px-4">Room</th>
                <th class="text-left py-3 px-4">Subject</th>
                <th class="text-left py-3 px-4">Section</th>
                <th class="text-left py-3 px-4">Status</th>
                <th class="text-left py-3 px-4">Late</th>
                <th class="text-left py-3 px-4">Channel</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="log in history.data"
                :key="log.id"
                class="border-b border-slate-50 hover:bg-slate-50"
              >
                <td class="py-3 px-4 font-mono text-xs text-slate-600 whitespace-nowrap">
                  {{ log.tapped_at }}
                </td>
                <td class="py-3 px-4 font-medium text-slate-800">{{ log.teacher?.name ?? '—' }}</td>
                <td class="py-3 px-4 text-slate-600">{{ log.classroom?.code ?? '—' }}</td>
                <td class="py-3 px-4 text-slate-600">{{ log.class_schedule?.subject?.name ?? '—' }}</td>
                <td class="py-3 px-4 text-slate-600">{{ log.class_schedule?.section?.name ?? '—' }}</td>
                <td class="py-3 px-4">
                  <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(log.status).bg, statusBadge(log.status).text]">
                    <span :class="['w-1.5 h-1.5 rounded-full', statusBadge(log.status).dot]" />
                    {{ statusBadge(log.status).label }}
                  </span>
                </td>
                <td class="py-3 px-4 text-amber-600 text-xs">
                  {{ log.late_minutes > 0 ? `+${log.late_minutes}m` : '—' }}
                </td>
                <td class="py-3 px-4">
                  <span
                    :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                      log.channel === 'qr' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600']"
                  >
                    {{ log.channel === 'qr' ? 'QR' : 'NFC' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="history.last_page > 1" class="flex justify-between items-center px-4 py-3 border-t border-slate-100 text-sm text-slate-500">
          <span>
            Showing {{ history.from }}–{{ history.to }} of {{ history.total }}
          </span>
          <div class="flex gap-2">
            <a
              v-for="link in history.links"
              :key="link.label"
              :href="link.url"
              v-html="link.label"
              :class="[
                'px-3 py-1 rounded text-sm',
                link.active
                  ? 'bg-indigo-600 text-white font-medium'
                  : 'text-slate-600 hover:bg-slate-100',
                !link.url ? 'opacity-40 pointer-events-none' : '',
              ]"
            />
          </div>
        </div>
      </AppCard>

    </template>

  </AdminLayout>
</template>
