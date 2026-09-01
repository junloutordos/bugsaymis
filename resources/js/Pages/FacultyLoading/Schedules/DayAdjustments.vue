<template>
  <Head title="Adjusted Day Schedules" />
  <AdminLayout title="Adjusted Day Schedules">
    <div class="space-y-5">
      <AppPageHeader
        title="Adjusted Day Schedules"
        subtitle="Create date-specific flag-ceremony or shortened-class schedules without changing the regular weekly timetable."
      >
        <template #actions>
          <AppButton variant="secondary" as="link" :href="route('faculty-loading.schedules.index', { term_id: term?.id })">
            <ArrowLeftIcon class="h-4 w-4" /> Regular Schedule
          </AppButton>
          <AppButton v-if="canManage" :disabled="!term" @click="openCreate">
            <PlusIcon class="h-4 w-4" /> New Adjustment
          </AppButton>
        </template>
      </AppPageHeader>

      <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ $page.props.flash.success }}
      </div>

      <div v-if="$page.props.flash?.warning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {{ $page.props.flash.warning }}
      </div>

      <AppFilterBar>
        <select v-model="selectedTermId" @change="changeTerm"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option v-for="item in terms" :key="item.id" :value="item.id">
            {{ item.label }}{{ item.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <Link :href="route('academic-calendar.index')" class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-700">
          <CalendarDaysIcon class="h-4 w-4" /> Academic Calendar
        </Link>
      </AppFilterBar>

      <div class="rounded-lg border border-indigo-100 bg-indigo-50/70 px-4 py-3 text-sm text-indigo-800">
        <strong>Regular schedule remains the default.</strong>
        Only a published record applies to its exact effective date. Drafts are previews, and cancelled records have no effect.
      </div>

      <AppCard v-if="!adjustments.length">
        <EmptyState title="No adjusted school days" subtitle="Create one for a transferred flag ceremony or an official activity requiring 30-minute classes." :icon="CalendarDaysIcon" />
      </AppCard>

      <AppCard v-else padding="none">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Adjustment</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Details</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reason</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-for="item in adjustments" :key="item.id">
                <td class="px-4 py-3 text-sm">
                  <div class="font-semibold text-slate-800">{{ item.weekday }}, {{ formatDate(item.effective_date) }}</div>
                </td>
                <td class="px-4 py-3 text-sm font-medium text-slate-700">
                  {{ adjustmentTypeLabel(item.adjustment_type) }}
                  <div class="mt-0.5 text-xs font-normal text-slate-500">{{ gradeLevelsLabel(item.grade_levels) }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-600">
                  <div v-if="hasFlag(item.adjustment_type)">Flag 7:30–8:00 AM<span v-if="item.postponed_from_date"> · From {{ formatDate(item.postponed_from_date) }}</span></div>
                  <div v-if="item.adjustment_type === 'early_start_stem_split'">
                    Starts {{ item.day_start_time }} · STEM {{ item.stem_class_duration_minutes }}min / Non-STEM {{ item.non_stem_class_duration_minutes }}min
                    <span v-if="item.health_break_title"> · {{ item.health_break_title }} {{ formatTimeRange(item.health_break_start_time, item.health_break_end_time) }}</span>
                  </div>
                  <div v-else-if="hasShortenedClasses(item.adjustment_type)">
                    30-minute classes<span v-if="protectsAssessments(item.adjustment_type)"> (major assessment periods stay 50 min)</span> · {{ item.activity_title }} · {{ formatTimeRange(item.activity_start_time, item.activity_end_time) }}
                  </div>
                </td>
                <td class="max-w-sm px-4 py-3 text-sm text-slate-600">{{ item.reason }}</td>
                <td class="px-4 py-3">
                  <span :class="statusClass(item.status)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">
                    {{ statusLabel(item.status) }}
                  </span>
                  <div v-if="item.published_by" class="mt-1 text-xs text-slate-400">by {{ item.published_by }}</div>
                  <div v-if="item.cancelled_by" class="mt-1 text-xs text-slate-400">by {{ item.cancelled_by }}</div>
                </td>
                <td class="w-px whitespace-nowrap px-4 py-3">
                  <div v-if="item.status !== 'cancelled'" class="flex items-center justify-end gap-1">
                    <AppIconButton v-if="canManage && item.status === 'draft'" label="Edit adjustment" @click="openEdit(item)">
                      <PencilSquareIcon class="h-4 w-4" />
                    </AppIconButton>
                    <AppIconButton v-if="canManage" label="Edit grade levels" @click="openEditGrades(item)">
                      <AcademicCapIcon class="h-4 w-4" />
                    </AppIconButton>
                    <AppIconButton v-if="canManage && item.status === 'draft'" label="Resolve conflicts" @click="openResolve(item)">
                      <AdjustmentsHorizontalIcon class="h-4 w-4" />
                    </AppIconButton>
                    <AppIconButton :label="item.status === 'draft' ? 'Preview adjusted schedule' : 'Print adjusted schedule'" variant="primary" @click="choosePrint(item)">
                      <PrinterIcon class="h-4 w-4" />
                    </AppIconButton>
                    <AppIconButton v-if="canManage && item.status === 'draft'" label="Publish adjustment" variant="success" @click="publish(item)">
                      <CheckCircleIcon class="h-4 w-4" />
                    </AppIconButton>
                    <AppIconButton v-if="canManage" label="Cancel adjustment" variant="danger" @click="cancelAdjustment(item)">
                      <XCircleIcon class="h-4 w-4" />
                    </AppIconButton>
                  </div>
                  <div v-else class="text-right text-sm text-slate-300" aria-label="No available actions">—</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </div>

    <AppModal :show="showForm" :title="editingId ? 'Edit Adjusted Day' : 'New Adjusted Day'" size="lg" @close="showForm = false">
      <div class="space-y-4">
        <div v-if="hasFlag(form.adjustment_type)" class="rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          First record the campus-wide Monday holiday or suspension in the Academic Calendar. The next day is calculated across Grades 7–12.
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Adjustment type</label>
          <select v-model="form.adjustment_type" @change="onTypeChange"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="flag_ceremony">Transferred Flag Ceremony</option>
            <option value="shortened_classes">30-Minute Classes for Official Activity</option>
            <option value="flag_ceremony_shortened_classes">Transferred Flag Ceremony + 30-Minute Classes</option>
            <option value="shortened_classes_protect_assessments">30-Minute Classes (Protect Assessment Periods)</option>
            <option value="early_start_stem_split">Early Start — STEM/Non-STEM Split (7 AM)</option>
          </select>
        </div>

        <div>
          <div class="mb-1 flex items-center justify-between">
            <label class="block text-sm font-medium text-slate-700">Grade levels</label>
            <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-700" @click="toggleAllGrades">
              {{ allGradesSelected ? 'Clear all' : 'Select all' }}
            </button>
          </div>
          <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
            <label v-for="grade in ALL_GRADES" :key="grade"
              class="flex items-center justify-center gap-1.5 rounded-lg border px-2 py-2 text-sm font-medium cursor-pointer"
              :class="form.grade_levels.includes(grade) ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-600'">
              <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                :checked="form.grade_levels.includes(grade)" @change="toggleGrade(grade)" />
              G{{ grade }}
            </label>
          </div>
          <p v-if="form.errors.grade_levels" class="mt-1 text-xs text-rose-600">{{ form.errors.grade_levels }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div v-if="hasFlag(form.adjustment_type)">
            <label class="mb-1 block text-sm font-medium text-slate-700">Postponed Monday</label>
            <input v-model="form.postponed_from_date" type="date" :min="term?.start_date" :max="term?.end_date" @change="suggestNextDay"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p v-if="form.errors.postponed_from_date" class="mt-1 text-xs text-rose-600">{{ form.errors.postponed_from_date }}</p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">{{ hasFlag(form.adjustment_type) ? 'Next common school day' : 'Effective date' }}</label>
            <input v-model="form.effective_date" type="date" :readonly="hasFlag(form.adjustment_type)" :min="term?.start_date" :max="term?.end_date"
              :class="hasFlag(form.adjustment_type) ? 'bg-slate-50' : 'bg-white'"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p v-if="suggesting" class="mt-1 text-xs text-indigo-500">Checking the Academic Calendar…</p>
            <p v-if="form.errors.effective_date" class="mt-1 text-xs text-rose-600">{{ form.errors.effective_date }}</p>
          </div>
        </div>

        <div v-if="hasShortenedClasses(form.adjustment_type)" class="space-y-4 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Official activity</label>
            <input v-model="form.activity_title" type="text" placeholder="Example: Research Congress"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p v-if="form.errors.activity_title" class="mt-1 text-xs text-rose-600">{{ form.errors.activity_title }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Activity starts</label>
              <input v-model="form.activity_start_time" type="time"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p v-if="form.errors.activity_start_time" class="mt-1 text-xs text-rose-600">{{ form.errors.activity_start_time }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Activity ends</label>
              <input v-model="form.activity_end_time" type="time"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p v-if="form.errors.activity_end_time" class="mt-1 text-xs text-rose-600">{{ form.errors.activity_end_time }}</p>
            </div>
          </div>
        </div>

        <div v-if="isEarlyStartStemSplit(form.adjustment_type)" class="space-y-4 rounded-xl border border-purple-100 bg-purple-50/50 p-4">
          <div v-if="stemSubjectCoverage.total > 0" class="rounded-lg border border-purple-200 bg-white px-3 py-2 text-xs text-purple-800">
            {{ stemSubjectCoverage.tagged }} of {{ stemSubjectCoverage.total }} active subjects are tagged STEM.
            <span v-if="stemSubjectCoverage.tagged === 0" class="font-semibold">No subjects are tagged yet — every class will compress to the non-STEM duration. Tag subjects in the Subject Catalog first.</span>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Day starts</label>
              <input v-model="form.day_start_time" type="time"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">STEM period (min)</label>
              <input v-model.number="form.stem_class_duration_minutes" type="number" min="10" max="60"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Non-STEM period (min)</label>
              <input v-model.number="form.non_stem_class_duration_minutes" type="number" min="10" max="60"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Health break (optional)</label>
            <input v-model="form.health_break_title" type="text" placeholder="Example: Snack Break"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p v-if="form.errors.health_break_title" class="mt-1 text-xs text-rose-600">{{ form.errors.health_break_title }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Break starts</label>
              <input v-model="form.health_break_start_time" type="time"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p v-if="form.errors.health_break_start_time" class="mt-1 text-xs text-rose-600">{{ form.errors.health_break_start_time }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Break ends</label>
              <input v-model="form.health_break_end_time" type="time"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p v-if="form.errors.health_break_end_time" class="mt-1 text-xs text-rose-600">{{ form.errors.health_break_end_time }}</p>
            </div>
          </div>
          <p class="text-xs text-slate-500">Lunch is not plotted on this type of adjusted day. Leave the break blank to skip it entirely.</p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Reason</label>
          <textarea v-model="form.reason" rows="3" placeholder="Official activity or schedule adjustment reason"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
          <p v-if="form.errors.reason" class="mt-1 text-xs text-rose-600">{{ form.errors.reason }}</p>
        </div>

        <div class="grid gap-3 rounded-lg bg-slate-50 p-3 text-sm sm:grid-cols-2">
          <div v-if="hasFlag(form.adjustment_type)"><span class="text-slate-500">Flag ceremony:</span> <strong>7:30–8:00 AM</strong></div>
          <div v-if="isEarlyStartStemSplit(form.adjustment_type)">
            <span class="text-slate-500">Class periods:</span>
            <strong>STEM {{ form.stem_class_duration_minutes }} minutes, Non-STEM {{ form.non_stem_class_duration_minutes }} minutes, no lunch plotted</strong>
          </div>
          <div v-else-if="hasShortenedClasses(form.adjustment_type)">
            <span class="text-slate-500">Class periods:</span>
            <strong v-if="protectsAssessments(form.adjustment_type)">30 minutes each, except periods with a scheduled major assessment (stay 50 minutes)</strong>
            <strong v-else>30 minutes each</strong>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="showForm = false">Close</AppButton>
          <AppButton :loading="form.processing" :disabled="!form.effective_date" @click="saveDraft">Save Draft</AppButton>
        </div>
      </template>
    </AppModal>

    <AppModal :show="showEditGrades" title="Edit Grade Levels" size="md" @close="showEditGrades = false">
      <div class="space-y-4">
        <p class="text-sm text-slate-600">
          Grade levels can be changed even after publishing — unselected grades keep their regular weekly schedule on this date.
        </p>
        <div>
          <div class="mb-1 flex items-center justify-between">
            <label class="block text-sm font-medium text-slate-700">Grade levels</label>
            <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-700" @click="toggleAllGrades(gradesForm)">
              {{ gradesForm.grade_levels.length === ALL_GRADES.length ? 'Clear all' : 'Select all' }}
            </button>
          </div>
          <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
            <label v-for="grade in ALL_GRADES" :key="grade"
              class="flex items-center justify-center gap-1.5 rounded-lg border px-2 py-2 text-sm font-medium cursor-pointer"
              :class="gradesForm.grade_levels.includes(grade) ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-600'">
              <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                :checked="gradesForm.grade_levels.includes(grade)" @change="toggleGrade(grade, gradesForm)" />
              G{{ grade }}
            </label>
          </div>
          <p v-if="gradesForm.errors.grade_levels" class="mt-1 text-xs text-rose-600">{{ gradesForm.errors.grade_levels }}</p>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="showEditGrades = false">Close</AppButton>
          <AppButton :loading="gradesForm.processing" @click="saveGrades">Save Grades</AppButton>
        </div>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  AcademicCapIcon,
  AdjustmentsHorizontalIcon,
  ArrowLeftIcon,
  CalendarDaysIcon,
  CheckCircleIcon,
  PencilSquareIcon,
  PlusIcon,
  PrinterIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  term: { type: Object, default: null },
  terms: { type: Array, default: () => [] },
  adjustments: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  stemSubjectCoverage: { type: Object, default: () => ({ tagged: 0, total: 0 }) },
})

const ALL_GRADES = [7, 8, 9, 10, 11, 12]

const selectedTermId = ref(props.term?.id ?? null)
const showForm = ref(false)
const editingId = ref(null)
const suggesting = ref(false)
const form = useForm({
  academic_term_id: props.term?.id ?? null,
  adjustment_type: 'flag_ceremony',
  grade_levels: [...ALL_GRADES],
  postponed_from_date: '',
  effective_date: '',
  activity_title: '',
  activity_start_time: '13:00',
  activity_end_time: '17:00',
  day_start_time: '07:00',
  stem_class_duration_minutes: 50,
  non_stem_class_duration_minutes: 30,
  health_break_title: '',
  health_break_start_time: '',
  health_break_end_time: '',
  reason: '',
})

const showEditGrades = ref(false)
const gradesForm = useForm({ grade_levels: [...ALL_GRADES] })
let editingGradesItem = null

const allGradesSelected = computed(() => form.grade_levels.length === ALL_GRADES.length)

function toggleGrade(grade, target = form) {
  const index = target.grade_levels.indexOf(grade)
  if (index === -1) {
    target.grade_levels.push(grade)
  } else {
    target.grade_levels.splice(index, 1)
  }
}

function toggleAllGrades(target = form) {
  target.grade_levels = target.grade_levels.length === ALL_GRADES.length ? [] : [...ALL_GRADES]
}

function gradeLevelsLabel(gradeLevels) {
  if (!gradeLevels || gradeLevels.length === ALL_GRADES.length) return 'All grades'
  return gradeLevels.map(grade => `G${grade}`).join(', ')
}

function openEditGrades(item) {
  editingGradesItem = item
  gradesForm.clearErrors()
  gradesForm.grade_levels = item.grade_levels && item.grade_levels.length ? [...item.grade_levels] : [...ALL_GRADES]
  showEditGrades.value = true
}

function saveGrades() {
  gradesForm.put(route('faculty-loading.schedules.day-adjustments.update-grades', editingGradesItem.id), {
    preserveScroll: true,
    onSuccess: () => { showEditGrades.value = false },
  })
}

function openResolve(item) {
  router.visit(route('faculty-loading.schedules.day-adjustments.resolve', item.id))
}

function changeTerm() {
  router.get(route('faculty-loading.schedules.day-adjustments.index'), { term_id: selectedTermId.value }, { preserveState: false })
}

function openCreate() {
  editingId.value = null
  form.reset()
  form.clearErrors()
  form.academic_term_id = props.term?.id ?? null
  form.adjustment_type = 'flag_ceremony'
  form.grade_levels = [...ALL_GRADES]
  form.activity_start_time = '13:00'
  form.activity_end_time = '17:00'
  form.day_start_time = '07:00'
  form.stem_class_duration_minutes = 50
  form.non_stem_class_duration_minutes = 30
  form.health_break_title = ''
  form.health_break_start_time = ''
  form.health_break_end_time = ''
  showForm.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.clearErrors()
  form.academic_term_id = props.term?.id ?? null
  form.adjustment_type = item.adjustment_type
  form.grade_levels = item.grade_levels && item.grade_levels.length ? [...item.grade_levels] : [...ALL_GRADES]
  form.postponed_from_date = item.postponed_from_date ?? ''
  form.effective_date = item.effective_date
  form.activity_title = item.activity_title ?? ''
  form.activity_start_time = item.activity_start_time ?? '13:00'
  form.activity_end_time = item.activity_end_time ?? '17:00'
  form.day_start_time = item.day_start_time ?? '07:00'
  form.stem_class_duration_minutes = item.stem_class_duration_minutes ?? 50
  form.non_stem_class_duration_minutes = item.non_stem_class_duration_minutes ?? 30
  form.health_break_title = item.health_break_title ?? ''
  form.health_break_start_time = item.health_break_start_time ?? ''
  form.health_break_end_time = item.health_break_end_time ?? ''
  form.reason = item.reason
  showForm.value = true
}

function onTypeChange() {
  form.clearErrors()
  if (!hasFlag(form.adjustment_type)) form.postponed_from_date = ''
  if (hasFlag(form.adjustment_type)) form.effective_date = ''
}

async function suggestNextDay() {
  form.effective_date = ''
  form.clearErrors('postponed_from_date', 'effective_date')
  if (!form.postponed_from_date || !form.academic_term_id) return

  suggesting.value = true
  try {
    const { data } = await axios.get(route('faculty-loading.schedules.day-adjustments.suggest'), {
      params: { academic_term_id: form.academic_term_id, postponed_from_date: form.postponed_from_date },
    })
    form.effective_date = data.effective_date
  } catch (error) {
    const errors = error.response?.data?.errors ?? {}
    form.setError('postponed_from_date', errors.postponed_from_date?.[0] ?? error.response?.data?.message ?? 'Unable to find the next school day.')
  } finally {
    suggesting.value = false
  }
}

function saveDraft() {
  const options = { preserveScroll: true, onSuccess: () => { showForm.value = false } }
  if (editingId.value) {
    form.put(route('faculty-loading.schedules.day-adjustments.update', editingId.value), options)
  } else {
    form.post(route('faculty-loading.schedules.day-adjustments.store'), options)
  }
}

async function choosePrint(item) {
  const result = await Swal.fire({
    title: item.status === 'draft' ? 'Preview adjusted schedule' : 'Print adjusted schedule',
    input: 'select',
    inputOptions: {
      all: 'All grade levels',
      7: 'Grade 7',
      8: 'Grade 8',
      9: 'Grade 9',
      10: 'Grade 10',
      11: 'Grade 11',
      12: 'Grade 12',
    },
    inputValue: 'all',
    showCancelButton: true,
    confirmButtonText: item.status === 'draft' ? 'Open preview' : 'Open print view',
    confirmButtonColor: '#4f46e5',
  })

  if (!result.isConfirmed) return

  const params = { adjustment: item.id }
  if (result.value !== 'all') params.grade = Number(result.value)
  window.open(route('faculty-loading.schedules.day-adjustments.print', params), '_blank', 'noopener')
}

async function publish(item) {
  const result = await Swal.fire({
    icon: 'question',
    title: 'Publish adjusted schedule?',
    text: 'This freezes the current generated schedule for official printing.',
    showCancelButton: true,
    confirmButtonText: 'Publish',
    confirmButtonColor: '#059669',
  })
  if (result.isConfirmed) router.post(route('faculty-loading.schedules.day-adjustments.publish', item.id), {}, { preserveScroll: true })
}

async function cancelAdjustment(item) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Cancel this adjustment?',
    text: 'The regular weekly schedule will apply on this date.',
    showCancelButton: true,
    confirmButtonText: 'Cancel adjustment',
    confirmButtonColor: '#e11d48',
  })
  if (result.isConfirmed) router.post(route('faculty-loading.schedules.day-adjustments.cancel', item.id), {}, { preserveScroll: true })
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

function hasFlag(type) {
  return ['flag_ceremony', 'flag_ceremony_shortened_classes'].includes(type)
}

function hasShortenedClasses(type) {
  return ['shortened_classes', 'flag_ceremony_shortened_classes', 'shortened_classes_protect_assessments', 'early_start_stem_split'].includes(type)
}

function protectsAssessments(type) {
  return type === 'shortened_classes_protect_assessments'
}

function isEarlyStartStemSplit(type) {
  return type === 'early_start_stem_split'
}

function adjustmentTypeLabel(type) {
  return ({
    flag_ceremony: 'Transferred Flag Ceremony',
    shortened_classes: '30-Minute Classes',
    flag_ceremony_shortened_classes: 'Flag Ceremony + 30-Minute Classes',
    shortened_classes_protect_assessments: '30-Minute Classes (Protect Assessments)',
    early_start_stem_split: 'Early Start — STEM/Non-STEM Split',
  })[type] ?? type
}

function formatTimeRange(start, end) {
  if (!start || !end) return ''
  const format = value => new Date(`2000-01-01T${value}:00`).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })
  return `${format(start)}–${format(end)}`
}

function statusLabel(status) {
  return ({ draft: 'Draft', published: 'Published', cancelled: 'Cancelled' })[status] ?? status
}

function statusClass(status) {
  return ({
    draft: 'bg-amber-100 text-amber-700',
    published: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-slate-100 text-slate-500',
  })[status] ?? 'bg-slate-100 text-slate-600'
}
</script>
