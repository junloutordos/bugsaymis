<template>
  <Head title="Competitions & Winnings" />
  <AdminLayout title="Competitions & Winnings">
    <div class="space-y-5">

      <AppPageHeader title="Competitions & Winnings"
        subtitle="Competition participation and awards — employees and coached students">
        <template #actions>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> Add Competition
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <AppCard v-for="stat in stats" :key="stat.label" :padded="false">
          <div class="p-4 flex items-center gap-3">
            <div :class="['h-10 w-10 rounded-full flex items-center justify-center shrink-0', stat.iconBg]">
              <component :is="stat.icon" :class="['h-5 w-5', stat.iconColor]" />
            </div>
            <div class="min-w-0">
              <p class="text-2xl font-bold text-slate-800 leading-tight">{{ stat.value }}</p>
              <p class="text-xs text-slate-500 truncate">{{ stat.label }}</p>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative">
          <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input v-model="search" type="text" placeholder="Search title, organizer, participant..."
            class="pl-9 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-64" />
        </div>
        <select v-model="typeFilter"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Types</option>
          <option value="employee">Employee / Faculty</option>
          <option value="student">Student</option>
        </select>
        <select v-model="levelFilter"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Levels</option>
          <option v-for="(label, value) in levels" :key="value" :value="value">{{ label }}</option>
        </select>
        <select v-model="syFilter"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All School Years</option>
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.name">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppCard :padded="false">
        <AppTable :is-empty="displayed.length === 0" :skeleton-cols="6" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Competition</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Level</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Participants & Awards</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Coach</th>
              <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
            </tr>
          </template>

          <tr v-for="c in displayed" :key="c.id" class="hover:bg-slate-50/60 align-top">
            <td class="px-4 py-3">
              <p class="text-sm font-medium text-slate-800">{{ c.title }}</p>
              <p v-if="c.organizer" class="text-xs text-slate-500">{{ c.organizer }}</p>
              <p v-if="c.venue" class="text-xs text-slate-400">{{ c.venue }}</p>
              <div class="mt-1 flex flex-wrap gap-1">
                <AppBadge :color="c.competition_type === 'student' ? 'indigo' : 'blue'">
                  {{ c.competition_type === 'student' ? 'Student' : 'Employee' }}
                </AppBadge>
                <AppBadge v-if="c.represents_pshs" color="amber">PSHS Representation</AppBadge>
                <AppBadge v-if="c.itjr_no" color="indigo">ITJR {{ c.itjr_no }}</AppBadge>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">
              {{ fmtDate(c.date_from) }}<template v-if="c.date_to && c.date_to !== c.date_from"><br />– {{ fmtDate(c.date_to) }}</template>
              <p v-if="c.school_year" class="text-xs text-slate-400 mt-0.5">SY {{ c.school_year }}</p>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="levelColor(c.level)">{{ c.level_label }}</AppBadge>
            </td>
            <td class="px-4 py-3">
              <div v-for="(p, i) in c.participants" :key="i" class="text-sm text-slate-700 flex items-baseline gap-1.5 py-0.5">
                <span>{{ p.name }}</span>
                <span v-if="p.batch" class="text-xs text-slate-400">({{ p.batch }})</span>
                <AppBadge v-if="p.award" color="green">{{ p.award }}</AppBadge>
                <span v-else class="text-xs text-slate-400">Participant</span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">
              <template v-if="c.competition_type === 'student'">
                <p v-if="c.coach" class="font-medium">{{ c.coach.name }}</p>
                <p v-for="cc in c.co_coaches" :key="cc.id" class="text-xs text-slate-500">
                  w/ {{ cc.name }}
                </p>
              </template>
              <span v-else class="text-xs text-slate-400">—</span>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <template v-if="canEdit(c)">
                <button type="button" @click="openForm(c)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                  <PencilSquareIcon class="h-4 w-4" />
                </button>
                <button type="button" @click="confirmDelete(c)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-danger-600 hover:bg-danger-50 transition-colors">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </template>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="c in displayed" :key="c.id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ c.title }}</p>
                  <p class="text-xs text-slate-500">{{ fmtDate(c.date_from) }}<template v-if="c.organizer"> · {{ c.organizer }}</template></p>
                </div>
                <div v-if="canEdit(c)" class="flex shrink-0">
                  <button type="button" @click="openForm(c)" class="p-1.5 text-slate-400 hover:text-indigo-600">
                    <PencilSquareIcon class="h-4 w-4" />
                  </button>
                  <button type="button" @click="confirmDelete(c)" class="p-1.5 text-slate-400 hover:text-danger-600">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </div>
              <div class="flex flex-wrap gap-1">
                <AppBadge :color="c.competition_type === 'student' ? 'indigo' : 'blue'">
                  {{ c.competition_type === 'student' ? 'Student' : 'Employee' }}
                </AppBadge>
                <AppBadge :color="levelColor(c.level)">{{ c.level_label }}</AppBadge>
                <AppBadge v-if="c.represents_pshs" color="amber">PSHS</AppBadge>
                <AppBadge v-if="c.itjr_no" color="indigo">ITJR {{ c.itjr_no }}</AppBadge>
              </div>
              <div>
                <p v-for="(p, i) in c.participants" :key="i" class="text-sm text-slate-700">
                  {{ p.name }} <AppBadge v-if="p.award" color="green">{{ p.award }}</AppBadge>
                </p>
                <p v-if="c.coach" class="text-xs text-slate-500 mt-1">Coach: {{ c.coach.name }}</p>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No competition records"
              subtitle="Add your first competition entry to start building the winnings log."
              :icon="TrophyIcon" />
          </template>
        </AppTable>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
          <p class="text-xs text-slate-500">
            {{ filtered.length }} record(s) — page {{ currentPage }} of {{ totalPages }}
          </p>
          <div class="flex gap-1">
            <AppButton variant="secondary" size="sm" :disabled="currentPage === 1" @click="currentPage--">Prev</AppButton>
            <AppButton variant="secondary" size="sm" :disabled="currentPage === totalPages" @click="currentPage++">Next</AppButton>
          </div>
        </div>
      </AppCard>

    </div>

    <!-- Add / Edit Modal -->
    <AppModal :show="modal" :title="(form.id ? 'Edit' : 'Add') + ' Competition'" size="2xl"
      body-class="px-6 py-4 space-y-4" @close="modal = false">

      <div v-if="Object.keys(form.errors).length"
        class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-3 py-2 text-xs space-y-0.5">
        <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Competition type -->
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Competition Type *</label>
        <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm">
          <button type="button" @click="setType('employee')"
            :class="['px-4 py-2 font-medium transition-colors', form.competition_type === 'employee' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            Employee / Faculty
          </button>
          <button type="button" @click="setType('student')"
            :class="['px-4 py-2 font-medium border-l border-slate-200 transition-colors', form.competition_type === 'student' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            Student
          </button>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2">
          <AppInput v-model="form.title" label="Competition Title" required placeholder="e.g. Regional Science Quiz Bee 2026" />
        </div>
        <AppInput v-model="form.organizer" label="Organizer" placeholder="e.g. DOST-Caraga" />
        <AppInput v-model="form.venue" label="Venue" placeholder="e.g. Butuan City" />
        <AppSelect v-model="form.level" label="Level" required placeholder="Select level...">
          <option v-for="(label, value) in levels" :key="value" :value="value">{{ label }}</option>
        </AppSelect>
        <div class="grid grid-cols-2 gap-2">
          <AppInput v-model="form.date_from" type="date" label="Date From" required />
          <AppInput v-model="form.date_to" type="date" label="Date To" />
        </div>
        <div class="col-span-2 flex items-center gap-2">
          <input v-model="form.represents_pshs" type="checkbox" id="represents-pshs"
            class="rounded text-indigo-600 focus:ring-indigo-500" />
          <label for="represents-pshs" class="text-sm text-slate-700">
            This participation represents PSHS
          </label>
        </div>
        <div v-if="!form.id" class="col-span-2 flex items-center gap-2">
          <input v-model="form.file_itjr" type="checkbox" id="file-itjr"
            class="rounded text-indigo-600 focus:ring-indigo-500" />
          <label for="file-itjr" class="text-sm text-slate-700">
            File an IT Job Request for Graphic Design (congratulatory poster / social media graphic)
          </label>
        </div>
      </div>

      <!-- Employee participants -->
      <div v-if="form.competition_type === 'employee'" class="space-y-2">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee Participants & Awards</p>
        <div v-for="(row, i) in form.employees" :key="i" class="flex items-center gap-2">
          <select v-model="row.user_id"
            class="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Select employee...</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
          <input v-model="row.award" type="text" list="award-suggestions" placeholder="Award (blank = participant)"
            class="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          <button type="button" @click="form.employees.splice(i, 1)" :disabled="form.employees.length === 1"
            class="p-1.5 rounded-lg text-slate-400 hover:text-danger-600 hover:bg-danger-50 disabled:opacity-30 transition-colors">
            <XMarkIcon class="h-4 w-4" />
          </button>
        </div>
        <AppButton variant="secondary" size="sm" @click="form.employees.push({ user_id: null, award: '' })">
          <PlusIcon class="h-3 w-3" /> Add Employee
        </AppButton>
      </div>

      <!-- Student participants + coaches -->
      <template v-else>
        <div class="space-y-2">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Student Participants & Awards</p>

          <!-- Student search -->
          <div class="relative">
            <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input v-model="studentQuery" type="text" placeholder="Search student by name (min. 2 letters)..."
              class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <div v-if="studentResults.length"
              class="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
              <button v-for="s in studentResults" :key="s.id" type="button" @click="addStudent(s)"
                class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 flex justify-between items-baseline gap-2">
                <span class="text-slate-700">{{ s.name }}</span>
                <span v-if="s.batch" class="text-xs text-slate-400 shrink-0">Batch {{ s.batch }}</span>
              </button>
            </div>
          </div>

          <div v-for="(row, i) in form.students" :key="row.student_id" class="flex items-center gap-2">
            <span class="flex-1 text-sm text-slate-700 border border-slate-100 bg-slate-50 rounded-lg px-3 py-2 truncate">
              {{ row.name }}<span v-if="row.batch" class="text-xs text-slate-400"> · Batch {{ row.batch }}</span>
            </span>
            <input v-model="row.award" type="text" list="award-suggestions" placeholder="Award (blank = participant)"
              class="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <button type="button" @click="form.students.splice(i, 1)"
              class="p-1.5 rounded-lg text-slate-400 hover:text-danger-600 hover:bg-danger-50 transition-colors">
              <XMarkIcon class="h-4 w-4" />
            </button>
          </div>
          <p v-if="!form.students.length" class="text-xs text-slate-400">No students added yet — search above to add.</p>
        </div>

        <div class="space-y-2">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Coaching</p>
          <p class="text-sm text-slate-600">
            Coach: <span class="font-medium text-slate-800">{{ coachName }}</span>
          </p>
          <div class="flex items-center gap-2">
            <select v-model="coCoachToAdd"
              class="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Add co-coach...</option>
              <option v-for="e in coCoachOptions" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
            <AppButton variant="secondary" size="sm" :disabled="!coCoachToAdd" @click="addCoCoach">
              <PlusIcon class="h-3 w-3" /> Add
            </AppButton>
          </div>
          <div v-if="form.co_coaches.length" class="flex flex-wrap gap-1.5">
            <span v-for="id in form.co_coaches" :key="id"
              class="inline-flex items-center gap-1 bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-full px-2.5 py-1 text-xs font-medium">
              {{ employeeName(id) }}
              <button type="button" @click="form.co_coaches = form.co_coaches.filter(c => c !== id)"
                class="hover:text-danger-600"><XMarkIcon class="h-3 w-3" /></button>
            </span>
          </div>
        </div>
      </template>

      <datalist id="award-suggestions">
        <option v-for="a in AWARD_SUGGESTIONS" :key="a" :value="a" />
      </datalist>

      <div>
        <AppTextarea v-model="form.remarks" label="Remarks" :rows="2" />
      </div>

      <template #footer>
        <AppButton variant="ghost" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Save' }}</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  CheckCircleIcon, FlagIcon, GlobeAltIcon, MagnifyingGlassIcon, PencilSquareIcon,
  PlusIcon, TrophyIcon, TrashIcon, UsersIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  competitions: { type: Array,  default: () => [] },
  employees:    { type: Array,  default: () => [] },
  schoolYears:  { type: Array,  default: () => [] },
  levels:       { type: Object, default: () => ({}) },
  canManage:    { type: Boolean, default: false },
})

