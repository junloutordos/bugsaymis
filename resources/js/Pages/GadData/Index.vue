<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  employees:   Object,
  students:    Object,
  leave:       Object,
  training:    Object,
  rewards:     Object,
  guidance:    Object,
  ipcr:        Object,
  csm:         Object,
  generatedAt: String,
})

const formattedDate = computed(() => {
  if (!props.generatedAt) return ''
  return new Date(props.generatedAt).toLocaleString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
})

const navSections = [
  { id: 'employees', label: 'Employees' },
  { id: 'students',  label: 'Students' },
  { id: 'leave',     label: 'Leave' },
  { id: 'training',  label: 'Training' },
  { id: 'rewards',   label: 'Rewards' },
  { id: 'guidance',  label: 'Guidance' },
  { id: 'ipcr',      label: 'IPCR' },
  { id: 'csm',       label: 'CSM' },
]

/** Return '< 5' for suppressed values, otherwise format with commas. */
function fmt(n) {
  if (n === null || n === undefined) return '< 5'
  return Number(n).toLocaleString('en-PH')
}

/** Bar width as % of the larger sibling. Returns '0%' when suppressed. */
function barW(val, male, female) {
  const m = male   ?? 0
  const f = female ?? 0
  const max = Math.max(m, f)
  if (!max || val === null || val === undefined) return '0%'
  return Math.round((val / max) * 100) + '%'
}

/** Sum of male + female (treating null as 0 for display total). */
function sumMF(obj) {
  if (!obj) return '—'
  const m = obj.male   ?? 0
  const f = obj.female ?? 0
  if (m === 0 && f === 0) return '—'
  return Number(m + f).toLocaleString('en-PH')
}
</script>

