<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppModal from "@/Components/AppModal.vue"
import { ArrowLeftIcon, PrinterIcon } from "@heroicons/vue/24/outline"
import { ref, computed } from "vue"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

const props = defineProps({
  ipcr:       Object,
  employee:   Object,
  supervisor: Object,
  plans:      Array,
})

const getAdjectivalRating = ipcrAdjectivalRating

// Maps each distinct IPCR status string to the closest AppBadge color.
function statusBadgeColor(status) {
  const map = {
    'New Target':                'blue',
    'For Review':                'amber',
    'Targets Approved':          'green',
    'Submitted for Rating':      'orange',
    'Rated & For PMT Review':    'purple',
    'Submitted to PMT':          'purple',
    'PMT Returned for Revision': 'red',
    'Submitted to HR':           'blue',
    'Approved by PMT':           'green',
    'Director Signed':           'green',
    'Returned for Revision':     'red',
    'Rejected':                  'red',
  }
  return map[status] ?? 'slate'
}

// ---------- Date helpers ----------
const extractYearFromRatingPeriod = (ratingPeriod) => {
  if (!ratingPeriod || typeof ratingPeriod !== "string") return ""
  const m = ratingPeriod.match(/(19|20)\d{2}/)
  return m ? m[0] : ""
}

const formatDateString = (value) => {
  if (!value) return "—"
  let d
  if (value instanceof Date) { d = value } else {
    let parsed = Date.parse(value)
    if (isNaN(parsed)) { const digits = String(value).match(/\d{9,}/); if (digits) parsed = parseInt(digits[0], 10) }
    if (isNaN(parsed)) return value || "—"
    d = new Date(parsed)
  }
  if (isNaN(d)) return "—"
  return d.toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" })
}

const ratingYear                    = computed(() => extractYearFromRatingPeriod(props.ipcr?.rating_period || ""))
const formattedSubmittedForReviewAt = computed(() => formatDateString(props.ipcr?.submitted_for_review_at))
const formattedTargetApprovedAt     = computed(() => formatDateString(props.ipcr?.target_approved_at))
const formattedSubmittedRatingAt    = computed(() => formatDateString(props.ipcr?.submitted_rating_at))

// ---------- Rating helpers ----------
const computeAverage = (q, e, t) => {
  const values = [q, e, t].filter(v => v !== null && v !== "" && !isNaN(v)).map(Number)
  if (!values.length) return "—"
  return (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2)
}

const computeNumericAverage = (q, e, t) => {
  const values = [q, e, t].filter(v => v !== null && v !== "" && !isNaN(v)).map(Number)
  if (!values.length) return null
  return values.reduce((a, b) => a + b, 0) / values.length
}

const formatAvg = (num) => {
  if (num === null || num === undefined || isNaN(num)) return "—"
  return Number(num).toFixed(2)
}

// ---------- Normalize function type ----------
const normalizeFunctionType = (raw) => {
  if (!raw) return "Uncategorized"
  const t = String(raw).trim().toLowerCase()
  if (["strategic", "strategic functions", "strategic function"].includes(t)) return "Strategic Functions"
  if (["core", "core functions", "core function"].includes(t)) return "Core Functions"
  if (["support", "support functions", "support function"].includes(t)) return "Support Functions"
  return String(raw).trim()
}

const functionTypeWeights = { "Strategic Functions": 0.30, "Core Functions": 0.55, "Support Functions": 0.15, "Uncategorized": 0 }
const functionTypeOrder   = { "Strategic Functions": 1, "Core Functions": 2, "Support Functions": 3, "Uncategorized": 4 }

