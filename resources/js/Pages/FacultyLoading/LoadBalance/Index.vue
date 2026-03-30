<template>
  <Head title="Load Balancing Optimization" />
  <AdminLayout title="Load Balancing Optimization">
    <div class="space-y-5">

      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
            <ScaleIcon class="h-5 w-5 text-indigo-500" />
            Load Balancing Optimization
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">
            Analyse faculty workloads and get intelligent rebalancing suggestions.
          </p>
        </div>
      </div>

      <!-- Alert -->
      <div v-if="alert.message" :class="[
          'rounded-lg px-4 py-3 text-sm flex items-start gap-2',
          alert.type === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700'
            : alert.type === 'warning' ? 'bg-amber-50 border border-amber-200 text-amber-700'
            : 'bg-red-50 border border-red-200 text-red-700'
        ]">
        <CheckCircleIcon v-if="alert.type === 'success'" class="h-4 w-4 mt-0.5 shrink-0" />
        <ExclamationTriangleIcon v-else class="h-4 w-4 mt-0.5 shrink-0" />
        <span>{{ alert.message }}</span>
      </div>

      <!-- ── Term Selector + Analyse ─────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row gap-4 items-end">
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 mb-1">School Year</label>
            <select v-model="form.school_year_id" @change="onSYChange"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null" disabled>Select school year…</option>
              <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
                {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
              </option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term</label>
            <select v-model="form.academic_term_id" :disabled="!form.school_year_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent disabled:opacity-50">
              <option :value="null" disabled>Select term…</option>
              <option v-for="t in selectedTerms" :key="t.id" :value="t.id">
                {{ t.name }}{{ t.is_current ? ' (current)' : '' }}
              </option>
            </select>
          </div>
          <button @click="runAnalysis"
            :disabled="!form.academic_term_id || analysing"
            class="inline-flex items-center gap-2 px-5 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
            <ArrowPathIcon v-if="analysing" class="h-4 w-4 animate-spin" />
            <MagnifyingGlassIcon v-else class="h-4 w-4" />
            {{ analysing ? 'Analysing…' : 'Analyse' }}
          </button>
        </div>
      </div>

      <!-- ── Analysis Results ──────────────────────────────────────── -->
      <template v-if="analysis">

        <!-- Term label -->
        <div class="text-sm text-slate-500">
          Results for: <strong class="text-slate-700">{{ analysis.term.full_label }}</strong>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-slate-700">{{ analysis.summary.faculty_count }}</p>
            <p class="text-xs text-slate-500 mt-1">Faculty</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-slate-700">{{ analysis.summary.average_load }}</p>
            <p class="text-xs text-slate-500 mt-1">Avg Units</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-slate-700">± {{ analysis.summary.std_deviation }}</p>
            <p class="text-xs text-slate-500 mt-1">Std Deviation</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-red-600">{{ analysis.summary.overloaded }}</p>
            <p class="text-xs text-slate-500 mt-1">Overloaded</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-amber-600">{{ analysis.summary.underloaded }}</p>
            <p class="text-xs text-slate-500 mt-1">Underloaded</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-emerald-600">{{ analysis.summary.full_load }}</p>
            <p class="text-xs text-slate-500 mt-1">Full Load</p>
          </div>
        </div>

        <!-- Workload Distribution Chart -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h2 class="text-sm font-semibold text-slate-700 mb-4">Workload Distribution</h2>
          <div class="space-y-2.5">
            <div v-for="f in sortedFaculty" :key="f.user_id" class="flex items-center gap-3">
              <!-- Name + status -->
              <div class="w-44 shrink-0 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full shrink-0" :class="statusDotClass(f.load_status)" />
                <span class="text-xs text-slate-700 truncate">{{ f.faculty_name }}</span>
              </div>

              <!-- Bar -->
              <div class="flex-1 relative h-5 bg-slate-100 rounded-full overflow-hidden">
                <!-- Full-load marker line at 18 units -->
                <div class="absolute top-0 bottom-0 w-px bg-slate-400 z-10"
                  :style="{ left: barPct(18) + '%' }" />
                <!-- Fill -->
                <div class="h-full rounded-full transition-all duration-500"
                  :class="barClass(f.load_status)"
                  :style="{ width: barPct(f.total_units) + '%' }" />
              </div>

              <!-- Total units -->
              <span class="text-xs font-semibold w-14 text-right tabular-nums"
                :class="statusTextClass(f.load_status)">
                {{ f.total_units }} u
              </span>

              <!-- Flags -->
              <div class="flex items-center gap-1 w-6">
                <ExclamationTriangleIcon v-if="f.flags.length"
                  class="h-4 w-4 text-amber-400 shrink-0"
                  :title="f.flags.map(flag => flag.message).join('\n')" />
              </div>
            </div>
          </div>
          <!-- Legend -->
          <div class="flex items-center gap-4 mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 inline-block" /> Full Load (18 u)</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block" /> Overloaded</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block" /> Underloaded</span>
            <span class="flex items-center gap-1.5"><span class="w-px h-3.5 bg-slate-400 inline-block" /> = 18 unit threshold</span>
          </div>
        </div>

        <!-- Faculty Detail Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div v-for="f in analysis.faculty" :key="f.user_id"
            class="bg-white rounded-xl border shadow-sm overflow-hidden"
            :class="f.flags.length ? 'border-amber-200' : 'border-slate-100'">

            <!-- Card header -->
            <div class="px-4 py-3 flex items-center justify-between border-b"
              :class="f.flags.length ? 'border-amber-100 bg-amber-50/40' : 'border-slate-100 bg-slate-50'">
              <div>
                <p class="text-sm font-semibold text-slate-800">{{ f.faculty_name }}</p>
                <p class="text-xs text-slate-500">{{ f.position || '—' }}</p>
              </div>
              <div class="flex items-center gap-2">
                <span v-if="f.is_locked" class="text-xs bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full">🔒 Locked</span>
                <span :class="loadStatusBadge(f.load_status)">{{ statusLabel(f.load_status) }}</span>
              </div>
            </div>

            <!-- Load unit breakdown -->
            <div class="px-4 py-3">
              <div class="grid grid-cols-3 gap-2 text-xs mb-3">
                <div class="text-center">
                  <p class="text-lg font-bold text-slate-800">{{ f.total_units }}</p>
                  <p class="text-slate-400">Total</p>
                </div>
                <div class="text-center">
                  <p class="text-lg font-bold text-indigo-600">{{ f.teaching_units }}</p>
                  <p class="text-slate-400">Teaching</p>
                </div>
                <div class="text-center">
                  <p class="text-lg font-bold text-purple-600">{{ f.subjects_count }}</p>
                  <p class="text-slate-400">Preparations</p>
                </div>
              </div>

              <!-- Unit type bars -->
              <div class="space-y-1 text-xs">
                <div v-for="type in unitTypes" :key="type.key" class="flex items-center gap-2">
                  <span class="w-20 text-slate-500">{{ type.label }}</span>
                  <div class="flex-1 bg-slate-100 rounded-full h-1.5">
                    <div class="h-full rounded-full"
                      :class="type.color"
                      :style="{ width: Math.min(100, (f[type.key] / 18) * 100) + '%' }" />
                  </div>
                  <span class="w-8 text-right text-slate-600 tabular-nums">{{ f[type.key] }}</span>
                </div>
              </div>

              <!-- Daily hours -->
              <div v-if="Object.keys(f.daily_hours).length" class="mt-3 pt-2 border-t border-slate-50">
                <p class="text-xs text-slate-400 mb-1.5">Daily Teaching Hours</p>
                <div class="flex gap-2">
                  <div v-for="day in weekDays" :key="day"
                    class="flex-1 text-center">
                    <div class="h-8 relative bg-slate-100 rounded-sm overflow-hidden mb-0.5">
                      <div class="absolute bottom-0 left-0 right-0 rounded-sm transition-all"
                        :class="(f.daily_hours[day] ?? 0) > 6 ? 'bg-red-400' : 'bg-indigo-300'"
                        :style="{ height: Math.min(100, ((f.daily_hours[day] ?? 0) / 8) * 100) + '%' }" />
                    </div>
                    <span class="text-[9px] text-slate-400">{{ day.slice(0, 2) }}</span>
                  </div>
                </div>
              </div>

              <!-- Flags -->
              <div v-if="f.flags.length" class="mt-3 space-y-1.5">
                <div v-for="flag in f.flags" :key="flag.type"
                  class="flex items-start gap-1.5 text-xs"
                  :class="flagTextClass(flag.severity)">
                  <ExclamationTriangleIcon v-if="flag.severity !== 'info'" class="h-3.5 w-3.5 mt-0.5 shrink-0" />
                  <InformationCircleIcon v-else class="h-3.5 w-3.5 mt-0.5 shrink-0" />
                  {{ flag.message }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Suggestions Panel ──────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
              <LightBulbIcon class="h-4 w-4 text-amber-400" />
              Rebalancing Suggestions
            </h2>
            <button @click="loadSuggestions"
              :disabled="loadingSuggestions"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg font-medium transition-colors disabled:opacity-50">
              <ArrowPathIcon v-if="loadingSuggestions" class="h-3.5 w-3.5 animate-spin" />
              <SparklesIcon v-else class="h-3.5 w-3.5" />
              {{ loadingSuggestions ? 'Generating…' : 'Generate Suggestions' }}
            </button>
          </div>

          <!-- Not loaded yet -->
          <div v-if="suggestions === null" class="py-10 text-center text-slate-400 text-sm">
            Click "Generate Suggestions" to get rebalancing recommendations.
          </div>

          <!-- No suggestions -->
          <div v-else-if="suggestions.length === 0" class="py-10 text-center">
            <CheckCircleIcon class="mx-auto h-10 w-10 text-emerald-300 mb-2" />
            <p class="text-sm font-medium text-slate-600">Load is well balanced.</p>
            <p class="text-xs text-slate-400 mt-1">No significant rebalancing improvements found.</p>
          </div>

          <!-- Suggestion list -->
          <div v-else class="divide-y divide-slate-50">
            <div v-for="(s, idx) in suggestions" :key="idx"
              class="px-5 py-4 hover:bg-slate-50/50 transition-colors">

              <!-- Transfer suggestion -->
              <template v-if="s.type === 'transfer'">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Transfer</span>
                      <span class="text-xs text-slate-400">+{{ s.improvement_score }} unit improvement</span>
                    </div>
                    <p class="text-sm text-slate-700">{{ s.explanation }}</p>
                    <div class="mt-2 flex items-center gap-4 text-xs text-slate-500">
                      <span>
                        <strong>{{ s.from_faculty_name }}</strong>:
                        {{ s.from_current_total }} → <span :class="statusTextClass(s.from_new_status)">{{ s.from_new_total }} u</span>
                      </span>
                      <span>→</span>
                      <span>
                        <strong>{{ s.to_faculty_name }}</strong>:
                        {{ s.to_current_total }} → <span :class="statusTextClass(s.to_new_status)">{{ s.to_new_total }} u</span>
                      </span>
                    </div>
                  </div>
                  <button @click="openConfirm(s, idx)"
                    :disabled="applying === idx"
                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
                    <ArrowPathIcon v-if="applying === idx" class="h-3 w-3 animate-spin" />
                    <CheckIcon v-else class="h-3 w-3" />
                    Apply
                  </button>
                </div>
              </template>

              <!-- Swap suggestion -->
              <template v-else-if="s.type === 'swap'">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">Swap</span>
                      <span class="text-xs text-slate-400">+{{ s.improvement_score }} unit improvement</span>
                    </div>
                    <p class="text-sm text-slate-700">{{ s.explanation }}</p>
                    <div class="mt-2 flex items-center gap-4 text-xs text-slate-500">
                      <span>
                        <strong>{{ s.faculty_a_name }}</strong>:
                        {{ s.faculty_a_current_total }} → <span :class="statusTextClass(s.faculty_a_new_status)">{{ s.faculty_a_new_total }} u</span>
                      </span>
                      <span>⇄</span>
                      <span>
                        <strong>{{ s.faculty_b_name }}</strong>:
                        {{ s.faculty_b_current_total }} → <span :class="statusTextClass(s.faculty_b_new_status)">{{ s.faculty_b_new_total }} u</span>
                      </span>
                    </div>
                  </div>
                  <button @click="openConfirm(s, idx)"
                    :disabled="applying === idx"
                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
                    <ArrowPathIcon v-if="applying === idx" class="h-3 w-3 animate-spin" />
                    <CheckIcon v-else class="h-3 w-3" />
                    Apply
                  </button>
                </div>
              </template>
            </div>
          </div>
        </div>

      </template>

    </div>

    <!-- ── Simulation Confirmation Modal ─────────────────────────────── -->
    <div v-if="confirm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

        <!-- Modal header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
          <div class="h-9 w-9 rounded-full bg-indigo-50 flex items-center justify-center shrink-0">
            <EyeIcon class="h-5 w-5 text-indigo-500" />
          </div>
          <div>
            <h2 class="text-base font-semibold text-slate-800">Confirm Rebalancing</h2>
            <p class="text-xs text-slate-500 mt-0.5">Preview the impact before applying this change.</p>
          </div>
        </div>

        <!-- Suggestion info -->
        <div class="px-6 py-4 space-y-4">
          <div class="flex items-center gap-2">
            <span v-if="confirm.suggestion?.type === 'transfer'"
              class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Transfer</span>
            <span v-else
              class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">Swap</span>
            <span class="text-xs text-slate-500">+{{ confirm.suggestion?.improvement_score }} unit improvement</span>
          </div>

          <p class="text-sm text-slate-700">{{ confirm.suggestion?.explanation }}</p>

          <!-- Impact table -->
          <div class="rounded-xl border border-slate-100 overflow-hidden">
            <table class="min-w-full text-xs">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-2 text-left font-semibold text-slate-500">Faculty</th>
                  <th class="px-4 py-2 text-center font-semibold text-slate-500">Before</th>
                  <th class="px-4 py-2 text-center font-semibold text-slate-500"></th>
                  <th class="px-4 py-2 text-center font-semibold text-slate-500">After</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">

                <!-- Transfer rows -->
                <template v-if="confirm.suggestion?.type === 'transfer'">
                  <tr>
                    <td class="px-4 py-2.5 font-medium text-slate-700">{{ confirm.suggestion.from_faculty_name }}</td>
                    <td class="px-4 py-2.5 text-center text-slate-500">{{ confirm.suggestion.from_current_total }} u</td>
                    <td class="px-4 py-2.5 text-center text-slate-400">→</td>
                    <td class="px-4 py-2.5 text-center font-semibold" :class="statusTextClass(confirm.suggestion.from_new_status)">
                      {{ confirm.suggestion.from_new_total }} u
                      <span class="ml-1 font-normal text-slate-400">({{ statusLabel(confirm.suggestion.from_new_status) }})</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 font-medium text-slate-700">{{ confirm.suggestion.to_faculty_name }}</td>
                    <td class="px-4 py-2.5 text-center text-slate-500">{{ confirm.suggestion.to_current_total }} u</td>
                    <td class="px-4 py-2.5 text-center text-slate-400">→</td>
                    <td class="px-4 py-2.5 text-center font-semibold" :class="statusTextClass(confirm.suggestion.to_new_status)">
                      {{ confirm.suggestion.to_new_total }} u
                      <span class="ml-1 font-normal text-slate-400">({{ statusLabel(confirm.suggestion.to_new_status) }})</span>
                    </td>
                  </tr>
                </template>

                <!-- Swap rows -->
                <template v-else-if="confirm.suggestion?.type === 'swap'">
                  <tr>
                    <td class="px-4 py-2.5 font-medium text-slate-700">{{ confirm.suggestion.faculty_a_name }}</td>
                    <td class="px-4 py-2.5 text-center text-slate-500">{{ confirm.suggestion.faculty_a_current_total }} u</td>
                    <td class="px-4 py-2.5 text-center text-slate-400">⇄</td>
                    <td class="px-4 py-2.5 text-center font-semibold" :class="statusTextClass(confirm.suggestion.faculty_a_new_status)">
                      {{ confirm.suggestion.faculty_a_new_total }} u
                      <span class="ml-1 font-normal text-slate-400">({{ statusLabel(confirm.suggestion.faculty_a_new_status) }})</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 font-medium text-slate-700">{{ confirm.suggestion.faculty_b_name }}</td>
                    <td class="px-4 py-2.5 text-center text-slate-500">{{ confirm.suggestion.faculty_b_current_total }} u</td>
                    <td class="px-4 py-2.5 text-center text-slate-400">⇄</td>
                    <td class="px-4 py-2.5 text-center font-semibold" :class="statusTextClass(confirm.suggestion.faculty_b_new_status)">
                      {{ confirm.suggestion.faculty_b_new_total }} u
                      <span class="ml-1 font-normal text-slate-400">({{ statusLabel(confirm.suggestion.faculty_b_new_status) }})</span>
                    </td>
                  </tr>
                </template>

              </tbody>
            </table>
          </div>

          <p class="text-xs text-slate-400">
            This action will permanently reassign the load. The analysis will refresh automatically after applying.
          </p>
        </div>

        <!-- Modal actions -->
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
          <button @click="confirm.open = false"
            class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 font-medium">
            Cancel
          </button>
          <button @click="confirmApply"
            :disabled="applying !== null"
            class="inline-flex items-center gap-2 px-5 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
            <ArrowPathIcon v-if="applying !== null" class="h-4 w-4 animate-spin" />
            <CheckIcon v-else class="h-4 w-4" />
            Confirm &amp; Apply
          </button>
        </div>

      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ScaleIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  MagnifyingGlassIcon,
  LightBulbIcon,
  SparklesIcon,
  CheckIcon,
  EyeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  schoolYears: Array,
})

const weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']

const unitTypes = [
  { key: 'teaching_units',     label: 'Teaching',     color: 'bg-indigo-400' },
  { key: 'research_units',     label: 'Research',     color: 'bg-purple-400' },
  { key: 'admin_units',        label: 'Admin',        color: 'bg-amber-400'  },
  { key: 'committee_units',    label: 'Committee',    color: 'bg-teal-400'   },
  { key: 'cocurricular_units', label: 'Co-curr.',     color: 'bg-rose-400'   },
]

// ── Form ────────────────────────────────────────────────────────────────────

const form = reactive({ school_year_id: null, academic_term_id: null })
const alert = reactive({ type: '', message: '' })

const selectedTerms = computed(() =>
  props.schoolYears?.find(sy => sy.id === form.school_year_id)?.terms ?? []
)

function onSYChange() {
  form.academic_term_id = null
  const currentTerm = selectedTerms.value.find(t => t.is_current)
  if (currentTerm) form.academic_term_id = currentTerm.id
}

// Auto-select current school year + term
const currentSY = props.schoolYears?.find(sy => sy.is_current)
if (currentSY) {
  form.school_year_id = currentSY.id
  const ct = currentSY.terms?.find(t => t.is_current)
  if (ct) form.academic_term_id = ct.id
}

