<script setup>
import { ref, watch, computed, onBeforeUnmount } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'
import { TrashIcon, BoltIcon, MagnifyingGlassIcon, UserPlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show:            { type: Boolean, default: false },
  classRecordId:   { type: Number,  required: true },
  quarter:         { type: Number,  required: true },
  initialStudents: { type: Array,   default: () => [] },
  subjectType:     { type: String,  default: null },
  sectionId:       { type: Number,  default: null },
})

const emit = defineEmits(['close', 'saved'])

const modalTitle = computed(() => `Manage Student Roster — Quarter ${props.quarter}`)
const isCrossSection = computed(() => ['elective', 'science_core'].includes(props.subjectType))
const scopeLabel = computed(() => {
  if (props.subjectType === 'science_core') return 'Science Core — select students across the grade level'
  if (props.subjectType === 'elective') return 'Elective — select students across the permitted grade level(s)'
  return 'Regular class — enrolled students are limited to this section'
})

const students       = ref([])
const saving         = ref(false)
const autoPopulating = ref(false)
const searchQuery    = ref('')
const candidates     = ref([])
const searching      = ref(false)
let searchTimer = null

function resetStudents() {
  students.value = props.initialStudents.map((s, i) => ({
    id:              s.id ?? null,
    sequence_number: s.sequence_number ?? (i + 1),
    family_name:     s.family_name ?? '',
    given_name:      s.given_name ?? '',
    middle_initial:  s.middle_initial ?? '',
    sex:             s.sex ?? 'M',
    student_id:      s.student_id ?? null,
    _delete:         false,
  }))
}

watch(() => props.show, (visible) => {
  if (!visible) return
  resetStudents()
  searchQuery.value = ''
  candidates.value = []
}, { immediate: true })

watch(searchQuery, () => {
  clearTimeout(searchTimer)
  if (searchQuery.value.trim().length < 2) {
    candidates.value = []
    return
  }
  searchTimer = setTimeout(searchCandidates, 250)
})

onBeforeUnmount(() => clearTimeout(searchTimer))

async function searchCandidates() {
  searching.value = true
  try {
    const { data } = await axios.get(
      route('class-records.students.candidates', {
        classRecord: props.classRecordId,
        q: props.quarter,
      }),
      { params: { search: searchQuery.value.trim() } },
    )
    const locallySelected = new Set(
      students.value.filter(s => !s._delete && s.student_id).map(s => Number(s.student_id)),
    )
    candidates.value = (data.students ?? []).filter(c => !locallySelected.has(Number(c.student_id)))
  } catch (err) {
    candidates.value = []
    Swal.fire('Search Error', err.response?.data?.message ?? 'Failed to search enrolled students.', 'error')
  } finally {
    searching.value = false
  }
}

function addCandidate(candidate) {
  const existing = students.value.find(s => Number(s.student_id) === Number(candidate.student_id))
  if (existing) {
    existing._delete = false
  } else {
    students.value.push({
      id: null,
      sequence_number: students.value.filter(s => !s._delete).length + 1,
      family_name: candidate.family_name,
      given_name: candidate.given_name,
      middle_initial: candidate.middle_initial,
      sex: candidate.sex,
      student_id: candidate.student_id,
      _delete: false,
    })
  }
  resequence()
  candidates.value = candidates.value.filter(c => c.student_id !== candidate.student_id)
  searchQuery.value = ''
}

function removeRow(idx) {
  students.value[idx]._delete = true
  resequence()
}

function resequence() {
  let sequence = 1
  students.value.filter(s => !s._delete).forEach(s => { s.sequence_number = sequence++ })
}

async function autoPopulate() {
  const confirm = await Swal.fire({
    title: 'Populate from Enrollment?',
    text: 'This will replace the active roster with all currently enrolled students in this section.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, populate',
  })
  if (!confirm.isConfirmed) return

  autoPopulating.value = true
  try {
    const { data } = await axios.get(
      route('class-records.students.from-enrollment', {
        classRecord: props.classRecordId,
        q: props.quarter,
      }),
    )
    students.value = (data.students ?? []).map(row => ({ ...row, id: null }))
    Swal.fire({
      icon: 'success',
      title: `${data.count} student(s) loaded.`,
      text: 'Review the roster, then select Save Roster.',
      timer: 1600,
      showConfirmButton: false,
    })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to load from enrollment.', 'error')
  } finally {
    autoPopulating.value = false
  }
}

