<script setup>
import { ref, computed, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import axios from 'axios'
import {
  CalendarDaysIcon,
  ClipboardDocumentCheckIcon,
  ExclamationTriangleIcon,
  UserGroupIcon,
  DocumentTextIcon,
  XMarkIcon,
  PlusIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  PencilIcon,
  TrashIcon,
  EyeIcon,
} from '@heroicons/vue/24/outline'
import { Bar, Line, Doughnut } from 'vue-chartjs'
import { BRAND, STATUS, gradientFill } from '@/Utils/chartTheme'
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, BarElement, LineElement,
  PointElement, ArcElement, Tooltip, Legend,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, ArcElement, Tooltip, Legend)

const props = defineProps({
  schoolYear:     Object,
  calendarEvents: Array,
  todayEvents:    Array,
  cards:          Object,
  charts:         Object,
  sections:       Array,
  subjects:       Array,
  currentMonth:   Number,
  currentYear:    Number,
})

// ── Calendar state ──────────────────────────────────────────────────────────

const calMonth = ref(props.currentMonth)
const calYear  = ref(props.currentYear)
const events   = ref([...props.calendarEvents])
const loading  = ref(false)

const MONTH_NAMES = [
  'January','February','March','April','May','June',
  'July','August','September','October','November','December',
]

// CID-created activities (meeting/event/training/other)
const CID_TYPE_COLORS = {
  meeting:  { bg: 'bg-blue-100',   text: 'text-blue-700',   dot: 'bg-blue-500'  },
  event:    { bg: 'bg-green-100',  text: 'text-green-700',  dot: 'bg-green-500' },
  training: { bg: 'bg-amber-100',  text: 'text-amber-700',  dot: 'bg-amber-500' },
  other:    { bg: 'bg-slate-100',  text: 'text-slate-600',  dot: 'bg-slate-400' },
}

function eventStyle(ev) {
  if (ev.source === 'class_record') {
    return { bg: 'bg-red-50 border border-red-300', text: 'text-red-700', dot: 'bg-red-400' }
  }
  return CID_TYPE_COLORS[ev.type] ?? CID_TYPE_COLORS.other
}

// AppBadge color mapping for the day-detail panel's event-type badge
const TYPE_BADGE_COLOR = { meeting: 'blue', event: 'green', training: 'amber', other: 'slate' }
function badgeColor(ev) {
  if (ev.source === 'class_record') return 'red'
  return TYPE_BADGE_COLOR[ev.type] ?? 'slate'
}