// ---------- Grouped plans (same structure as EmployeeIPCRShow) ----------
const groupedPlansByFunction = computed(() => {
  const groups = {}
  ;(props.plans || []).forEach(plan => {
    const aoo          = plan.performance_indicator?.agency_outcome
    const functionType = normalizeFunctionType(aoo?.function_type)
    const outcome      = aoo?.outcome || "Uncategorized"
    const subOutcome   = aoo?.sub_outcome || "—"
    const subAbbrev    = subOutcome !== "—" ? subOutcome.slice(0, 4) : subOutcome
    const piDesc       = plan.performance_indicator?.description || "—"
    if (!groups[functionType]) groups[functionType] = {}
    if (!groups[functionType][outcome]) groups[functionType][outcome] = {}
    if (!groups[functionType][outcome][subAbbrev]) groups[functionType][outcome][subAbbrev] = {}
    if (!groups[functionType][outcome][subAbbrev][piDesc]) groups[functionType][outcome][subAbbrev][piDesc] = []
    groups[functionType][outcome][subAbbrev][piDesc].push(plan)
  })
  const sorted = {}
  Object.keys(functionTypeOrder).forEach(ft => { if (groups[ft]) sorted[ft] = groups[ft] })
  Object.keys(groups).filter(ft => !functionTypeOrder[ft]).sort().forEach(ft => (sorted[ft] = groups[ft]))
  Object.keys(sorted).forEach(ft => {
    const outcomes = sorted[ft]
    const sortedOutcomes = {}
    Object.keys(outcomes).sort().forEach(outcome => {
      sortedOutcomes[outcome] = {}
      Object.keys(outcomes[outcome]).sort().forEach(sub => {
        const pis = outcomes[outcome][sub]
        const sortedPI = {}
        Object.keys(pis).sort().forEach(piDesc => { sortedPI[piDesc] = pis[piDesc] })
        sortedOutcomes[outcome][sub] = sortedPI
      })
    })
    sorted[ft] = sortedOutcomes
  })
  return sorted
})

// ---------- Summary by function type ----------
const summaryByFunctionType = computed(() => {
  const summary = {}
  ;(props.plans || []).forEach(plan => {
    const aoo          = plan.performance_indicator?.agency_outcome
    const functionType = normalizeFunctionType(aoo?.function_type)
    if (!summary[functionType]) {
      summary[functionType] = { plansCount: 0, totalQ: 0, countQ: 0, totalE: 0, countE: 0, totalT: 0, countT: 0, totalA: 0, countA: 0, weight: functionTypeWeights[functionType] ?? 0 }
    }
    const entry = summary[functionType]
    entry.plansCount++
    const pivot = plan.pivot
    if (!pivot) return
    const Q = pivot.sup_quality; const E = pivot.sup_efficiency; const T = pivot.sup_timeliness
    if (Q !== null && Q !== "" && !isNaN(Q)) { entry.totalQ += Number(Q); entry.countQ++ }
    if (E !== null && E !== "" && !isNaN(E)) { entry.totalE += Number(E); entry.countE++ }
    if (T !== null && T !== "" && !isNaN(T)) { entry.totalT += Number(T); entry.countT++ }
    const avgFromPivot = pivot.sup_average
    let planAvg = null
    if (avgFromPivot !== undefined && avgFromPivot !== null && avgFromPivot !== "" && !isNaN(avgFromPivot)) { planAvg = Number(avgFromPivot) } else { const c = computeNumericAverage(Q, E, T); if (c !== null) planAvg = c }
    if (planAvg !== null) { entry.totalA += Number(planAvg); entry.countA++ }
  })
  return Object.keys(summary).sort((a, b) => (functionTypeOrder[a] || 99) - (functionTypeOrder[b] || 99)).reduce((obj, key) => { obj[key] = summary[key]; return obj }, {})
})

const finalIPCRRating = computed(() => {
  let totalWeighted = 0
  for (const [, entry] of Object.entries(summaryByFunctionType.value)) {
    if (entry.countA === 0) continue
    totalWeighted += (entry.totalA / entry.countA) * entry.weight
  }
  return Number(totalWeighted).toFixed(2)
})

// ---------- Accomplishment viewer ----------
const accViewerPlan = ref(null)
const openAccViewer  = (plan) => { accViewerPlan.value = plan }
const closeAccViewer = () => { accViewerPlan.value = null }
const formatAccDate  = (d) => d ? new Date(d).toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" }) : "—"

const printIPCR = () => window.print()
</script>

