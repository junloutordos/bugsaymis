<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import {
  AcademicCapIcon,
  DocumentArrowDownIcon,
  TrophyIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  student:           Object,
  schoolYears:       Array,
  selectedSchoolYear:Number,
  selectedSYName:    String,
  enrollment:        Object,
  grades:            Array,
  standing:          Object,
  reportCardUrl:     String,
})

const schoolYearId = ref(props.selectedSchoolYear)
watch(schoolYearId, (val) => {
  router.get(route('student-portal.grades'), { school_year_id: val }, { preserveState: true })
})

function geColor(ge) {
  if (ge == null) return 'text-slate-400'
  const v = parseFloat(ge)
  if (v <= 1.5) return 'text-green-700 font-semibold'
  if (v <= 2.5) return 'text-slate-700'
  if (v <= 3.0) return 'text-amber-600'
  return 'text-red-600 font-semibold'
}
</script>

<template>
  <Head title="My Grades" />
  <StudentPortalLayout>
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <AcademicCapIcon class="w-6 h-6 text-indigo-600" />
          <h1 class="text-lg font-semibold text-slate-800">My Grades</h1>
        </div>

        <div class="flex items-center gap-3">
          <select
            v-model="schoolYearId"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              S.Y. {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
            </option>
          </select>

          <a
            v-if="reportCardUrl"
            :href="reportCardUrl"
            target="_blank"
            class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
          >
            <DocumentArrowDownIcon class="w-4 h-4" />
            Download Report Card
          </a>
        </div>
      </div>

      <!-- Enrollment status -->
      <div
        v-if="enrollment"
        class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap items-center gap-4"
      >
        <div class="flex items-center gap-2">
          <CheckCircleIcon class="w-5 h-5 text-green-600" />
          <div>
            <p class="text-sm font-medium text-slate-800">
              Grade {{ enrollment.grade_level }} — {{ enrollment.section_name }}
            </p>
            <p class="text-xs text-slate-500">S.Y. {{ selectedSYName }} · {{ enrollment.status }}</p>
          </div>
        </div>
      </div>
      <div v-else class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-amber-700 text-sm">
        You are not enrolled for school year {{ selectedSYName }}.
      </div>

      <!-- Academic standing summary -->
      <div v-if="standing" class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Academic Standing</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div class="bg-slate-50 rounded-lg p-3 text-center">
            <p class="text-xs text-slate-500 mb-1">GWA</p>
            <p class="text-lg font-bold" :class="geColor(standing.gwa)">
              {{ standing.gwa ? parseFloat(standing.gwa).toFixed(3) : '—' }}
            </p>
          </div>
          <div class="bg-slate-50 rounded-lg p-3 text-center">
            <p class="text-xs text-slate-500 mb-1">Subjects</p>
            <p class="text-lg font-bold text-slate-700">{{ standing.subject_count }}</p>
          </div>
          <div class="bg-slate-50 rounded-lg p-3 text-center">
            <p class="text-xs text-slate-500 mb-1">Failed</p>
            <p class="text-lg font-bold" :class="standing.failed_subject_count > 0 ? 'text-red-600' : 'text-slate-700'">
              {{ standing.failed_subject_count }}
            </p>
          </div>
          <div class="bg-amber-50 rounded-lg p-3 text-center">
            <p class="text-xs text-slate-500 mb-1">Honors</p>
            <p class="text-sm font-bold text-amber-700">
              {{ standing.honors !== 'None' ? standing.honors : '—' }}
            </p>
          </div>
        </div>

        <div v-if="standing.honors && standing.honors !== 'None'" class="mt-3 flex items-center gap-2 text-amber-700">
          <TrophyIcon class="w-4 h-4" />
          <p class="text-sm font-semibold">{{ standing.honors }}</p>
        </div>
      </div>

      <!-- Grades table -->
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700">Subject Grades — S.Y. {{ selectedSYName }}</h2>
        </div>

        <div v-if="grades.length === 0" class="py-12 text-center text-slate-400 text-sm">
          No grade data available for this school year yet.
        </div>

        <table v-else class="min-w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Subject</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Q1</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Q2</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Q3</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Q4</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Final</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Remarks</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="g in grades" :key="g.subject_name" class="hover:bg-slate-50">
              <td class="px-4 py-2.5 font-medium text-slate-800">{{ g.subject_name }}</td>
              <td class="px-4 py-2.5 text-center tabular-nums" :class="geColor(g.q1_ge)">{{ g.q1_ge ?? '—' }}</td>
              <td class="px-4 py-2.5 text-center tabular-nums" :class="geColor(g.q2_ge)">{{ g.q2_ge ?? '—' }}</td>
              <td class="px-4 py-2.5 text-center tabular-nums" :class="geColor(g.q3_ge)">{{ g.q3_ge ?? '—' }}</td>
              <td class="px-4 py-2.5 text-center tabular-nums" :class="geColor(g.q4_ge)">{{ g.q4_ge ?? '—' }}</td>
              <td class="px-4 py-2.5 text-center tabular-nums font-bold" :class="geColor(g.final_ge)">{{ g.final_ge ?? '—' }}</td>
              <td class="px-4 py-2.5 text-center">
                <span :class="[
                  'text-xs px-2 py-0.5 rounded-full font-medium',
                  g.passed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                ]">{{ g.remarks }}</span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- GE scale note -->
        <div class="px-5 py-3 border-t border-slate-100 text-xs text-slate-400">
          Grade Equivalents (GE): 1.0 Outstanding · 1.5 Very Satisfactory · 2.0 Satisfactory ·
          2.5 Fairly Satisfactory · 3.0 Did Not Meet Expectations · 5.0 Failed
        </div>
      </div>

    </div>
  </StudentPortalLayout>
</template>