const page = usePage()
const authUser = computed(() => page.props.auth?.user ?? {})

const AWARD_SUGGESTIONS = [
  'Champion', '1st Runner-up', '2nd Runner-up', '3rd Runner-up',
  'Gold Medal', 'Silver Medal', 'Bronze Medal',
  'Finalist', 'Special Award', 'Best Paper', 'Best Presenter', 'Participant',
]

// ── Stats ────────────────────────────────────────────────────────────────────

const stats = computed(() => [
  { label: 'Total Competitions', value: props.competitions.length,
    icon: TrophyIcon, iconBg: 'bg-indigo-50', iconColor: 'text-indigo-600' },
  { label: 'Student Competitions', value: props.competitions.filter(c => c.competition_type === 'student').length,
    icon: UsersIcon, iconBg: 'bg-blue-50', iconColor: 'text-blue-600' },
  { label: 'National / International', value: props.competitions.filter(c => ['national', 'international'].includes(c.level)).length,
    icon: GlobeAltIcon, iconBg: 'bg-purple-50', iconColor: 'text-purple-600' },
  { label: 'PSHS Representations', value: props.competitions.filter(c => c.represents_pshs).length,
    icon: FlagIcon, iconBg: 'bg-amber-50', iconColor: 'text-amber-600' },
])

