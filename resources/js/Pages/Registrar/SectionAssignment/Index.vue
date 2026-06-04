<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  CheckCircleIcon,
  SparklesIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  students:    Object,  // { 8: [...], 9: [...], 10: [...], 11: [...], 12: [...] }
  sections:    Object,  // { 8: [...], 9: [...], ... }
  alreadyDone: Boolean,
  currentSyId: Number,
})

// ── State ─────────────────────────────────────────────────────────────────────

// Derive active grade levels from whatever students exist (supports G7 new enrollees + G8-12 legacy)
const gradeLevels = computed(() =>
  Object.keys(props.students ?? {}).map(Number).sort((a, b) => a - b)
)
const activeTab = ref(gradeLevels.value[0] ?? 7)
const submitting    = ref(false)
const flashSuccess  = ref('')
const flashError    = ref('')

// assignments[student_id] = section_id (number or null)
const assignments = ref({})

// Pre-populate with null for all students
const allStudents = computed(() => {
  const out = {}
  for (const grade of gradeLevels) {
    for (const s of (props.students[grade] ?? [])) {
      if (!(s.student_id in out)) out[s.student_id] = null
    }
  }
  return out
})

// Initialise on mount
for (const grade of gradeLevels.value) {
  for (const s of (props.students[grade] ?? [])) {
    assignments.value[s.student_id] = null
  }
}

// ── Derived counts ────────────────────────────────────────────────────────────

function sectionCounts(grade) {
  const counts = {}
  for (const sec of (props.sections[grade] ?? [])) {
    counts[sec.id] = { total: 0, male: 0, female: 0 }
  }
  for (const s of (props.students[grade] ?? [])) {
    const sid = assignments.value[s.student_id]
    if (sid && counts[sid]) {
      counts[sid].total++
      if (s.sex === 'Male')   counts[sid].male++
      if (s.sex === 'Female') counts[sid].female++
    }
  }
  return counts
}

const totalAssigned = computed(() => {
  return Object.values(assignments.value).filter(v => v !== null).length
})

const totalStudents = computed(() => {
  return gradeLevels.value.reduce((sum, g) => sum + (props.students[g]?.length ?? 0), 0)
})

const allAssigned = computed(() => totalAssigned.value === totalStudents.value)

// ── Auto-balance M/F ──────────────────────────────────────────────────────────

function shuffle(arr) {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]]
  }
  return arr
}

function autoBalance(grade) {
  const students  = props.students[grade] ?? []
  const sections  = props.sections[grade] ?? []
  if (!students.length || !sections.length) return

  const males   = shuffle(students.filter(s => s.sex === 'Male').map(s => s.student_id))
  const females = shuffle(students.filter(s => s.sex === 'Female').map(s => s.student_id))
  const others  = shuffle(students.filter(s => s.sex !== 'Male' && s.sex !== 'Female').map(s => s.student_id))

  // Interleave M/F, append unknowns at end
  const interleaved = []
  const maxLen = Math.max(males.length, females.length)
  for (let i = 0; i < maxLen; i++) {
    if (i < males.length)   interleaved.push(males[i])
    if (i < females.length) interleaved.push(females[i])
  }
  interleaved.push(...others)

  // Round-robin across sections
  interleaved.forEach((sid, idx) => {
    assignments.value[sid] = sections[idx % sections.length].id
  })
}

function autoBalanceAll() {
  for (const grade of gradeLevels.value) autoBalance(grade)
}

// ── Submit ────────────────────────────────────────────────────────────────────

function submit() {
  flashError.value = ''

  // Validate all assigned
  const unassigned = Object.entries(assignments.value).filter(([, v]) => v === null)
  if (unassigned.length) {
    flashError.value = `${unassigned.length} student(s) still have no section assigned.`
    return
  }

  const payload = Object.entries(assignments.value).map(([studentId, sectionId]) => ({
    student_id: parseInt(studentId),
    section_id: sectionId,
  }))

  submitting.value = true
  router.post(route('registrar.section-assignment.store'), { assignments: payload }, {
    onError:  (errors) => {
      flashError.value = Object.values(errors)[0] ?? 'An error occurred.'
      submitting.value = false
    },
    onFinish: () => { submitting.value = false },
  })
}
</script>

