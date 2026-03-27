<template>
  <Head title="Class Schedules" />
  <AdminLayout title="Class Schedules">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Class Schedules</h1>
          <p class="text-sm text-slate-500 mt-0.5">Assign and manage faculty class schedules</p>
        </div>
        <button @click="openForm()"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <PlusIcon class="h-4 w-4" /> Assign Schedule
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <select v-model="filters.faculty_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All Faculty</option>
          <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
      </div>

      <!-- Empty -->
      <div v-if="schedules.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <CalendarIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No schedules found</p>
        <p class="text-xs text-slate-400 mt-1">Assign a schedule to get started.</p>
      </div>

      <!-- Week view grouped by day -->
      <div v-else class="space-y-4">
        <div v-for="day in daysWithSchedules" :key="day"
          class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-700">{{ day }}</h3>
          </div>
          <table class="min-w-full divide-y divide-slate-50 text-sm">
            <thead>
              <tr class="text-xs text-slate-400 uppercase tracking-wide">
                <th class="px-4 py-2 text-left">Time</th>
                <th class="px-4 py-2 text-left">Faculty</th>
                <th class="px-4 py-2 text-left">Subject</th>
                <th class="px-4 py-2 text-left">Section</th>
                <th class="px-4 py-2 text-left">Room</th>
                <th class="px-4 py-2 text-center">Status</th>
                <th class="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="s in byDay[day]" :key="s.id" class="hover:bg-slate-50/50">
                <td class="px-4 py-3 font-mono text-xs text-slate-600 whitespace-nowrap">
                  {{ fmtTime(s.start_time) }} – {{ fmtTime(s.end_time) }}
                </td>
                <td class="px-4 py-3 text-slate-800 font-medium">{{ s.faculty?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                  <p class="text-slate-800">{{ s.subject?.name ?? '—' }}</p>
                  <p class="text-xs text-slate-400 font-mono">{{ s.subject?.code }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ sectionLabel(s.section_id) }}</td>
                <td class="px-4 py-3 text-slate-600">{{ s.classroom?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="statusBadge(s.status)"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                    {{ s.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button @click="openForm(s)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                      <PencilIcon class="h-4 w-4" />
                    </button>
                    <button @click="cancel(s)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                      <TrashIcon class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Schedule Form Modal -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 my-8">
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'Assign' }} Schedule</h2>

        <!-- Validation result banner -->
        <div v-if="validationResult" class="space-y-1.5">
          <div v-for="err in validationResult.errors ?? []" :key="err"
            class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-3 py-2 text-xs flex items-start gap-1.5">
            <ExclamationCircleIcon class="h-4 w-4 shrink-0 mt-0.5" /> {{ err }}
          </div>
          <div v-for="w in validationResult.warnings ?? []" :key="w"
            class="bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-3 py-2 text-xs flex items-start gap-1.5">
            <ExclamationTriangleIcon class="h-4 w-4 shrink-0 mt-0.5" /> {{ w }}
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Faculty *</label>
            <select v-model="form.faculty_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select faculty...</option>
              <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Subject *</label>
            <select v-model="form.subject_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select subject...</option>
              <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.code }} — {{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Section *</label>
            <select v-model="form.section_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select section...</option>
              <option v-for="sec in sections" :key="sec.id" :value="sec.id">{{ sec.sectionname }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Classroom *</label>
            <select v-model="form.classroom_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select classroom...</option>
              <option v-for="c in classrooms" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term *</label>
            <select v-model="form.academic_term_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select term...</option>
              <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">School Year *</label>
            <select v-model="form.school_year_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select school year...</option>
              <option v-for="t in terms" :key="'sy-' + t.id" :value="t.id">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Day *</label>
            <select v-model="form.day_of_week" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option value="">Select day...</option>
              <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Start Time *</label>
            <input v-model="form.start_time" type="time" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">End Time *</label>
            <input v-model="form.end_time" type="time" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select v-model="form.status" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option value="active">Active</option>
              <option value="tentative">Tentative</option>
              <option v-if="form.id" value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="2" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
          </div>

          <!-- Override warnings toggle -->
          <div v-if="validationResult && validationResult.warnings?.length && !validationResult.errors?.length" class="col-span-2 flex items-center gap-2">
            <input v-model="form.force" type="checkbox" id="force-save" class="rounded text-amber-500" />
            <label for="force-save" class="text-sm text-amber-700">I acknowledge the warnings — save anyway</label>
          </div>
        </div>

        <div class="flex justify-between gap-3 pt-1">
          <button type="button" @click="checkConflicts"
            class="px-4 py-2 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg font-medium flex items-center gap-1.5">
            <MagnifyingGlassIcon class="h-4 w-4" /> Check Conflicts
          </button>
          <div class="flex gap-2">
            <button @click="modal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
            <button @click="save" :disabled="form.processing"
              class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
              {{ form.id ? 'Update' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import {
  CalendarIcon, CheckCircleIcon, ExclamationCircleIcon, ExclamationTriangleIcon,
  MagnifyingGlassIcon, PencilIcon, PlusIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  schedules:   { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  subjects:    { type: Array,  default: () => [] },
  classrooms:  { type: Array,  default: () => [] },
  sections:    { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
})

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

const filters = reactive({
  term_id:    props.filters.term_id    ?? props.currentTerm?.id ?? null,
  faculty_id: props.filters.faculty_id ?? null,
})

function applyFilters() {
  router.get(route('faculty-loading.schedules.index'), filters, { preserveState: true })
}

// Group schedules by day
const byDay = computed(() => {
  const map = {}
  for (const d of days) map[d] = []
  for (const s of props.schedules) {
    if (map[s.day_of_week]) map[s.day_of_week].push(s)
  }
  return map
})

const daysWithSchedules = computed(() => days.filter(d => byDay.value[d].length > 0))

function fmtTime(t) {
  if (! t) return '—'
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  return `${hour % 12 || 12}:${m} ${ampm}`
}

function sectionLabel(id) {
  return props.sections.find(s => s.id === id)?.sectionname ?? `Section ${id}`
}

function statusBadge(status) {
  return {
    active:    'bg-emerald-50 text-emerald-700',
    tentative: 'bg-amber-50 text-amber-700',
    cancelled: 'bg-slate-100 text-slate-400',
  }[status] ?? 'bg-slate-50 text-slate-600'
}

// Form & modal
const modal            = ref(false)
const validationResult = ref(null)

const form = useForm({
  id: null, faculty_id: null, subject_id: null, section_id: null, classroom_id: null,
  school_year_id: null, academic_term_id: null, day_of_week: '',
  start_time: '', end_time: '', status: 'active', remarks: '', force: false,
})

function openForm(s = null) {
  validationResult.value = null
  if (s) {
    Object.assign(form, {
      id: s.id, faculty_id: s.faculty?.id ?? null, subject_id: s.subject?.id ?? null,
      section_id: s.section_id, classroom_id: s.classroom?.id ?? null,
      school_year_id: null, academic_term_id: filters.term_id ?? null,
      day_of_week: s.day_of_week, start_time: s.start_time?.slice(0,5) ?? '',
      end_time: s.end_time?.slice(0,5) ?? '', status: s.status, remarks: s.remarks ?? '', force: false,
    })
  } else {
    form.reset(); form.id = null; form.status = 'active'; form.force = false
    form.academic_term_id = filters.term_id ?? null
  }
  modal.value = true
}

async function checkConflicts() {
  if (! form.faculty_id || ! form.academic_term_id || ! form.day_of_week || ! form.start_time || ! form.end_time) return
  const payload = {
    faculty_id:       form.faculty_id,
    subject_id:       form.subject_id ?? 0,
    section_id:       form.section_id ?? 0,
    classroom_id:     form.classroom_id ?? 0,
    academic_term_id: form.academic_term_id,
    day_of_week:      form.day_of_week,
    start_time:       form.start_time,
    end_time:         form.end_time,
    exclude_id:       form.id,
  }
  try {
    const { data } = await axios.post(route('faculty-loading.schedules.validate'), payload)
    validationResult.value = data
  } catch (e) {
    console.error(e)
  }
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.schedules.update', form.id), {
      onSuccess: () => { modal.value = false; validationResult.value = null },
    })
  } else {
    form.post(route('faculty-loading.schedules.store'), {
      onSuccess: () => { modal.value = false; validationResult.value = null },
    })
  }
}

function cancel(s) {
  if (! confirm(`Cancel schedule for "${s.subject?.name}" on ${s.day_of_week}?`)) return
  useForm({}).delete(route('faculty-loading.schedules.destroy', s.id))
}
</script>