function clearAlert() { alert.type = ''; alert.message = '' }

// ── Analysis ─────────────────────────────────────────────────────────────────

const analysing = ref(false)
const analysis  = ref(null)
const suggestions = ref(null)

const sortedFaculty = computed(() => {
  if (!analysis.value?.faculty) return []
  return [...analysis.value.faculty].sort((a, b) => b.total_units - a.total_units)
})

// Chart helpers (bar at most 120% of threshold so overloads are still visible)
const BAR_MAX = 24 // units at 100% width
function barPct(units) { return Math.min(100, (units / BAR_MAX) * 100) }

async function runAnalysis() {
  if (!form.academic_term_id || analysing.value) return
  analysing.value = true
  analysis.value  = null
  suggestions.value = null
  clearAlert()

  try {
    const { data } = await axios.get('/faculty-loading/load-balance/analysis', {
      params: { academic_term_id: form.academic_term_id },
    })
    analysis.value = data
  } catch (err) {
    alert.type    = 'error'
    alert.message = err.response?.data?.message ?? 'Failed to load analysis.'
  } finally {
    analysing.value = false
  }
}

// ── Suggestions ───────────────────────────────────────────────────────────────

const loadingSuggestions = ref(false)

async function loadSuggestions() {
  if (!form.academic_term_id || loadingSuggestions.value) return
  loadingSuggestions.value = true
  clearAlert()

  try {
    const { data } = await axios.post('/faculty-loading/load-balance/suggest', {
      academic_term_id: form.academic_term_id,
    })
    suggestions.value = data.suggestions
  } catch (err) {
    alert.type    = 'error'
    alert.message = err.response?.data?.message ?? 'Failed to generate suggestions.'
  } finally {
    loadingSuggestions.value = false
  }
}