<template>
  <Head title="Section Assignment" />
  <AdminLayout title="Section Assignment">

    <!-- Already done banner -->
    <div v-if="alreadyDone"
         class="mb-6 flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
      <CheckCircleIcon class="h-5 w-5 shrink-0 text-green-500" />
      <span>Enrollment records already exist for SY 2026-2027. Section Assignment is no longer available.</span>
    </div>

    <template v-else>

      <!-- Header row -->
      <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-base font-semibold text-slate-800">Legacy Import — SY 2026-2027</h2>
          <p class="text-xs text-slate-500 mt-0.5">
            Assign {{ totalStudents }} continuing students (Grades 8–12) to sections before confirming enrollment.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs text-slate-500">{{ totalAssigned }} / {{ totalStudents }} assigned</span>
          <button @click="autoBalanceAll"
                  class="flex items-center gap-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 px-3 py-2 text-xs font-medium text-white">
            <SparklesIcon class="h-4 w-4" />
            Auto-Balance All (M/F)
          </button>
          <button @click="submit"
                  :disabled="submitting || !allAssigned"
                  class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-white transition"
                  :class="allAssigned && !submitting
                    ? 'bg-indigo-600 hover:bg-indigo-700'
                    : 'bg-slate-300 cursor-not-allowed'">
            <CheckCircleIcon class="h-4 w-4" />
            {{ submitting ? 'Enrolling…' : 'Confirm Enrollment' }}
          </button>
        </div>
      </div>

      <!-- Flash error -->
      <div v-if="flashError"
           class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
        {{ flashError }}
      </div>

      <!-- Progress bar -->
      <div class="mb-5 h-2 w-full rounded-full bg-slate-100">
        <div class="h-2 rounded-full bg-indigo-500 transition-all duration-300"
             :style="{ width: totalStudents ? (totalAssigned / totalStudents * 100) + '%' : '0%' }"></div>
      </div>

      <!-- Grade tabs -->
      <div class="mb-4 flex gap-1 border-b border-slate-200">
        <button v-for="grade in gradeLevels" :key="grade"
                @click="activeTab = grade"
                class="px-4 py-2 text-sm font-medium transition border-b-2 -mb-px"
                :class="activeTab === grade
                  ? 'border-indigo-600 text-indigo-600'
                  : 'border-transparent text-slate-500 hover:text-slate-700'">
          Grade {{ grade }}
          <span class="ml-1 text-xs">({{ (students[grade] ?? []).length }})</span>
        </button>
      </div>

      <!-- Per-grade panel -->
      <div v-for="grade in gradeLevels" :key="grade" v-show="activeTab === grade">

        <!-- Section capacity summary for this grade -->
        <div class="mb-4 flex flex-wrap gap-3">
          <div v-for="sec in (sections[grade] ?? [])" :key="sec.id"
               class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
            <UserGroupIcon class="h-4 w-4 text-slate-400" />
            <span class="font-medium text-slate-700">{{ sec.sectionname }}</span>
            <span class="text-slate-400">·</span>
            <span class="text-blue-600 font-semibold">{{ sectionCounts(grade)[sec.id]?.male ?? 0 }}M</span>
            <span class="text-pink-500 font-semibold">{{ sectionCounts(grade)[sec.id]?.female ?? 0 }}F</span>
            <span class="text-slate-500">/ {{ sec.capacity }}</span>
          </div>

          <button @click="autoBalance(grade)"
                  class="ml-auto flex items-center gap-1 rounded-lg border border-violet-300 bg-violet-50 px-3 py-2 text-xs font-medium text-violet-700 hover:bg-violet-100">
            <SparklesIcon class="h-3.5 w-3.5" />
            Auto-Balance Grade {{ grade }}
          </button>
        </div>

        <!-- Student table -->
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
              <tr>
                <th class="px-4 py-2 text-left w-8">#</th>
                <th class="px-4 py-2 text-left">Name</th>
                <th class="px-4 py-2 text-left w-20">Sex</th>
                <th class="px-4 py-2 text-left w-24">PISAY ID</th>
                <th class="px-4 py-2 text-left w-52">Section</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(student, idx) in (students[grade] ?? [])" :key="student.student_id"
                  class="hover:bg-slate-50/60">
                <td class="px-4 py-2 text-slate-400 text-xs">{{ idx + 1 }}</td>
                <td class="px-4 py-2 font-medium text-slate-800">{{ student.full_name }}</td>
                <td class="px-4 py-2">
                  <span :class="student.sex === 'Male'
                    ? 'text-blue-600 font-medium'
                    : student.sex === 'Female'
                    ? 'text-pink-500 font-medium'
                    : 'text-slate-400'">
                    {{ student.sex || '—' }}
                  </span>
                </td>
                <td class="px-4 py-2 text-slate-500 text-xs font-mono">{{ student.pisays_id || '—' }}</td>
                <td class="px-4 py-2">
                  <select v-model="assignments[student.student_id]"
                          class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                          :class="!assignments[student.student_id] ? 'text-slate-400' : 'text-slate-800'">
                    <option :value="null">— Select section —</option>
                    <option v-for="sec in (sections[grade] ?? [])" :key="sec.id" :value="sec.id">
                      {{ sec.sectionname }}
                    </option>
                  </select>
                </td>
              </tr>
              <tr v-if="!(students[grade] ?? []).length">
                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No students for this grade.</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div><!-- end per-grade panel -->

    </template>

  </AdminLayout>
</template>