function formatDate(d) {
  const y   = d.getFullYear()
  const m   = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const todayStr = formatDate(new Date())

const calendarDays = computed(() => {
  const first    = new Date(calYear.value, calMonth.value - 1, 1)
  const last     = new Date(calYear.value, calMonth.value, 0)
  const startDow = first.getDay()
  const days     = []

  for (let i = 0; i < startDow; i++) {
    const d = new Date(calYear.value, calMonth.value - 1, -startDow + i + 1)
    days.push({ date: formatDate(d), day: d.getDate(), current: false, events: [] })
  }
  for (let d = 1; d <= last.getDate(); d++) {
    const date = formatDate(new Date(calYear.value, calMonth.value - 1, d))
    days.push({ date, day: d, current: true, events: events.value.filter(e => e.scheduled_date === date) })
  }
  const remainder = 42 - days.length
  for (let i = 1; i <= remainder; i++) {
    const d = new Date(calYear.value, calMonth.value, i)
    days.push({ date: formatDate(d), day: i, current: false, events: [] })
  }
  return days
})

function dayHasMaxedSection(dayEvents) {
  const bySec = {}
  dayEvents.forEach(e => {
    if (e.source === 'class_record' && e.section_id) {
      bySec[e.section_id] = (bySec[e.section_id] || 0) + 1
    }
  })
  return Object.values(bySec).some(c => c >= 3)
}

async function navigateMonth(delta) {
  let m = calMonth.value + delta
  let y = calYear.value
  if (m < 1)  { m = 12; y-- }
  if (m > 12) { m = 1;  y++ }
  calMonth.value = m
  calYear.value  = y
  loading.value  = true
  try {
    const { data } = await axios.get(route('cid.dashboard.events'), { params: { month: m, year: y } })
    events.value = data
  } finally {
    loading.value = false
  }
}

// ── Create / edit modal (CID activities only — no assessment type) ────────────

const showModal       = ref(false)
const editingSchedule = ref(null)
const selectedDate    = ref('')
const form            = ref(emptyForm())
const formErrors      = ref({})
const saving          = ref(false)

function emptyForm() {
  return {
    title: '', type: 'meeting', scheduled_date: '',
    section_id: '', subject_id: '', start_time: '', end_time: '', description: '',
  }
}

function openCreate(date = '') {
  editingSchedule.value = null
  formErrors.value      = {}
  form.value            = emptyForm()
  form.value.scheduled_date = date
  selectedDate.value    = date
  showModal.value       = true
}

function openEdit(event) {
  editingSchedule.value = event
  formErrors.value      = {}
  form.value = {
    title:          event.title,
    type:           event.type,
    scheduled_date: event.scheduled_date,
    section_id:     event.section_id ?? '',
    subject_id:     event.subject_id ?? '',
    start_time:     event.start_time ?? '',
    end_time:       event.end_time ?? '',
    description:    event.description ?? '',
  }
  selectedDate.value = event.scheduled_date
  showModal.value    = true
}

function closeModal() {
  showModal.value       = false
  editingSchedule.value = null
}

async function saveSchedule() {
  formErrors.value = {}
  saving.value     = true
  try {
    const payload = {
      ...form.value,
      section_id: form.value.section_id || null,
      subject_id: form.value.subject_id || null,
    }
    if (editingSchedule.value) {
      const { data } = await axios.put(route('cid.dashboard.update', editingSchedule.value.id), payload)
      const idx = events.value.findIndex(e => e.id === data.schedule.id)
      if (idx !== -1) events.value.splice(idx, 1, data.schedule)
    } else {
      const { data } = await axios.post(route('cid.dashboard.store'), payload)
      const prefix = `${calYear.value}-${String(calMonth.value).padStart(2, '0')}`
      if (data.schedule.scheduled_date.startsWith(prefix)) {
        events.value.push(data.schedule)
      }
    }
    closeModal()
  } catch (err) {
    if (err.response?.status === 422) {
      formErrors.value = err.response.data.errors ?? { _general: err.response.data.message }
    }
  } finally {
    saving.value = false
  }
}

// ── Delete ───────────────────────────────────────────────────────────────────

async function deleteSchedule(event) {
  if (!confirm(`Delete "${event.title}"?`)) return
  await axios.delete(route('cid.dashboard.destroy', event.id))
  events.value = events.value.filter(e => e.id !== event.id)
}

// ── Day detail panel ─────────────────────────────────────────────────────────

const selectedDayEvents = ref(null)
const selectedDayDate   = ref('')

function selectDay(day) {
  if (!day.current) return
  selectedDayDate.value   = day.date
  selectedDayEvents.value = day.events
}

// ── Charts ────────────────────────────────────────────────────────────────────

const barChartData = computed(() => ({
  labels:   props.charts.assessmentLoad.map(r => r.section),
  datasets: [{
    label:           'Assessments',
    data:            props.charts.assessmentLoad.map(r => r.count),
    backgroundColor: BRAND,
  }],
}))

const barChartOptions = {
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
}

const lineChartData = computed(() => ({
  labels:   props.charts.teacherAttendance.map(r => r.label),
  datasets: [{
    label:           'Teachers Present',
    data:            props.charts.teacherAttendance.map(r => r.present),
    borderColor:     BRAND,
    backgroundColor: gradientFill(BRAND),
    fill:            true,
  }],
}))

const lineChartOptions = {
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
}

const donutChartData = computed(() => ({
  labels:   props.charts.classRecordStatus.map(r => r.label),
  datasets: [{
    data:            props.charts.classRecordStatus.map(r => r.count),
    backgroundColor: [STATUS.success, STATUS.warning],
  }],
}))

const donutChartOptions = {
  plugins: { legend: { position: 'bottom' } },
}
</script>

<template>
  <Head title="CID Dashboard" />
  <AdminLayout title="CID Dashboard">

    <!-- Header -->
    <AppPageHeader title="CID Dashboard" :subtitle="schoolYear?.name ?? 'No active school year'">
      <template #actions>
        <AppButton @click="openCreate(todayStr)">
          <PlusIcon class="w-4 h-4" />
          New Activity
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- ── Row 1: Summary Cards ─────────────────────────────────────────── -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">

      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Assessments Today</span>
          <CalendarDaysIcon class="w-5 h-5 text-red-400" />
        </div>
        <p class="text-3xl font-bold text-slate-800">{{ cards.assessments_today }}</p>
        <p class="text-xs text-slate-400 mt-0.5">from class records</p>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Sections at Max</span>
          <ExclamationTriangleIcon class="w-5 h-5 text-amber-400" />
        </div>
        <p class="text-3xl font-bold text-slate-800">{{ cards.sections_at_max }}</p>
        <p class="text-xs text-slate-400 mt-0.5">3 assessments today</p>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Teachers Present</span>
          <UserGroupIcon class="w-5 h-5 text-green-400" />
        </div>
        <p class="text-3xl font-bold text-slate-800">{{ cards.teachers_present_today }}</p>
        <p class="text-xs text-slate-400 mt-0.5">today via tap-in</p>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Class Records</span>
          <ClipboardDocumentCheckIcon class="w-5 h-5 text-indigo-400" />
        </div>
        <p class="text-3xl font-bold text-slate-800">{{ cards.class_records_pending }}</p>
        <p class="text-xs text-slate-400 mt-0.5">pending review</p>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">This Week</span>
          <DocumentTextIcon class="w-5 h-5 text-blue-400" />
        </div>
        <p class="text-3xl font-bold text-slate-800">{{ cards.activities_this_week }}</p>
        <p class="text-xs text-slate-400 mt-0.5">CID activities</p>
      </div>

    </div>

    <!-- ── Row 2: Calendar + Today Panel ────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

      <!-- Calendar (2/3) -->
      <AppCard class="lg:col-span-2">
        <!-- Month nav -->
        <div class="flex items-center justify-between mb-4">
          <button @click="navigateMonth(-1)" class="p-1.5 rounded hover:bg-slate-100">
            <ChevronLeftIcon class="w-5 h-5 text-slate-500" />
          </button>
          <span class="text-sm font-semibold text-slate-700">
            {{ MONTH_NAMES[calMonth - 1] }} {{ calYear }}
          </span>
          <button @click="navigateMonth(1)" class="p-1.5 rounded hover:bg-slate-100">
            <ChevronRightIcon class="w-5 h-5 text-slate-500" />
          </button>
        </div>

        <!-- Day headers -->
        <div class="grid grid-cols-7 mb-1">
          <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"
            class="text-center text-xs font-semibold text-slate-400 py-1">
            {{ d }}
          </div>
        </div>

        <!-- Day cells -->
        <div v-if="loading" class="flex items-center justify-center h-64 text-slate-400 text-sm">
          Loading…
        </div>
        <div v-else class="grid grid-cols-7 gap-px bg-slate-100 rounded-lg overflow-hidden">
          <div
            v-for="day in calendarDays" :key="day.date"
            :class="[
              'bg-white min-h-[80px] p-1.5 text-xs cursor-pointer hover:bg-indigo-50 transition-colors',
              !day.current ? 'opacity-30' : '',
              day.date === todayStr ? 'ring-2 ring-inset ring-indigo-400' : '',
              selectedDayDate === day.date ? 'bg-indigo-50' : '',
            ]"
            @click="selectDay(day)"
          >
            <div class="flex items-center justify-between mb-1">
              <span :class="['font-medium', day.date === todayStr ? 'text-indigo-600' : 'text-slate-700']">
                {{ day.day }}
              </span>
              <span
                v-if="day.current && dayHasMaxedSection(day.events)"
                title="A section has reached 3 assessments today"
                class="text-amber-500 font-bold text-[10px]"
              >3/3</span>
            </div>
            <div class="space-y-0.5 overflow-hidden">
              <div
                v-for="ev in day.events.slice(0, 3)" :key="ev.id"
                :class="['rounded px-1 py-0.5 truncate leading-tight text-[11px]', eventStyle(ev).bg, eventStyle(ev).text]"
              >
                {{ ev.title }}
              </div>
              <div v-if="day.events.length > 3" class="text-slate-400 pl-1">
                +{{ day.events.length - 3 }} more
              </div>
            </div>
          </div>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap gap-3 mt-3">
          <div class="flex items-center gap-1">
            <span class="w-2 h-2 rounded-full inline-block bg-red-400 border border-red-300"></span>
            <span class="text-xs text-slate-500">Assessment (Class Record)</span>
          </div>
          <div v-for="(colors, type) in CID_TYPE_COLORS" :key="type" class="flex items-center gap-1">
            <span :class="['w-2 h-2 rounded-full inline-block', colors.dot]"></span>
            <span class="text-xs text-slate-500 capitalize">{{ type }} (CID)</span>
          </div>
        </div>
      </AppCard>

      <!-- Today / Selected Day Panel (1/3) -->
      <AppCard class="flex flex-col">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-slate-700">
            {{ selectedDayDate && selectedDayDate !== todayStr ? selectedDayDate : 'Today' }}
          </h3>
          <button
            @click="openCreate(selectedDayDate || todayStr)"
            class="text-indigo-600 hover:text-indigo-700 text-xs font-medium flex items-center gap-1"
          >
            <PlusIcon class="w-3.5 h-3.5" /> Add
          </button>
        </div>

        <div class="flex-1 overflow-y-auto space-y-2 max-h-[360px] pr-1">
          <template v-if="(selectedDayEvents ?? todayEvents).length === 0">
            <div class="flex flex-col items-center justify-center h-32 text-slate-300">
              <CalendarDaysIcon class="w-10 h-10 mb-2" />
              <span class="text-xs">No activities</span>
            </div>
          </template>

          <div
            v-for="ev in (selectedDayEvents ?? todayEvents)"
            :key="ev.id"
            :class="['rounded-lg p-2.5 border', eventStyle(ev).bg]"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1">
                  <AppBadge :color="badgeColor(ev)" class="uppercase">
                    {{ ev.source === 'class_record' ? 'Assessment' : ev.type }}
                  </AppBadge>
                  <EyeIcon v-if="ev.source === 'class_record'" class="w-3 h-3 text-slate-400" title="Read-only — set by teacher" />
                </div>
                <p class="text-sm font-medium text-slate-800 leading-tight">{{ ev.title }}</p>
                <p v-if="ev.section_name" class="text-xs text-slate-500 mt-0.5">{{ ev.section_name }}</p>
                <p v-if="ev.subject_name && ev.source === 'class_record'" class="text-xs text-slate-400">{{ ev.subject_name }}</p>
                <p v-if="ev.start_time" class="text-xs text-slate-400 mt-0.5">
                  {{ ev.start_time }}{{ ev.end_time ? ' – ' + ev.end_time : '' }}
                </p>
                <p v-if="ev.description" class="text-xs text-slate-500 mt-1 line-clamp-2">{{ ev.description }}</p>
                <p v-if="ev.created_by" class="text-xs text-slate-400 mt-0.5 italic">{{ ev.source === 'class_record' ? 'Teacher: ' : 'By: ' }}{{ ev.created_by }}</p>
              </div>
              <!-- Only CID-created events can be edited/deleted -->
              <div v-if="ev.source === 'cid'" class="flex gap-1 shrink-0">
                <button @click="openEdit(ev)" class="p-1 rounded hover:bg-white/60">
                  <PencilIcon class="w-3.5 h-3.5 text-slate-500" />
                </button>
                <button @click="deleteSchedule(ev)" class="p-1 rounded hover:bg-white/60">
                  <TrashIcon class="w-3.5 h-3.5 text-red-400" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </AppCard>
    </div>

    <!-- ── Row 3: Charts ─────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <AppCard class="lg:col-span-1" title="Assessment Load by Section (This Month)" subtitle="From class records">
        <div class="h-48">
          <Bar v-if="charts.assessmentLoad.length" :data="barChartData" :options="barChartOptions" />
          <div v-else class="flex items-center justify-center h-full text-slate-300 text-xs">No assessments scheduled</div>
        </div>
      </AppCard>

      <AppCard class="lg:col-span-1" title="Teacher Attendance Rate (This Week)">
        <div class="h-48">
          <Line :data="lineChartData" :options="lineChartOptions" />
        </div>
      </AppCard>

      <AppCard class="lg:col-span-1" title="Class Record Status (Current SY)">
        <div class="h-48 flex items-center justify-center">
          <Doughnut
            v-if="charts.classRecordStatus.some(r => r.count > 0)"
            :data="donutChartData"
            :options="donutChartOptions"
          />
          <div v-else class="text-slate-300 text-xs">No class records</div>
        </div>
      </AppCard>

    </div>

    <!-- ── Create / Edit Modal (CID activities only) ─────────────────────── -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 pt-5 pb-3 border-b border-slate-100">
          <h2 class="text-base font-semibold text-slate-800">
            {{ editingSchedule ? 'Edit Activity' : 'New Activity' }}
          </h2>
          <button @click="closeModal" class="p-1.5 rounded hover:bg-slate-100">
            <XMarkIcon class="w-5 h-5 text-slate-400" />
          </button>
        </div>

        <div class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">

          <div v-if="formErrors._general" class="bg-danger-50 border border-danger-100 text-danger-700 rounded-lg px-3 py-2 text-sm">
            {{ formErrors._general }}
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Title <span class="text-red-500">*</span></label>
            <input v-model="form.title" type="text" placeholder="e.g. Department Meeting"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            <p v-if="formErrors.title" class="text-red-500 text-xs mt-1">{{ formErrors.title[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Type <span class="text-red-500">*</span></label>
            <select v-model="form.type"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
              <option value="meeting">Meeting</option>
              <option value="event">Event</option>
              <option value="training">Training</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
            <input v-model="form.scheduled_date" type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            <p v-if="formErrors.scheduled_date" class="text-red-500 text-xs mt-1">{{ formErrors.scheduled_date[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Section</label>
            <select v-model="form.section_id"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
              <option value="">— All sections / Not section-specific —</option>
              <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.label }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Subject</label>
            <select v-model="form.subject_id"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
              <option value="">— Not subject-specific —</option>
              <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Start Time</label>
              <input v-model="form.start_time" type="time"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">End Time</label>
              <input v-model="form.end_time" type="time"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="3" placeholder="Optional notes…"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full resize-none" />
          </div>

        </div>

        <div class="flex justify-end gap-2 px-6 py-4 border-t border-slate-100">
          <AppButton variant="secondary" @click="closeModal">
            Cancel
          </AppButton>
          <AppButton @click="saveSchedule" :loading="saving">
            {{ saving ? 'Saving…' : (editingSchedule ? 'Update' : 'Create') }}
          </AppButton>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