<template>
  <Head :title="`HR Review — ${ipcr.title}`" />
  <AdminLayout :title="`HR IPCR Review: ${ipcr.title}`">
    <div>
      <!-- Back -->
      <AppButton variant="ghost" size="sm" class="mb-4" @click="router.visit(route('hr-ipcr.index'))">
        <ArrowLeftIcon class="w-4 h-4" /> Back to HR IPCR List
      </AppButton>

      <!-- Header card -->
      <AppCard :padded="false" class="mb-4 no-print">
        <div class="flex items-start justify-between flex-wrap gap-3 p-5">
          <div>
            <h2 class="text-xl font-semibold text-slate-800">{{ ipcr.title }}</h2>
            <p class="text-slate-500 text-sm mt-0.5">Rating Period: {{ ipcr.rating_period }}</p>
          </div>
          <div class="flex items-center gap-3">
            <AppBadge :color="statusBadgeColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
            <AppButton variant="secondary" @click="printIPCR">
              <PrinterIcon class="h-4 w-4" />
              Print IPCR
            </AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Plans Table (same format as EmployeeIPCRShow) -->
      <div class="bg-white p-4 rounded-lg shadow" id="ipcr-printable">

        <!-- Intro block -->
        <div class="mb-4">
          <p class="text-l text-center font-semibold mb-2">Individual Performance Commitment and Review (IPCR)<br/>FY {{ ratingYear }}</p>
          <br/><br/>
          <p>
            I, <b class="uppercase">{{ employee.name }}</b>, <b class="uppercase">{{ employee.position }}</b> of Philippine Science High School - Caraga Region Campus, commit to deliver
            and agree to be rated on the attainment of the following targets in accordance with
            the indicated measures for the period <b class="uppercase">{{ ipcr.rating_period }}</b>.
          </p>
        </div>
        <br/><br/>

        <div class="grid grid-cols-10 gap-4 mb-4 text-right">
          <div class="col-span-1"></div>
          <div class="col-span-1"></div>
          <div class="col-span-2"></div>
          <div class="col-span-4 text-center">
            <p class="font-medium"><b style="text-transform: uppercase;">{{ employee.name }}</b></p>
            <small class="block">{{ employee.position }}</small>
            <small class="block">Date: {{ formattedSubmittedForReviewAt }}</small>
          </div>
          <div class="col-span-2 text-left">
            <small class="block">5 - Outstanding</small>
            <small class="block">4 - Very Satisfactory</small>
            <small class="block">3 - Satisfactory</small>
            <small class="block">2 - Unsatisfactory</small>
            <small class="block">1 - Poor</small>
          </div>
        </div>

        <table class="min-w-full border text-sm border-collapse">
          <tr class="font-bold text-gray-800">
            <td colspan="4" class="border px-3 text-left">Reviewed by:</td>
            <td colspan="2" class="border px-3 text-left">Date:</td>
            <td colspan="4" class="border px-3 text-left">Approved by:</td>
            <td colspan="2" class="border px-3 text-left">Date:</td>
          </tr>
          <tr>
            <td colspan="4" class="border px-3 py-3 text-center">
              <br/><br/><b style="text-transform: uppercase;">{{ supervisor?.name ?? '—' }}</b><br/><small>{{ supervisor?.position ?? '' }}</small>
            </td>
            <td colspan="2" class="border px-3 py-3 text-center"><br/>{{ formattedTargetApprovedAt }}</td>
            <td colspan="4" class="border px-3 py-3 text-center">
              <br/><br/><b>ENGR. RAMIL A. SANCHEZ</b><br/><small>Director III</small>
            </td>
            <td colspan="2" class="border px-3 py-3 text-center"><br/>{{ formattedTargetApprovedAt }}</td>
          </tr>
        </table>

        <!-- Main plans table -->
        <table class="min-w-full border border-gray-300 text-sm border-collapse mt-4">
          <thead class="bg-gray-100 text-gray-700 text-sm uppercase">
            <tr>
              <th rowspan="2" colspan="2" class="border px-4 py-2 text-center">Output</th>
              <th rowspan="2" class="border px-4 py-2">Success Indicators</th>
              <th rowspan="2" class="border px-4 py-2">Actual Accomplishment</th>
              <th rowspan="2" class="border px-4 py-2">Means of Verification</th>
              <th colspan="4" class="border px-4 py-2 text-center">Rating</th>
              <th rowspan="2" class="border px-4 py-2 text-center">Remarks</th>
            </tr>
            <tr>
              <th class="border px-4 py-2 text-center">Q<sup>1</sup></th>
              <th class="border px-4 py-2 text-center">E<sup>2</sup></th>
              <th class="border px-4 py-2 text-center">T<sup>3</sup></th>
              <th class="border px-4 py-2 text-center">A<sup>4</sup></th>
            </tr>
          </thead>

          <tbody>
            <template v-for="(outcomes, functionType) in groupedPlansByFunction" :key="functionType">
              <tr class="bg-gray-300">
                <td style="text-transform: uppercase;" colspan="10"
                    class="px-4 py-2 font-bold text-gray-800 border border-gray-300">{{ functionType }}</td>
              </tr>
              <tr v-if="functionType === 'Strategic Functions'">
                <td colspan="12" class="px-4 py-2 font-bold text-gray-800 border border-gray-300">DOST POINT AGENDA</td>
              </tr>
              <tr v-if="functionType === 'Strategic Functions'">
                <td colspan="12" class="px-4 py-2 font-bold text-gray-800 border border-gray-300">
                  INCREASED IN COMPETITIVENESS OF FILIPINOS IN SCIENCE AND ENGINEERING
                </td>
              </tr>

              <template v-for="(subGroups, outcome) in outcomes" :key="outcome">
                <tr class="bg-gray-200">
                  <td colspan="10" class="px-4 py-2 font-semibold text-gray-700 border border-gray-300">{{ outcome }}</td>
                </tr>

                <template v-for="(pis, subAbbrev) in subGroups" :key="subAbbrev">
                  <template v-for="(piPlans, piDesc) in pis" :key="piDesc">

                    <!-- First row per PI group -->
                    <tr class="hover:bg-gray-50">
                      <td v-if="Object.keys(pis)[0] === piDesc"
                          :rowspan="Object.values(pis).reduce((total, arr) => total + arr.length, 0)"
                          class="px-4 py-2 font-medium text-gray-700 border border-gray-300">{{ subAbbrev }}</td>
                      <td :rowspan="piPlans.length" class="px-4 py-2 border border-gray-300 font-medium">{{ piDesc }}</td>
                      <td class="px-4 py-2 border border-gray-300">{{ piPlans[0].success_indicator }}</td>
                      <td class="px-4 py-2 border border-gray-300">
                        <p class="text-sm">{{ piPlans[0].pivot?.accomplishment || '—' }}</p>
                        <button v-if="piPlans[0].accomplishments_count > 0" @click="openAccViewer(piPlans[0])"
                          class="mt-1 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full hover:bg-indigo-100">
                          📋 {{ piPlans[0].accomplishments_count }} accomplishment{{ piPlans[0].accomplishments_count > 1 ? 's' : '' }}
                        </button>
                        <span v-else class="block mt-1 text-xs text-gray-400 italic">No accomplishments</span>
                      </td>
                      <td class="px-4 py-2 border border-gray-300">
                        <a v-if="piPlans[0].pivot?.mov_link" :href="piPlans[0].pivot.mov_link" target="_blank"
                          class="text-blue-600 hover:underline break-all">{{ piPlans[0].pivot.mov_link }}</a>
                        <span v-else>—</span>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_quality ?? "—" }}</div>
                        <div>DC: {{ piPlans[0].pivot?.sup_quality ?? "—" }}</div>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_efficiency ?? "—" }}</div>
                        <div>DC: {{ piPlans[0].pivot?.sup_efficiency ?? "—" }}</div>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_timeliness ?? "—" }}</div>
                        <div>DC: {{ piPlans[0].pivot?.sup_timeliness ?? "—" }}</div>
                      </td>
                      <td class="px-4 py-2 text-center font-medium border border-gray-300">
                        <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_average ?? computeAverage(piPlans[0].pivot?.self_quality, piPlans[0].pivot?.self_efficiency, piPlans[0].pivot?.self_timeliness) }}</div>
                        <div>DC: {{ piPlans[0].pivot?.sup_average ?? computeAverage(piPlans[0].pivot?.sup_quality, piPlans[0].pivot?.sup_efficiency, piPlans[0].pivot?.sup_timeliness) }}</div>
                      </td>
                      <td class="px-4 py-2 border border-gray-300">{{ piPlans[0].pivot?.remarks || "—" }}</td>
                    </tr>

                    <!-- Remaining rows -->
                    <tr v-for="plan in piPlans.slice(1)" :key="plan.id" class="hover:bg-gray-50">
                      <td class="px-4 py-2 border border-gray-300">{{ plan.success_indicator }}</td>
                      <td class="px-4 py-2 border border-gray-300">
                        <p class="text-sm">{{ plan.pivot?.accomplishment || '—' }}</p>
                        <button v-if="plan.accomplishments_count > 0" @click="openAccViewer(plan)"
                          class="mt-1 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full hover:bg-indigo-100">
                          📋 {{ plan.accomplishments_count }} accomplishment{{ plan.accomplishments_count > 1 ? 's' : '' }}
                        </button>
                        <span v-else class="block mt-1 text-xs text-gray-400 italic">No accomplishments</span>
                      </td>
                      <td class="px-4 py-2 border border-gray-300">
                        <a v-if="plan.pivot?.mov_link" :href="plan.pivot.mov_link" target="_blank"
                          class="text-blue-600 hover:underline break-all">{{ plan.pivot.mov_link }}</a>
                        <span v-else>—</span>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_quality ?? "—" }}</div>
                        <div>DC: {{ plan.pivot?.sup_quality ?? "—" }}</div>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_efficiency ?? "—" }}</div>
                        <div>DC: {{ plan.pivot?.sup_efficiency ?? "—" }}</div>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_timeliness ?? "—" }}</div>
                        <div>DC: {{ plan.pivot?.sup_timeliness ?? "—" }}</div>
                      </td>
                      <td class="px-4 py-2 text-center font-medium border border-gray-300">
                        <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_average ?? computeAverage(plan.pivot?.self_quality, plan.pivot?.self_efficiency, plan.pivot?.self_timeliness) }}</div>
                        <div>DC: {{ plan.pivot?.sup_average ?? computeAverage(plan.pivot?.sup_quality, plan.pivot?.sup_efficiency, plan.pivot?.sup_timeliness) }}</div>
                      </td>
                      <td class="px-4 py-2 border border-gray-300">{{ plan.pivot?.remarks || "—" }}</td>
                    </tr>

                  </template>
                </template>
              </template>
            </template>
          </tbody>
        </table>

        <!-- Summary Table -->
        <br/>
        <table class="min-w-full border text-sm border-collapse">
          <thead class="bg-gray-100 text-gray-700 text-sm uppercase">
            <tr>
              <th rowspan="2" class="border px-4 py-2 text-center w-40">Output</th>
              <th colspan="4" class="border px-4 py-2 text-center">Rating</th>
              <th rowspan="2" class="border px-4 py-2 text-center w-24">% Weight</th>
              <th rowspan="2" class="border px-4 py-2 text-center w-40">Overall Weighted Score</th>
            </tr>
            <tr>
              <th class="border px-4 py-2 text-center w-16">Q<sup>1</sup></th>
              <th class="border px-4 py-2 text-center w-16">E<sup>2</sup></th>
              <th class="border px-4 py-2 text-center w-16">T<sup>3</sup></th>
              <th class="border px-4 py-2 text-center w-16">A<sup>4</sup></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, type) in summaryByFunctionType" :key="type" class="font-medium">
              <td class="border px-3 py-2">{{ type }}</td>
              <td class="border px-3 py-2 text-center">{{ row.countQ ? formatAvg(row.totalQ / row.countQ) : "—" }}</td>
              <td class="border px-3 py-2 text-center">{{ row.countE ? formatAvg(row.totalE / row.countE) : "—" }}</td>
              <td class="border px-3 py-2 text-center">{{ row.countT ? formatAvg(row.totalT / row.countT) : "—" }}</td>
              <td class="border px-3 py-2 text-center">{{ row.countA ? formatAvg(row.totalA / row.countA) : "—" }}</td>
              <td class="border px-3 py-2 text-center">{{ (row.weight * 100).toFixed(0) }}%</td>
              <td class="border px-3 py-2 text-center">{{ row.countA ? formatAvg((row.totalA / row.countA) * row.weight) : "—" }}</td>
            </tr>
            <tr class="bg-gray-50 text-gray-800">
              <td colspan="6" class="border px-3 py-3 text-left">TOTAL</td>
              <td class="border px-3 py-3 text-center font-bold">{{ finalIPCRRating }}</td>
            </tr>
            <tr class="bg-gray-50 text-gray-800">
              <td colspan="6" class="border px-3 py-3 text-left">Adjectival Rating</td>
              <td class="border px-3 py-3 text-center font-bold">{{ getAdjectivalRating(finalIPCRRating) }}</td>
            </tr>
            <tr class="bg-gray-50 text-gray-800">
              <td colspan="12" class="border px-3 py-3 text-left">
                Comments and Recommendations for Development Purposes: <i class="font-bold">{{ ipcr.remarks }}</i>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Signature footer -->
        <table class="min-w-full border text-sm border-collapse mt-2">
          <tr class="text-gray-800">
            <td colspan="2" class="border px-3 text-left">Discuss with:</td>
            <td colspan="1" class="border px-3 text-left">Date:</td>
            <td colspan="3" class="border px-3 text-left">Assessed by:</td>
            <td colspan="1" class="border px-3 text-left">Date:</td>
            <td colspan="3" class="border px-3 text-left">Final Rating by:</td>
            <td colspan="2" class="border px-3 text-left">Date:</td>
          </tr>
          <tr>
            <td colspan="2" class="border px-3 py-3 text-center">
              <br/><br/><b style="text-transform: uppercase;">{{ employee.name }}</b><br/><small>{{ employee.position }}</small>
            </td>
            <td colspan="1" class="border px-3 py-3 text-center"><br/><br/>{{ formattedSubmittedForReviewAt }}</td>
            <td colspan="3" class="border px-3 py-3 text-center">
              <br/><br/><b style="text-transform: uppercase;">{{ supervisor?.name ?? '—' }}</b><br/><small>{{ supervisor?.position ?? '' }}</small>
            </td>
            <td colspan="1" class="border px-3 py-3 text-center"><br/><br/>{{ formattedSubmittedRatingAt }}</td>
            <td colspan="3" class="border px-3 py-3 text-center">
              <br/><br/><b>ENGR. RAMIL A. SANCHEZ</b><br/><small>Director III</small>
            </td>
            <td colspan="2" class="border px-3 py-3 text-center"><br/><br/>____________________</td>
          </tr>
          <tr class="font-bold text-gray-800">
            <td colspan="12" class="border px-3 text-left">
              <small><i>Legend: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1 - Effectiveness/Quality &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 2 - Efficiency &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3 - Timeliness &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4 - Average</i></small>
            </td>
          </tr>
        </table>

      </div>
    </div>

    <!-- Accomplishments Viewer Modal -->
    <AppModal
      :show="!!accViewerPlan"
      title="Accomplishments"
      :subtitle="accViewerPlan?.success_indicator"
      size="2xl"
      panel-class="max-h-[80vh]"
      @close="closeAccViewer"
    >
      <div v-if="!accViewerPlan?.accomplishments?.length" class="py-16 text-center text-slate-400 text-sm">
        No daily accomplishments recorded.
      </div>
      <div v-else class="space-y-4">
        <div v-for="acc in accViewerPlan.accomplishments" :key="acc.id" class="border border-slate-100 rounded-lg p-3 text-sm">
          <div class="flex items-center justify-between mb-1">
            <span class="font-medium text-slate-700">{{ formatAccDate(acc.accomplishment_date) }}</span>
          </div>
          <p class="text-slate-600">{{ acc.description || "—" }}</p>
          <div v-if="acc.photos?.length" class="mt-2 flex flex-wrap gap-2">
            <a v-for="photo in acc.photos" :key="photo.id" :href="photo.url" target="_blank">
              <img :src="photo.url" class="h-16 w-16 object-cover rounded-lg border border-slate-200" />
            </a>
          </div>
        </div>
      </div>
    </AppModal>

  </AdminLayout>
</template>