<template>
  <Head title="GAD Data Dashboard — PSHS-CRC" />

  <div class="min-h-screen bg-slate-50">

    <!-- ── Navbar ──────────────────────────────────────────────────────── -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-20 shadow-sm">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="h-9 w-9 object-contain flex-shrink-0" />
          <div class="min-w-0">
            <div class="text-sm font-bold text-slate-800 leading-tight truncate">PSHS – Caraga Region Campus</div>
            <div class="text-xs text-slate-500 leading-tight">GAD Statistics Dashboard</div>
          </div>
        </div>
        <div class="flex-shrink-0 flex items-center gap-2">
          <span class="hidden sm:inline text-xs text-slate-400">Cached:</span>
          <span class="text-xs font-medium text-slate-600 bg-slate-100 px-2 py-1 rounded-full whitespace-nowrap">{{ formattedDate }}</span>
        </div>
      </div>
      <!-- Jump-nav -->
      <div class="max-w-6xl mx-auto px-4 sm:px-6 pb-2 flex gap-3 overflow-x-auto no-scrollbar">
        <a v-for="s in navSections" :key="s.id" :href="'#' + s.id"
           class="text-xs text-slate-500 hover:text-indigo-600 whitespace-nowrap pb-1 border-b-2 border-transparent hover:border-indigo-400 transition">
          {{ s.label }}
        </a>
      </div>
    </header>

    <!-- ── Hero ────────────────────────────────────────────────────────── -->
    <section class="bg-gradient-to-br from-indigo-700 to-indigo-900 text-white">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 sm:py-14">
        <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 text-white/90 text-xs font-medium px-3 py-1 rounded-full mb-4">
          <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
          GFPS / GAD Committee — Public Statistics
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold leading-snug mb-3">
          Campus Gender &amp; Development<br />Statistics Dashboard
        </h1>
        <p class="text-indigo-200 text-sm max-w-xl leading-relaxed">
          Sex-disaggregated aggregate data across all campus modules for GAD mainstreaming
          assessment and reporting. Counts below 5 are suppressed to protect individual privacy.
        </p>
        <!-- Legend -->
        <div class="flex flex-wrap gap-4 mt-6 text-sm">
          <span class="flex items-center gap-2">
            <span class="w-4 h-4 rounded bg-indigo-300 block"></span>
            <span class="text-indigo-100">Male</span>
          </span>
          <span class="flex items-center gap-2">
            <span class="w-4 h-4 rounded bg-rose-300 block"></span>
            <span class="text-indigo-100">Female</span>
          </span>
          <span class="flex items-center gap-2">
            <span class="w-4 h-4 rounded bg-white/20 block border border-white/30"></span>
            <span class="text-indigo-100">&lt;&nbsp;5 (suppressed)</span>
          </span>
        </div>
      </div>
    </section>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-10 space-y-12">

      <!-- ════════════════════════════════════════════════════
           1. EMPLOYEES
      ════════════════════════════════════════════════════ -->
      <section id="employees">
        <div class="flex items-center gap-3 mb-1">
          <span class="w-1 h-6 rounded-full bg-indigo-500 block"></span>
          <h2 class="text-base font-semibold text-slate-800">Employees</h2>
        </div>
        <p class="text-xs text-slate-400 mb-5 ml-4">Active employees. Bands: SG 1–9 Rank-and-File · SG 10–14 Supervisory · SG 15+ Managerial.</p>

        <!-- Summary cards -->
        <div class="grid grid-cols-3 gap-3 mb-6">
          <div class="bg-indigo-500 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(employees.total.male) }}</div>
            <div class="text-xs text-indigo-100 mt-0.5">Male</div>
          </div>
          <div class="bg-rose-400 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(employees.total.female) }}</div>
            <div class="text-xs text-rose-100 mt-0.5">Female</div>
          </div>
          <div class="bg-slate-700 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ sumMF(employees.total) }}</div>
            <div class="text-xs text-slate-300 mt-0.5">Total</div>
          </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
          <!-- By Division -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">By Division</h3>
            <div v-if="!employees.byDivision?.length" class="text-sm text-slate-400 italic">No data available.</div>
            <div v-else class="space-y-3">
              <div v-for="row in employees.byDivision" :key="row.name">
                <div class="text-xs font-medium text-slate-600 mb-1 truncate">{{ row.name }}</div>
                <div class="flex items-center gap-1.5 mb-0.5">
                  <span class="text-xs w-4 text-indigo-500 font-medium">M</span>
                  <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                    <div class="h-full bg-indigo-400 rounded-full transition-all duration-300"
                         :style="{ width: barW(row.male, row.male, row.female) }"></div>
                  </div>
                  <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.male) }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs w-4 text-rose-400 font-medium">F</span>
                  <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                    <div class="h-full bg-rose-400 rounded-full transition-all duration-300"
                         :style="{ width: barW(row.female, row.male, row.female) }"></div>
                  </div>
                  <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.female) }}</span>
                </div>
              </div>
            </div>
          </div>
          <!-- By Salary Band -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">By Salary Band</h3>
            <div v-if="!employees.bySalaryBand?.length" class="text-sm text-slate-400 italic">No data available.</div>
            <div v-else class="space-y-3">
              <div v-for="row in employees.bySalaryBand" :key="row.name">
                <div class="text-xs font-medium text-slate-600 mb-1">{{ row.name }}</div>
                <div class="flex items-center gap-1.5 mb-0.5">
                  <span class="text-xs w-4 text-indigo-500 font-medium">M</span>
                  <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                    <div class="h-full bg-indigo-400 rounded-full" :style="{ width: barW(row.male, row.male, row.female) }"></div>
                  </div>
                  <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.male) }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs w-4 text-rose-400 font-medium">F</span>
                  <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                    <div class="h-full bg-rose-400 rounded-full" :style="{ width: barW(row.female, row.male, row.female) }"></div>
                  </div>
                  <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.female) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ════════════════════════════════════════════════════
           2. STUDENTS
      ════════════════════════════════════════════════════ -->
      <section id="students">
        <div class="flex items-center gap-3 mb-1">
          <span class="w-1 h-6 rounded-full bg-violet-500 block"></span>
          <h2 class="text-base font-semibold text-slate-800">Students</h2>
        </div>
        <p class="text-xs text-slate-400 mb-5 ml-4">Currently enrolled students per grade level.</p>

        <div class="grid grid-cols-3 gap-3 mb-6">
          <div class="bg-indigo-500 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(students.total.male) }}</div>
            <div class="text-xs text-indigo-100 mt-0.5">Male</div>
          </div>
          <div class="bg-rose-400 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(students.total.female) }}</div>
            <div class="text-xs text-rose-100 mt-0.5">Female</div>
          </div>
          <div class="bg-slate-700 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ sumMF(students.total) }}</div>
            <div class="text-xs text-slate-300 mt-0.5">Total</div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">By Grade Level</h3>
          <div v-if="!students.byGrade?.length" class="text-sm text-slate-400 italic">No enrollment data available.</div>
          <div v-else class="space-y-3">
            <div v-for="row in students.byGrade" :key="row.name">
              <div class="text-xs font-medium text-slate-600 mb-1">{{ row.name }}</div>
              <div class="flex items-center gap-1.5 mb-0.5">
                <span class="text-xs w-4 text-indigo-500 font-medium">M</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-indigo-400 rounded-full" :style="{ width: barW(row.male, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-10 text-right text-slate-500">{{ fmt(row.male) }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="text-xs w-4 text-rose-400 font-medium">F</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-rose-400 rounded-full" :style="{ width: barW(row.female, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-10 text-right text-slate-500">{{ fmt(row.female) }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ════════════════════════════════════════════════════
           3. LEAVE APPLICATIONS
      ════════════════════════════════════════════════════ -->
      <section id="leave">
        <div class="flex items-center gap-3 mb-1">
          <span class="w-1 h-6 rounded-full bg-sky-500 block"></span>
          <h2 class="text-base font-semibold text-slate-800">Leave Applications</h2>
        </div>
        <p class="text-xs text-slate-400 mb-5 ml-4">All leave applications filed. Approved = final HR/supervisor approval.</p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
          <div class="bg-indigo-500 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(leave.total.male) }}</div>
            <div class="text-xs text-indigo-100 mt-0.5">Male — Filed</div>
          </div>
          <div class="bg-rose-400 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(leave.total.female) }}</div>
            <div class="text-xs text-rose-100 mt-0.5">Female — Filed</div>
          </div>
          <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4">
            <div class="text-2xl font-bold text-indigo-700">{{ fmt(leave.approved.male) }}</div>
            <div class="text-xs text-slate-500 mt-0.5">Male — Approved</div>
          </div>
          <div class="bg-rose-50 border border-rose-100 rounded-xl p-4">
            <div class="text-2xl font-bold text-rose-600">{{ fmt(leave.approved.female) }}</div>
            <div class="text-xs text-slate-500 mt-0.5">Female — Approved</div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">By Leave Type</h3>
          <div v-if="!leave.byType?.length" class="text-sm text-slate-400 italic">No data available.</div>
          <div v-else class="space-y-3">
            <div v-for="row in leave.byType" :key="row.name">
              <div class="text-xs font-medium text-slate-600 mb-1">{{ row.name }}</div>
              <div class="flex items-center gap-1.5 mb-0.5">
                <span class="text-xs w-4 text-indigo-500 font-medium">M</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-indigo-400 rounded-full" :style="{ width: barW(row.male, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-10 text-right text-slate-500">{{ fmt(row.male) }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="text-xs w-4 text-rose-400 font-medium">F</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-rose-400 rounded-full" :style="{ width: barW(row.female, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-10 text-right text-slate-500">{{ fmt(row.female) }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ════════════════════════════════════════════════════
           4. TRAINING & DEVELOPMENT
      ════════════════════════════════════════════════════ -->
      <section id="training">
        <div class="flex items-center gap-3 mb-1">
          <span class="w-1 h-6 rounded-full bg-teal-500 block"></span>
          <h2 class="text-base font-semibold text-slate-800">Training &amp; Development</h2>
        </div>
        <p class="text-xs text-slate-400 mb-5 ml-4">Unique employees with at least one training record. Counted by year of training.</p>

        <div class="grid grid-cols-3 gap-3 mb-6">
          <div class="bg-indigo-500 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(training.total.male) }}</div>
            <div class="text-xs text-indigo-100 mt-0.5">Male Trained</div>
          </div>
          <div class="bg-rose-400 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(training.total.female) }}</div>
            <div class="text-xs text-rose-100 mt-0.5">Female Trained</div>
          </div>
          <div class="bg-slate-700 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ sumMF(training.total) }}</div>
            <div class="text-xs text-slate-300 mt-0.5">Total</div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Training Participants by Year</h3>
          <div v-if="!training.byYear?.length" class="text-sm text-slate-400 italic">No training records found.</div>
          <div v-else class="space-y-3">
            <div v-for="row in training.byYear" :key="row.name">
              <div class="text-xs font-medium text-slate-600 mb-1">{{ row.name }}</div>
              <div class="flex items-center gap-1.5 mb-0.5">
                <span class="text-xs w-4 text-indigo-500 font-medium">M</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-indigo-400 rounded-full" :style="{ width: barW(row.male, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-10 text-right text-slate-500">{{ fmt(row.male) }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="text-xs w-4 text-rose-400 font-medium">F</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-rose-400 rounded-full" :style="{ width: barW(row.female, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-10 text-right text-slate-500">{{ fmt(row.female) }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ════════════════════════════════════════════════════
           5. REWARDS & RECOGNITION
      ════════════════════════════════════════════════════ -->
      <section id="rewards">
        <div class="flex items-center gap-3 mb-1">
          <span class="w-1 h-6 rounded-full bg-amber-500 block"></span>
          <h2 class="text-base font-semibold text-slate-800">Rewards &amp; Recognition</h2>
        </div>
        <p class="text-xs text-slate-400 mb-5 ml-4">Award nominations by sex and nomination status.</p>

        <div class="grid grid-cols-3 gap-3 mb-6">
          <div class="bg-indigo-500 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(rewards.total.male) }}</div>
            <div class="text-xs text-indigo-100 mt-0.5">Male Nominated</div>
          </div>
          <div class="bg-rose-400 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(rewards.total.female) }}</div>
            <div class="text-xs text-rose-100 mt-0.5">Female Nominated</div>
          </div>
          <div class="bg-slate-700 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ sumMF(rewards.total) }}</div>
            <div class="text-xs text-slate-300 mt-0.5">Total</div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">By Nomination Status</h3>
          <div v-if="!rewards.byStatus?.length" class="text-sm text-slate-400 italic">No nomination data available.</div>
          <div v-else class="space-y-3">
            <div v-for="row in rewards.byStatus" :key="row.name">
              <div class="text-xs font-medium text-slate-600 mb-1">{{ row.name }}</div>
              <div class="flex items-center gap-1.5 mb-0.5">
                <span class="text-xs w-4 text-indigo-500 font-medium">M</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-indigo-400 rounded-full" :style="{ width: barW(row.male, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.male) }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="text-xs w-4 text-rose-400 font-medium">F</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-rose-400 rounded-full" :style="{ width: barW(row.female, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.female) }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ════════════════════════════════════════════════════
           6. GUIDANCE COUNSELING
      ════════════════════════════════════════════════════ -->
      <section id="guidance">
        <div class="flex items-center gap-3 mb-1">
          <span class="w-1 h-6 rounded-full bg-emerald-500 block"></span>
          <h2 class="text-base font-semibold text-slate-800">Guidance Counseling</h2>
        </div>
        <p class="text-xs text-slate-400 mb-5 ml-4">Guidance session reports filed, grouped by school year.</p>

        <div class="grid grid-cols-3 gap-3 mb-6">
          <div class="bg-indigo-500 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(guidance.total.male) }}</div>
            <div class="text-xs text-indigo-100 mt-0.5">Male</div>
          </div>
          <div class="bg-rose-400 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(guidance.total.female) }}</div>
            <div class="text-xs text-rose-100 mt-0.5">Female</div>
          </div>
          <div class="bg-slate-700 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ sumMF(guidance.total) }}</div>
            <div class="text-xs text-slate-300 mt-0.5">Total Sessions</div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Sessions by School Year</h3>
          <div v-if="!guidance.byYear?.length" class="text-sm text-slate-400 italic">No guidance session data available.</div>
          <div v-else class="space-y-3">
            <div v-for="row in guidance.byYear" :key="row.name">
              <div class="text-xs font-medium text-slate-600 mb-1">{{ row.name }}</div>
              <div class="flex items-center gap-1.5 mb-0.5">
                <span class="text-xs w-4 text-indigo-500 font-medium">M</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-indigo-400 rounded-full" :style="{ width: barW(row.male, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.male) }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="text-xs w-4 text-rose-400 font-medium">F</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-rose-400 rounded-full" :style="{ width: barW(row.female, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.female) }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ════════════════════════════════════════════════════
           7. IPCR PERFORMANCE
      ════════════════════════════════════════════════════ -->
      <section id="ipcr">
        <div class="flex items-center gap-3 mb-1">
          <span class="w-1 h-6 rounded-full bg-rose-500 block"></span>
          <h2 class="text-base font-semibold text-slate-800">IPCR Performance</h2>
        </div>
        <p class="text-xs text-slate-400 mb-5 ml-4">Unique employees who submitted IPCR, and their average supervisor-rated performance score.</p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-indigo-500 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(ipcr.total.male) }}</div>
            <div class="text-xs text-indigo-100 mt-0.5">Male — Filers</div>
          </div>
          <div class="bg-rose-400 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(ipcr.total.female) }}</div>
            <div class="text-xs text-rose-100 mt-0.5">Female — Filers</div>
          </div>
          <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4">
            <div class="text-2xl font-bold text-indigo-700">
              {{ ipcr.avgRatings.male !== null ? ipcr.avgRatings.male : 'N/A' }}
            </div>
            <div class="text-xs text-slate-500 mt-0.5">Male — Avg Rating</div>
          </div>
          <div class="bg-rose-50 border border-rose-100 rounded-xl p-4">
            <div class="text-2xl font-bold text-rose-600">
              {{ ipcr.avgRatings.female !== null ? ipcr.avgRatings.female : 'N/A' }}
            </div>
            <div class="text-xs text-slate-500 mt-0.5">Female — Avg Rating</div>
          </div>
        </div>
      </section>

      <!-- ════════════════════════════════════════════════════
           8. CSM CLIENT FEEDBACK
      ════════════════════════════════════════════════════ -->
      <section id="csm">
        <div class="flex items-center gap-3 mb-1">
          <span class="w-1 h-6 rounded-full bg-orange-500 block"></span>
          <h2 class="text-base font-semibold text-slate-800">CSM Client Feedback</h2>
        </div>
        <p class="text-xs text-slate-400 mb-5 ml-4">Citizens Charter Satisfaction survey respondents by sex and client type.</p>

        <div class="grid grid-cols-3 gap-3 mb-6">
          <div class="bg-indigo-500 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(csm.total.male) }}</div>
            <div class="text-xs text-indigo-100 mt-0.5">Male</div>
          </div>
          <div class="bg-rose-400 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ fmt(csm.total.female) }}</div>
            <div class="text-xs text-rose-100 mt-0.5">Female</div>
          </div>
          <div class="bg-slate-700 text-white rounded-xl p-4">
            <div class="text-2xl font-bold">{{ sumMF(csm.total) }}</div>
            <div class="text-xs text-slate-300 mt-0.5">Total</div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">By Client Type</h3>
          <div v-if="!csm.byClientType?.length" class="text-sm text-slate-400 italic">No CSM data available.</div>
          <div v-else class="space-y-3">
            <div v-for="row in csm.byClientType" :key="row.name">
              <div class="text-xs font-medium text-slate-600 mb-1">{{ row.name }}</div>
              <div class="flex items-center gap-1.5 mb-0.5">
                <span class="text-xs w-4 text-indigo-500 font-medium">M</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-indigo-400 rounded-full" :style="{ width: barW(row.male, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.male) }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="text-xs w-4 text-rose-400 font-medium">F</span>
                <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
                  <div class="h-full bg-rose-400 rounded-full" :style="{ width: barW(row.female, row.male, row.female) }"></div>
                </div>
                <span class="text-xs w-8 text-right text-slate-500">{{ fmt(row.female) }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

    </main>

    <!-- ── Footer ──────────────────────────────────────────────────────── -->
    <footer class="border-t border-slate-200 bg-white py-8 text-center text-xs text-slate-400 space-y-1">
      <p class="font-medium text-slate-500">Philippine Science High School – Caraga Region Campus in Butuan City</p>
      <p>GFPS / GAD Committee Dashboard &mdash; Data refreshes every hour</p>
      <p>Counts below 5 are suppressed per statistical disclosure standards</p>
      <p class="mt-2">Generated: {{ formattedDate }}</p>
    </footer>

  </div>
</template>