// ── Filters & pagination ─────────────────────────────────────────────────────

const search      = ref('')
const typeFilter  = ref('')
const levelFilter = ref('')
const syFilter    = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.competitions.filter(c => {
    if (typeFilter.value && c.competition_type !== typeFilter.value) return false
    if (levelFilter.value && c.level !== levelFilter.value) return false
    if (syFilter.value && c.school_year !== syFilter.value) return false
    if (!q) return true
    const haystack = [
      c.title, c.organizer, c.venue, c.coach?.name,
      ...c.participants.map(p => `${p.name} ${p.award ?? ''}`),
      ...c.co_coaches.map(cc => cc.name),
    ].join(' ').toLowerCase()
    return haystack.includes(q)
  })
})

const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})
watch([search, typeFilter, levelFilter, syFilter], () => { currentPage.value = 1 })

// ── Formatting ───────────────────────────────────────────────────────────────

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

function levelColor(level) {
  return {
    campus:        'slate',
    inter_campus:  'blue',
    regional:      'indigo',
    national:      'purple',
    international: 'amber',
  }[level] ?? 'slate'
}

function canEdit(c) {
  return props.canManage || c.created_by === authUser.value.id
}

function employeeName(id) {
  return props.employees.find(e => e.id === id)?.name ?? `User ${id}`
}

