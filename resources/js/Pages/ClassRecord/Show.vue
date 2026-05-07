<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { badgeBase } from '@/Composables/useStatusBadge.js'
import Swal from 'sweetalert2'
import {
  LockClosedIcon,
  LockOpenIcon,
  ArrowLeftIcon,
  CheckCircleIcon,
  ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline'
import ScoreGrid from './components/ScoreGrid.vue'

const props = defineProps({
  classRecord:   Object,
  isAdmin:       { type: Boolean, default: false },
  stanineLookup: { type: Array, default: () => [] },
})

const page = usePage()

// ── Status badge ──────────────────────────────────────────────────────────────
function statusBadge(status) {
  return {
    draft:     'bg-slate-100 text-slate-600',
    submitted: 'bg-blue-100 text-blue-700',
    checked:   'bg-emerald-100 text-emerald-700',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

// ── Quarter tabs ──────────────────────────────────────────────────────────────
const activeQuarter = ref(1)
const activeSubTab  = ref('setup')  // 'setup' | 'scores'

const currentQuarterData = computed(() =>
  props.classRecord.quarters?.find(q => q.quarter === activeQuarter.value) ?? null
)

const isLocked = computed(() => currentQuarterData.value?.is_locked ?? false)

// ── Assessment setup ──────────────────────────────────────────────────────────
// Build editable assessment rows from the grading option categories
const assessmentDraft = ref({})

function buildDraft(quarter) {
  const draft = {}
  for (const cat of props.classRecord.grading_option?.categories ?? []) {
    draft[cat.id] = []
    const existing = (quarter?.assessments ?? []).filter(a => a.grading_category_id === cat.id)
    for (let n = 1; n <= cat.max_assessments; n++) {
      const found = existing.find(a => a.assessment_number === n)
      draft[cat.id].push({
        grading_category_id: cat.id,
        assessment_number:   n,
        title:               found?.title ?? '',
        activity_date:       found?.activity_date ?? '',
        max_score:           found?.max_score ?? '',
        _saved:              !!found,
      })
    }
  }
  return draft
}

const savingSetup = ref(false)
const setupErrors = ref([])

watch(activeQuarter, (q) => {
  activeSubTab.value    = 'setup'
  assessmentDraft.value = buildDraft(currentQuarterData.value)
  savingSetup.value     = false
  setupErrors.value     = []
}, { immediate: true })

watch(() => props.classRecord.quarters, () => {
  assessmentDraft.value = buildDraft(currentQuarterData.value)
}, { deep: true })

async function saveSetup() {
  savingSetup.value = true
  setupErrors.value = []

  const assessments = Object.values(assessmentDraft.value).flat().filter(a => a.title && a.max_score)

  if (!assessments.length) {
    setupErrors.value = ['Add at least one assessment with a title and max score.']
    savingSetup.value = false
    return
  }

  try {
    await axios.post(
      route('class-records.assessments.upsert', { classRecord: props.classRecord.id, q: activeQuarter.value }),
      { assessments }
    )
    await Swal.fire({ icon: 'success', title: 'Setup saved!', timer: 1000, showConfirmButton: false })
    router.reload({ only: ['classRecord'] })
  } catch (err) {
    if (err.response?.status === 422) {
      setupErrors.value = Object.values(err.response.data.errors ?? {}).flat()
    } else {
      setupErrors.value = [err.response?.data?.message ?? 'Failed to save setup.']
    }
  } finally {
    savingSetup.value = false
  }
}

// ── Quarter lock / unlock ─────────────────────────────────────────────────────
async function lockQuarter() {
  const result = await Swal.fire({
    title: `Lock Quarter ${activeQuarter.value}?`,
    text: 'Score entry will be disabled after locking. Admins can unlock it.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Lock',
    confirmButtonColor: '#d97706',
  })
  if (!result.isConfirmed) return

  try {
    await axios.post(route('class-records.quarters.lock', { classRecord: props.classRecord.id, q: activeQuarter.value }))
    router.reload({ only: ['classRecord'] })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to lock quarter.', 'error')
  }
}

async function unlockQuarter() {
  try {
    await axios.post(route('class-records.quarters.unlock', { classRecord: props.classRecord.id, q: activeQuarter.value }))
    router.reload({ only: ['classRecord'] })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to unlock quarter.', 'error')
  }
}

// ── Workflow: submit / check ──────────────────────────────────────────────────
async function submitRecord() {
  const result = await Swal.fire({
    title:             'Submit for Review?',
    text:              'The class record will be locked for editing and sent to the Academic Unit Head for review.',
    icon:              'question',
    showCancelButton:  true,
    confirmButtonText: 'Yes, Submit',
  })
  if (!result.isConfirmed) return

  try {
    await axios.post(route('class-records.submit', props.classRecord.id))
    router.reload()
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to submit.', 'error')
  }
}

async function checkRecord() {
  try {
    await axios.post(route('class-records.check', props.classRecord.id))
    router.reload()
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to mark as checked.', 'error')
  }
}
</script>

<template>
  <Head :title="`Class Record — ${classRecord.subject_name}`" />
  <AdminLayout :title="classRecord.subject_name">
    <div class="space-y-5">

      <!-- Back + header -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="flex items-start gap-3">
          <button @click="router.visit(route('class-records.page.index'))"
            class="mt-0.5 p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500">
            <ArrowLeftIcon class="h-4 w-4" />
          </button>
          <div>
            <h1 class="text-xl font-bold text-slate-800">{{ classRecord.subject_name }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">
              {{ classRecord.year_level_section }} &middot; {{ classRecord.school_year }}
              &middot; {{ classRecord.grading_option?.name }}
            </p>
            <div class="mt-1.5">
              <span :class="[badgeBase, statusBadge(classRecord.status)]">
                {{ classRecord.status === 'checked' ? 'Checked ✓' : classRecord.status.charAt(0).toUpperCase() + classRecord.status.slice(1) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Workflow actions -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- Export All button -->
          <a :href="route('class-records.export', classRecord.id)"
            class="inline-flex items-center gap-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
            <ArrowDownTrayIcon class="h-4 w-4" /> Export All
          </a>
          <button v-if="classRecord.status === 'draft'"
            @click="submitRecord"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            Submit for Review
          </button>
          <button v-if="classRecord.status === 'submitted' && isAdmin"
            @click="checkRecord"
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <CheckCircleIcon class="h-4 w-4" /> Mark as Checked
          </button>
        </div>
      </div>

      <!-- Quarter tabs -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Quarter tab bar -->
        <div class="flex border-b border-slate-100">
          <button v-for="q in [1,2,3,4]" :key="q"
            @click="activeQuarter = q"
            :class="[
              'px-6 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
              activeQuarter === q
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700',
            ]">
            Quarter {{ q }}
            <span v-if="classRecord.quarters?.find(qt => qt.quarter === q)?.is_locked"
              class="ml-1.5 inline-flex"><LockClosedIcon class="h-3 w-3 text-amber-500" /></span>
          </button>
        </div>

        <!-- Quarter export button -->
        <div class="flex justify-end px-4 pt-2">
          <a :href="route('class-records.quarters.export', { classRecord: classRecord.id, q: activeQuarter })"
            class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-indigo-600 px-2 py-1 rounded hover:bg-slate-50">
            <ArrowDownTrayIcon class="h-3.5 w-3.5" /> Export Q{{ activeQuarter }}
          </a>
        </div>

        <!-- Sub-tab bar -->
        <div class="flex gap-1 px-4 pt-1 border-b border-slate-50">
          <button
            v-for="tab in ['setup', 'scores']" :key="tab"
            @click="activeSubTab = tab"
            :class="[
              'px-4 py-1.5 rounded-lg text-xs font-medium transition-colors',
              activeSubTab === tab
                ? 'bg-indigo-50 text-indigo-700'
                : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50',
            ]">
            {{ tab === 'setup' ? '⚙ Setup' : '📊 Scores & Grades' }}
          </button>
        </div>

        <!-- ── Setup sub-tab ─────────────────────────────────────────────── -->
        <div v-if="activeSubTab === 'setup'" class="p-5">

          <!-- Lock / unlock controls -->
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs text-slate-500">
              Configure assessment titles, dates, and max scores for each category.
            </p>
            <div class="flex items-center gap-2">
              <button v-if="!isLocked"
                @click="lockQuarter"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100 text-xs font-medium">
                <LockClosedIcon class="h-3.5 w-3.5" /> Lock Quarter
              </button>
              <button v-if="isLocked && isAdmin"
                @click="unlockQuarter"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-medium">
                <LockOpenIcon class="h-3.5 w-3.5" /> Unlock
              </button>
              <span v-if="isLocked && !isAdmin"
                class="inline-flex items-center gap-1 text-xs text-amber-600">
                <LockClosedIcon class="h-3.5 w-3.5" /> Locked
              </span>
            </div>
          </div>

          <!-- Errors -->
          <div v-if="setupErrors.length"
            class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-xs space-y-1">
            <p v-for="e in setupErrors" :key="e">{{ e }}</p>
          </div>

          <!-- Category groups -->
          <div class="space-y-6">
            <div v-for="cat in classRecord.grading_option?.categories" :key="cat.id">
              <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">
                  {{ cat.code }}
                </span>
                <span class="text-sm font-semibold text-slate-700">{{ cat.name }}</span>
                <span class="text-xs text-slate-400">({{ Math.round(cat.weight * 100) }}%)</span>
              </div>

              <div class="overflow-x-auto rounded-lg border border-slate-100">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-16">#</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Title / Description</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-36">Activity Date</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-28">Max Score</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in assessmentDraft[cat.id]" :key="row.assessment_number"
                      class="hover:bg-slate-50/40">
                      <td class="px-4 py-2 text-xs font-bold text-slate-500">
                        {{ cat.code }}{{ row.assessment_number }}
                      </td>
                      <td class="px-4 py-2">
                        <input v-model="row.title" type="text"
                          :disabled="isLocked"
                          :placeholder="`${cat.code}${row.assessment_number} title…`"
                          class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400" />
                      </td>
                      <td class="px-4 py-2">
                        <input v-model="row.activity_date" type="date"
                          :disabled="isLocked"
                          class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400" />
                      </td>
                      <td class="px-4 py-2">
                        <input v-model.number="row.max_score" type="number" min="0.01" step="0.5"
                          :disabled="isLocked"
                          placeholder="e.g. 30"
                          class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Save button -->
          <div class="mt-5 flex justify-end">
            <button v-if="!isLocked"
              @click="saveSetup"
              :disabled="savingSetup"
              class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-colors shadow-sm">
              {{ savingSetup ? 'Saving…' : 'Save Setup' }}
            </button>
          </div>
        </div>

        <!-- ── Scores & Grades sub-tab ───────────────────────────────────── -->
        <div v-if="activeSubTab === 'scores'" class="p-5">
          <ScoreGrid
            :class-record-id="classRecord.id"
            :quarter-number="activeQuarter"
            :quarter-data="currentQuarterData"
            :grading-option="classRecord.grading_option"
            :stanine-lookup="stanineLookup"
            :previous-grades="{}"
            :is-locked="isLocked"
            @reload="router.reload({ only: ['classRecord'] })"
          />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