async function save() {
  const rows = students.value.filter(s => !s._delete)
  if (!rows.length) {
    await Swal.fire('Validation', 'At least one student is required.', 'warning')
    return
  }

  saving.value = true
  try {
    await axios.post(
      route('class-records.students.upsert', {
        classRecord: props.classRecordId,
        q: props.quarter,
      }),
      { students: rows },
    )
    emit('saved')
    emit('close')
  } catch (err) {
    const errors = Object.values(err.response?.data?.errors ?? {}).flat()
    Swal.fire('Error', errors[0] ?? err.response?.data?.message ?? 'Failed to save students.', 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal :show="show" :title="modalTitle" size="3xl" body-class="" @close="$emit('close')">
    <div class="px-6 py-3 border-b border-slate-100 bg-slate-50 space-y-3 shrink-0">
      <div class="flex flex-wrap items-center gap-2">
        <span
          class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium mr-auto"
          :class="isCrossSection ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700'"
        >
          {{ scopeLabel }}
        </span>

        <button
          v-if="!isCrossSection && sectionId"
          @click="autoPopulate"
          :disabled="autoPopulating"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-indigo-300 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-medium transition-colors"
        >
          <BoltIcon class="h-3.5 w-3.5" />
          {{ autoPopulating ? 'Loading…' : 'Populate from Enrollment' }}
        </button>
      </div>

      <div class="relative">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Search enrolled students by name, LRN, or Pisay ID…"
          class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />

        <div
          v-if="searchQuery.trim().length >= 2"
          class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
        >
          <p v-if="searching" class="px-4 py-3 text-sm text-slate-400">Searching enrolled students…</p>
          <p v-else-if="!candidates.length" class="px-4 py-3 text-sm text-slate-400">
            No eligible students found in this class scope.
          </p>
          <button
            v-for="candidate in candidates"
            :key="candidate.student_id"
            type="button"
            @click="addCandidate(candidate)"
            class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-2.5 text-left last:border-0 hover:bg-indigo-50"
          >
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800">
                {{ candidate.family_name }}, {{ candidate.given_name }} {{ candidate.middle_initial }}
              </p>
              <p class="text-xs text-slate-500">
                Grade {{ candidate.grade_level }}
                <span v-if="candidate.homeroom_section"> · {{ candidate.homeroom_section }}</span>
                <span v-if="candidate.lrn"> · LRN {{ candidate.lrn }}</span>
              </p>
            </div>
            <UserPlusIcon class="h-4 w-4 shrink-0 text-indigo-600" />
          </button>
        </div>
      </div>
    </div>

    <div class="overflow-y-auto flex-1">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 sticky top-0">
          <tr>
            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-12">#</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Student</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-20">Sex</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-24">Link</th>
            <th class="px-3 py-2 w-10"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <template v-for="(student, idx) in students" :key="student.id ?? `student-${student.student_id ?? idx}`">
            <tr v-if="!student._delete" class="hover:bg-slate-50/50">
              <td class="px-3 py-2 text-xs text-slate-400">{{ student.sequence_number }}</td>
              <td class="px-3 py-2">
                <p class="font-medium text-slate-800">
                  {{ student.family_name }}, {{ student.given_name }}
                  <span v-if="student.middle_initial">{{ student.middle_initial }}.</span>
                </p>
                <p v-if="!student.student_id" class="mt-0.5 text-xs text-amber-600">
                  Legacy name-only row. Saving will link a unique exact enrollment match; otherwise remove it and select the student above.
                </p>
              </td>
              <td class="px-3 py-2 text-slate-600">{{ student.sex }}</td>
              <td class="px-3 py-2">
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                  :class="student.student_id ? 'bg-success-50 text-success-700' : 'bg-amber-100 text-amber-700'"
                >
                  {{ student.student_id ? 'Linked' : 'Unlinked' }}
                </span>
              </td>
              <td class="px-3 py-2 text-center">
                <button @click="removeRow(idx)" class="p-1 rounded hover:bg-red-50 text-slate-400 hover:text-red-600">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </td>
            </tr>
          </template>
          <tr v-if="!students.some(s => !s._delete)">
            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">
              Search above to add an enrolled student.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <template #footer>
      <AppButton variant="secondary" @click="$emit('close')">Cancel</AppButton>
      <AppButton :loading="saving" :disabled="saving" @click="save">
        {{ saving ? 'Saving…' : 'Save Roster' }}
      </AppButton>
    </template>
  </AppModal>
</template>