// ── Form & modal ─────────────────────────────────────────────────────────────

const modal = ref(false)

const form = useForm({
  id: null,
  title: '',
  competition_type: 'employee',
  organizer: '',
  venue: '',
  level: '',
  date_from: '',
  date_to: '',
  represents_pshs: false,
  file_itjr: false,
  remarks: '',
  employees: [{ user_id: null, award: '' }],
  students: [],
  co_coaches: [],
})

/** Coach display: the original submitter when editing, the current user when adding. */
const editingCoach = ref(null)
const coachName = computed(() => editingCoach.value?.name ?? authUser.value.name)

const coCoachToAdd = ref(null)
const coCoachOptions = computed(() => {
  const coachId = editingCoach.value?.id ?? authUser.value.id
  return props.employees.filter(e => e.id !== coachId && !form.co_coaches.includes(e.id))
})

function setType(type) {
  form.competition_type = type
}

function addCoCoach() {
  if (coCoachToAdd.value && !form.co_coaches.includes(coCoachToAdd.value)) {
    form.co_coaches.push(coCoachToAdd.value)
  }
  coCoachToAdd.value = null
}

function openForm(c = null) {
  form.reset()
  form.clearErrors()
  editingCoach.value = null
  if (c && c.id) {
    Object.assign(form, {
      id:               c.id,
      title:            c.title,
      competition_type: c.competition_type,
      organizer:        c.organizer ?? '',
      venue:            c.venue ?? '',
      level:            c.level,
      date_from:        c.date_from ?? '',
      date_to:          c.date_to ?? '',
      represents_pshs:  c.represents_pshs,
      remarks:          c.remarks ?? '',
      employees: c.competition_type === 'employee'
        ? c.participants.map(p => ({ user_id: p.user_id, award: p.award ?? '' }))
        : [{ user_id: null, award: '' }],
      students: c.competition_type === 'student'
        ? c.participants.map(p => ({ student_id: p.student_id, name: p.name, batch: p.batch, award: p.award ?? '' }))
        : [],
      co_coaches: c.co_coaches.map(cc => cc.id),
    })
    editingCoach.value = c.coach
  } else {
    // New employee-type entry: the submitting employee is the default participant
    form.employees = [{ user_id: authUser.value.id ?? null, award: '' }]
  }
  studentQuery.value = ''
  studentResults.value = []
  modal.value = true
}

function save() {
  const opts = {
    preserveScroll: true,
    onSuccess: () => { modal.value = false },
  }
  if (form.id) {
    form.put(route('cid.competitions.update', form.id), opts)
  } else {
    form.post(route('cid.competitions.store'), opts)
  }
}

function confirmDelete(c) {
  Swal.fire({
    icon: 'warning',
    title: 'Delete this record?',
    text: `"${c.title}" and its participants will be removed.`,
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
  }).then((res) => {
    if (!res.isConfirmed) return
    router.delete(route('cid.competitions.destroy', c.id), { preserveScroll: true })
  })
}

// ── Student search (debounced) ───────────────────────────────────────────────

const studentQuery   = ref('')
const studentResults = ref([])
let searchTimer = null

watch(studentQuery, (q) => {
  clearTimeout(searchTimer)
  if (!q || q.trim().length < 2) {
    studentResults.value = []
    return
  }
  searchTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get(route('cid.competitions.students.search'), { params: { q: q.trim() } })
      // Exclude students already added
      const addedIds = new Set(form.students.map(s => s.student_id))
      studentResults.value = data.filter(s => !addedIds.has(s.id))
    } catch {
      studentResults.value = []
    }
  }, 300)
})

function addStudent(s) {
  form.students.push({ student_id: s.id, name: s.name, batch: s.batch, award: '' })
  studentQuery.value = ''
  studentResults.value = []
}
</script>