// ── Simulation / Confirm Modal ────────────────────────────────────────────────

const confirm = reactive({ open: false, suggestion: null, idx: null })

function openConfirm(suggestion, idx) {
  confirm.suggestion = suggestion
  confirm.idx        = idx
  confirm.open       = true
}

// ── Apply ────────────────────────────────────────────────────────────────────

const applying = ref(null)

async function confirmApply() {
  const s = confirm.suggestion
  if (!s) return
  confirm.open = false

  if (s.type === 'transfer') {
    await applyTransfer(s, confirm.idx)
  } else {
    await applySwap(s, confirm.idx)
  }
}

async function applyTransfer(suggestion, idx) {
  applying.value = idx
  clearAlert()

  try {
    const { data } = await axios.post('/faculty-loading/load-balance/apply', {
      type:               'transfer',
      load_assignment_id: suggestion.load_assignment_id,
      to_user_id:         suggestion.to_user_id,
    })
    alert.type    = 'success'
    alert.message = data.message
    // Refresh analysis and suggestions
    await runAnalysis()
    await loadSuggestions()
  } catch (err) {
    alert.type    = 'error'
    alert.message = err.response?.data?.message ?? 'Failed to apply transfer.'
  } finally {
    applying.value = null
  }
}

async function applySwap(suggestion, idx) {
  applying.value = idx
  clearAlert()

  try {
    const { data } = await axios.post('/faculty-loading/load-balance/apply', {
      type:             'swap',
      assignment_a_id:  suggestion.assignment_a_id,
      assignment_b_id:  suggestion.assignment_b_id,
    })
    alert.type    = 'success'
    alert.message = data.message
    await runAnalysis()
    await loadSuggestions()
  } catch (err) {
    alert.type    = 'error'
    alert.message = err.response?.data?.message ?? 'Failed to apply swap.'
  } finally {
    applying.value = null
  }
}

// ── Style helpers ─────────────────────────────────────────────────────────────

function statusLabel(status) {
  return { overload: 'Overloaded', full_load: 'Full Load', underload: 'Underloaded' }[status] ?? status
}

function loadStatusBadge(status) {
  return {
    overload:  'inline-flex text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium',
    full_load: 'inline-flex text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-medium',
    underload: 'inline-flex text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium',
  }[status] ?? 'inline-flex text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full'
}

function statusDotClass(status) {
  return {
    overload:  'bg-red-400',
    full_load: 'bg-emerald-400',
    underload: 'bg-amber-400',
  }[status] ?? 'bg-slate-300'
}

function statusTextClass(status) {
  return {
    overload:  'text-red-600 font-semibold',
    full_load: 'text-emerald-600 font-semibold',
    underload: 'text-amber-600 font-semibold',
  }[status] ?? 'text-slate-600'
}

function barClass(status) {
  return {
    overload:  'bg-red-400',
    full_load: 'bg-emerald-400',
    underload: 'bg-amber-300',
  }[status] ?? 'bg-slate-300'
}

function flagTextClass(severity) {
  return {
    high:   'text-red-600',
    medium: 'text-amber-600',
    info:   'text-slate-500',
  }[severity] ?? 'text-slate-500'
}
</script>
