<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppBreadcrumb from "@/Components/AppBreadcrumb.vue";
import AppCard from "@/Components/AppCard.vue";
import AppBadge from "@/Components/AppBadge.vue";
import AppButton from "@/Components/AppButton.vue";
import AppInput from "@/Components/AppInput.vue";
import AppTextarea from "@/Components/AppTextarea.vue";
import AppModal from "@/Components/AppModal.vue";
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";
import { useSubmit } from "@/Composables/useSubmit";
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating";

const props = defineProps({
  ipcr:     Object,
  plans:    Array,
  employee: Object,
  unitHead: Object,
  isMutable:    { type: Boolean, default: true },
  periodClosed: { type: Boolean, default: false },
});

// ---------- Breadcrumb ----------
const breadcrumbItems = computed(() => [
  { label: "My Unit", href: route("my-unit-ipcr.index") },
  { label: props.employee.name },
]);

// ---------- Status badge ----------
function statusColor(status) {
  const map = {
    "New Target":                "blue",
    "For Review":                "amber",
    "Targets Approved":          "green",
    "Submitted for Rating":      "orange",
    "Rated & For PMT Review":    "purple",
    "Submitted to PMT":          "purple",
    "PMT Returned for Revision": "red",
    "Submitted to HR":           "blue",
    "Approved by PMT":           "green",
    "Director Signed":           "green",
    "Returned for Revision":     "red",
    "Rejected":                  "red",
  };
  return map[status] ?? "slate";
}

// ---------- Rating helpers ----------
const computeAverage = (q, e, t) => {
  const vals = [q, e, t].filter(v => v !== null && v !== "" && !isNaN(v)).map(Number);
  if (!vals.length) return "—";
  return (vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(2);
};

const formatAvg = (num) => {
  if (num === null || num === undefined || isNaN(num)) return "—";
  return Number(num).toFixed(2);
};

// ---------- Plan grouping (mirrors EmployeeIPCRShow) ----------
const normalizeFunctionType = (raw) => {
  if (!raw) return "Uncategorized";
  const t = String(raw).trim().toLowerCase();
  if (t.includes("strategic")) return "Strategic Functions";
  if (t.includes("core"))      return "Core Functions";
  if (t.includes("support"))   return "Support Functions";
  return String(raw).trim();
};

const functionTypeOrder = { "Strategic Functions": 1, "Core Functions": 2, "Support Functions": 3, "Uncategorized": 4 };
const functionTypeWeights = { "Strategic Functions": 0.30, "Core Functions": 0.55, "Support Functions": 0.15, "Uncategorized": 0 };

const groupedPlansByFunction = computed(() => {
  const groups = {};
  (props.plans || []).forEach(plan => {
    const aoo  = plan.performance_indicator?.agency_outcome;
    const ft   = normalizeFunctionType(aoo?.function_type);
    const out  = aoo?.outcome    || "Uncategorized";
    const sub  = (aoo?.sub_outcome || "—") !== "—" ? (aoo.sub_outcome || "—").slice(0, 4) : "—";
    const piD  = plan.performance_indicator?.description || "—";

    if (!groups[ft])            groups[ft] = {};
    if (!groups[ft][out])       groups[ft][out] = {};
    if (!groups[ft][out][sub])  groups[ft][out][sub] = {};
    if (!groups[ft][out][sub][piD]) groups[ft][out][sub][piD] = [];
    groups[ft][out][sub][piD].push(plan);
  });

  const sorted = {};
  Object.keys(functionTypeOrder).forEach(ft => { if (groups[ft]) sorted[ft] = groups[ft]; });
  Object.keys(groups).filter(ft => !functionTypeOrder[ft]).sort().forEach(ft => (sorted[ft] = groups[ft]));
  Object.keys(sorted).forEach(ft => {
    const so = {};
    Object.keys(sorted[ft]).sort().forEach(out => {
      so[out] = {};
      Object.keys(sorted[ft][out]).sort().forEach(sub => {
        const sp = {};
        Object.keys(sorted[ft][out][sub]).sort().forEach(pi => { sp[pi] = sorted[ft][out][sub][pi]; });
        so[out][sub] = sp;
      });
    });
    sorted[ft] = so;
  });
  return sorted;
});

// ---------- Summary ----------
const summaryByFunctionType = computed(() => {
  const s = {};
  (props.plans || []).forEach(plan => {
    const ft = normalizeFunctionType(plan.performance_indicator?.agency_outcome?.function_type);
    if (!s[ft]) s[ft] = { totalQ: 0, countQ: 0, totalE: 0, countE: 0, totalT: 0, countT: 0, totalA: 0, countA: 0, weight: functionTypeWeights[ft] ?? 0 };
    const piv = plan.pivot;
    if (!piv) return;
    const Q = piv.sup_quality, E = piv.sup_efficiency, T = piv.sup_timeliness;
    if (Q !== null && !isNaN(Q)) { s[ft].totalQ += Number(Q); s[ft].countQ++; }
    if (E !== null && !isNaN(E)) { s[ft].totalE += Number(E); s[ft].countE++; }
    if (T !== null && !isNaN(T)) { s[ft].totalT += Number(T); s[ft].countT++; }
    let avg = null;
    if (piv.sup_average !== null && !isNaN(piv.sup_average)) avg = Number(piv.sup_average);
    else { const c = [Q,E,T].filter(v=>v!==null&&!isNaN(v)).map(Number); if(c.length) avg = c.reduce((a,b)=>a+b,0)/c.length; }
    if (avg !== null) { s[ft].totalA += avg; s[ft].countA++; }
  });
  return Object.keys(s).sort((a,b) => (functionTypeOrder[a]||99) - (functionTypeOrder[b]||99))
    .reduce((o,k) => { o[k] = s[k]; return o; }, {});
});

const finalIPCRRating = computed(() => {
  let total = 0;
  for (const [, e] of Object.entries(summaryByFunctionType.value)) {
    if (e.countA) total += (e.totalA / e.countA) * (e.weight || 0);
  }
  return Number(total).toFixed(2);
});

const { isSubmitting, submit } = useSubmit();

// ---------- Per-plan rating authorization ----------
// Unit heads can only rate plans whose rated_by = 'Unit Head'
// AND one of the plan's offices has unit_head = this user
const canRatePlan = (plan) => {
  if (!props.isMutable) return false;
  if (props.ipcr.status !== "Submitted for Rating") return false;
  if (plan.rated_by !== "Unit Head") return false;
  return plan.offices?.some(o => o.unit_head == props.unitHead?.id) ?? false;
};

// ---------- Rating modal ----------
const isModalOpen  = ref(false);
const currentPlan  = ref(null);
const form = ref({ accomplishment: "", mov_link: "", quality: null, efficiency: null, timeliness: null });

const liveAverage = computed(() => computeAverage(form.value.quality, form.value.efficiency, form.value.timeliness));

const openModal = (plan) => {
  if (!canRatePlan(plan)) return;
  currentPlan.value = plan;
  form.value = {
    accomplishment: plan.pivot?.accomplishment || "",
    mov_link:       plan.pivot?.mov_link       || "",
    quality:        plan.pivot?.sup_quality    ?? null,
    efficiency:     plan.pivot?.sup_efficiency ?? null,
    timeliness:     plan.pivot?.sup_timeliness ?? null,
  };
  isModalOpen.value = true;
};

const saveModal = () => {
  if (!currentPlan.value) return;
  if (!form.value.accomplishment?.trim() || !form.value.mov_link?.trim()) {
    Swal.fire({ icon: "warning", title: "Missing Required Fields", text: "Please fill in BOTH the Accomplishment and MOV Link before saving.", confirmButtonColor: "#2563eb" });
    return;
  }
  submit(
    (o) => router.put(
      route("division-chief-employee-ipcr-plan.rateIPCRPlan", [props.ipcr.id, currentPlan.value.id]),
      { accomplishment: form.value.accomplishment, mov_link: form.value.mov_link, sup_quality: form.value.quality, sup_efficiency: form.value.efficiency, sup_timeliness: form.value.timeliness },
      o
    ),
    {
      onSuccess: () => {
        const avg = computeAverage(form.value.quality, form.value.efficiency, form.value.timeliness);
        currentPlan.value.pivot.accomplishment  = form.value.accomplishment;
        currentPlan.value.pivot.mov_link        = form.value.mov_link;
        currentPlan.value.pivot.sup_quality     = form.value.quality;
        currentPlan.value.pivot.sup_efficiency  = form.value.efficiency;
        currentPlan.value.pivot.sup_timeliness  = form.value.timeliness;
        currentPlan.value.pivot.sup_average     = avg;
        isModalOpen.value = false;
        Swal.fire({ icon: "success", title: "Saved!", timer: 2000, showConfirmButton: false });
      },
      onError: () => Swal.fire({ icon: "error", title: "Error", text: "Failed to save. Please check your input." }),
    }
  );
};

// ---------- Accomplishments viewer ----------
const accViewerPlan = ref(null);
const openAccViewer  = (plan) => { accViewerPlan.value = plan; };
const closeAccViewer = ()     => { accViewerPlan.value = null; };
const formatAccDate  = (d)    => d ? new Date(d).toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" }) : "—";

// ---------- Ratings shown at PMT stage ----------
const PMT_STAGES     = ["Rated & For PMT Review", "Submitted to PMT", "PMT Returned for Revision", "Approved by PMT"];
const showOnlySupRatings = computed(() => PMT_STAGES.includes(props.ipcr.status));
const isAtRatedStage     = computed(() => ["Submitted for Rating", ...PMT_STAGES].includes(props.ipcr.status));
</script>

<template>
  <Head :title="`IPCR – ${employee.name}`" />
  <AdminLayout :title="`My Unit – ${employee.name}`">
    <div class="space-y-4">

      <AppBreadcrumb :items="breadcrumbItems" />

      <!-- IPCR card -->
      <AppCard>
        <div class="flex items-start justify-between flex-wrap gap-2">
          <div>
            <h2 class="text-xl font-semibold text-slate-800">{{ ipcr.title }}</h2>
            <p class="text-slate-500 text-sm mt-0.5">{{ employee.name }} — {{ employee.position }}</p>
            <p class="text-slate-500 text-sm">Rating Period: {{ ipcr.rating_period }}</p>
            <p class="text-xs text-slate-400 mt-0.5">Office: {{ employee.office?.name ?? "—" }}</p>
          </div>
          <AppBadge :color="statusColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
        </div>

        <!-- Rater note -->
        <div v-if="ipcr.status === 'Submitted for Rating'"
          class="mt-3 text-xs text-warning-700 bg-warning-50 border border-warning-100 rounded-lg px-3 py-2">
          Plans marked <strong>Unit Head</strong> in your offices are available for rating. Click an accomplishment cell to rate.
        </div>
      </AppCard>

      <!-- Plans Table -->
      <AppCard :padded="false">
        <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-300 text-sm border-collapse">
          <thead class="bg-slate-50 text-slate-700 text-xs uppercase">
            <tr>
              <th rowspan="2" colspan="2" class="border px-4 py-2 text-center font-semibold text-slate-500 tracking-wide">Output</th>
              <th rowspan="2" class="border px-4 py-2 font-semibold text-slate-500 tracking-wide">Success Indicators</th>
              <th rowspan="2" class="border px-4 py-2 font-semibold text-slate-500 tracking-wide">Accomplishment</th>
              <th rowspan="2" class="border px-4 py-2 font-semibold text-slate-500 tracking-wide">MOV Link</th>
              <th colspan="4" class="border px-4 py-2 text-center font-semibold text-slate-500 tracking-wide">Rating</th>
            </tr>
            <tr>
              <th class="border px-4 py-2 text-center font-semibold text-slate-500 tracking-wide">Q</th>
              <th class="border px-4 py-2 text-center font-semibold text-slate-500 tracking-wide">E</th>
              <th class="border px-4 py-2 text-center font-semibold text-slate-500 tracking-wide">T</th>
              <th class="border px-4 py-2 text-center font-semibold text-slate-500 tracking-wide">A</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="!plans || plans.length === 0">
              <td colspan="9" class="border px-4 py-16 text-center text-slate-400">No plans assigned to this IPCR.</td>
            </tr>

            <template v-for="(outcomes, functionType) in groupedPlansByFunction" :key="functionType">
              <tr class="bg-slate-200">
                <td colspan="9" class="px-4 py-2 font-bold text-slate-700 border border-gray-300 uppercase">
                  {{ functionType }}
                </td>
              </tr>

              <template v-for="(subGroups, outcome) in outcomes" :key="outcome">
                <tr class="bg-slate-100">
                  <td colspan="9" class="px-4 py-2 font-semibold text-slate-600 border border-gray-300">
                    {{ outcome }}
                  </td>
                </tr>

                <template v-for="(pis, subAbbrev) in subGroups" :key="subAbbrev">
                  <template v-for="(piPlans, piDesc) in pis" :key="piDesc">

                    <!-- First row of PI group -->
                    <tr class="hover:bg-slate-50/60">
                      <td v-if="Object.keys(pis)[0] === piDesc"
                          :rowspan="Object.values(pis).reduce((t, a) => t + a.length, 0)"
                          class="px-4 py-2 font-medium text-slate-600 border border-gray-300 text-xs">
                        {{ subAbbrev !== '—' ? subAbbrev : '' }}
                      </td>
                      <td :rowspan="piPlans.length" class="px-4 py-2 border border-gray-300 font-medium text-slate-700 text-xs">
                        {{ piDesc }}
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <div>{{ piPlans[0].success_indicator }}</div>
                        <p v-if="piPlans[0].pivot?.individual_target" class="mt-1 text-xs text-slate-500 whitespace-pre-line">{{ piPlans[0].pivot.individual_target }}</p>
                        <div class="text-xs mt-0.5">
                          <span :class="piPlans[0].rated_by === 'Unit Head' ? 'text-blue-600 font-medium' : 'text-gray-400'">
                            Rater: {{ piPlans[0].rated_by || 'Division Chief' }}
                          </span>
                          <template v-if="piPlans[0].offices?.length">
                            — {{ piPlans[0].offices.map(o => o.name).join(', ') }}
                          </template>
                        </div>
                      </td>

                      <!-- Accomplishment cell -->
                      <td class="px-4 py-2 border border-gray-300">
                        <p
                          :class="canRatePlan(piPlans[0]) ? 'text-blue-600 cursor-pointer hover:underline' : 'text-gray-600 cursor-default'"
                          @click="canRatePlan(piPlans[0]) ? openModal(piPlans[0]) : null"
                        >
                          {{ piPlans[0].pivot?.accomplishment || '—' }}
                        </p>
                        <button
                          v-if="piPlans[0].accomplishments_count > 0"
                          @click="openAccViewer(piPlans[0])"
                          class="mt-1 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full hover:bg-indigo-100"
                        >
                          📋 {{ piPlans[0].accomplishments_count }} accomplishment{{ piPlans[0].accomplishments_count > 1 ? 's' : '' }}
                        </button>
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <a v-if="piPlans[0].pivot?.mov_link" :href="piPlans[0].pivot.mov_link" target="_blank"
                           class="text-blue-600 hover:underline break-all text-xs">
                          {{ piPlans[0].pivot.mov_link }}
                        </a>
                        <span v-else>—</span>
                      </td>

                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_quality ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_quality ?? "—" }}</div>
                          <div>Sup: {{ piPlans[0].pivot?.sup_quality ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_efficiency ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_efficiency ?? "—" }}</div>
                          <div>Sup: {{ piPlans[0].pivot?.sup_efficiency ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_timeliness ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_timeliness ?? "—" }}</div>
                          <div>Sup: {{ piPlans[0].pivot?.sup_timeliness ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center font-medium border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_average ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_average ?? "—" }}</div>
                          <div>Sup: {{ piPlans[0].pivot?.sup_average ?? "—" }}</div>
                        </template>
                      </td>
                    </tr>

                    <!-- Remaining rows -->
                    <tr v-for="plan in piPlans.slice(1)" :key="plan.id" class="hover:bg-slate-50/60">
                      <td class="px-4 py-2 border border-gray-300">
                        <div>{{ plan.success_indicator }}</div>
                        <p v-if="plan.pivot?.individual_target" class="mt-1 text-xs text-slate-500 whitespace-pre-line">{{ plan.pivot.individual_target }}</p>
                        <div class="text-xs mt-0.5">
                          <span :class="plan.rated_by === 'Unit Head' ? 'text-blue-600 font-medium' : 'text-gray-400'">
                            Rater: {{ plan.rated_by || 'Division Chief' }}
                          </span>
                          <template v-if="plan.offices?.length">
                            — {{ plan.offices.map(o => o.name).join(', ') }}
                          </template>
                        </div>
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <p
                          :class="canRatePlan(plan) ? 'text-blue-600 cursor-pointer hover:underline' : 'text-gray-600 cursor-default'"
                          @click="canRatePlan(plan) ? openModal(plan) : null"
                        >
                          {{ plan.pivot?.accomplishment || '—' }}
                        </p>
                        <button
                          v-if="plan.accomplishments_count > 0"
                          @click="openAccViewer(plan)"
                          class="mt-1 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full hover:bg-indigo-100"
                        >
                          📋 {{ plan.accomplishments_count }} accomplishment{{ plan.accomplishments_count > 1 ? 's' : '' }}
                        </button>
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <a v-if="plan.pivot?.mov_link" :href="plan.pivot.mov_link" target="_blank"
                           class="text-blue-600 hover:underline break-all text-xs">{{ plan.pivot.mov_link }}</a>
                        <span v-else>—</span>
                      </td>

                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_quality ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_quality ?? "—" }}</div>
                          <div>Sup: {{ plan.pivot?.sup_quality ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_efficiency ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_efficiency ?? "—" }}</div>
                          <div>Sup: {{ plan.pivot?.sup_efficiency ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_timeliness ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_timeliness ?? "—" }}</div>
                          <div>Sup: {{ plan.pivot?.sup_timeliness ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center font-medium border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_average ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_average ?? "—" }}</div>
                          <div>Sup: {{ plan.pivot?.sup_average ?? "—" }}</div>
                        </template>
                      </td>
                    </tr>

                  </template>
                </template>
              </template>
            </template>
          </tbody>
        </table>
        </div>

        <!-- Summary table (rated stages) -->
        <div v-if="isAtRatedStage" class="p-5">
          <table class="min-w-full border text-sm border-collapse">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
              <tr>
                <th rowspan="2" class="border px-4 py-2 text-center w-40 font-semibold tracking-wide">Output</th>
                <th colspan="4" class="border px-4 py-2 text-center font-semibold tracking-wide">Rating</th>
                <th rowspan="2" class="border px-4 py-2 text-center w-24 font-semibold tracking-wide">% Weight</th>
                <th rowspan="2" class="border px-4 py-2 text-center w-40 font-semibold tracking-wide">Weighted Score</th>
              </tr>
              <tr>
                <th class="border px-4 py-2 text-center w-16 font-semibold tracking-wide">Q</th>
                <th class="border px-4 py-2 text-center w-16 font-semibold tracking-wide">E</th>
                <th class="border px-4 py-2 text-center w-16 font-semibold tracking-wide">T</th>
                <th class="border px-4 py-2 text-center w-16 font-semibold tracking-wide">A</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, type) in summaryByFunctionType" :key="type" class="font-medium">
                <td class="border px-3 py-2 text-slate-700">{{ type }}</td>
                <td class="border px-3 py-2 text-center text-slate-700">{{ row.countQ ? formatAvg(row.totalQ / row.countQ) : "—" }}</td>
                <td class="border px-3 py-2 text-center text-slate-700">{{ row.countE ? formatAvg(row.totalE / row.countE) : "—" }}</td>
                <td class="border px-3 py-2 text-center text-slate-700">{{ row.countT ? formatAvg(row.totalT / row.countT) : "—" }}</td>
                <td class="border px-3 py-2 text-center text-slate-700">{{ row.countA ? formatAvg(row.totalA / row.countA) : "—" }}</td>
                <td class="border px-3 py-2 text-center text-slate-700">{{ (row.weight * 100).toFixed(0) }}%</td>
                <td class="border px-3 py-2 text-center text-slate-700">{{ row.countA ? formatAvg((row.totalA / row.countA) * row.weight) : "—" }}</td>
              </tr>
              <tr class="bg-slate-50 font-semibold">
                <td colspan="6" class="border px-3 py-3 text-left text-slate-700">TOTAL</td>
                <td class="border px-3 py-3 text-center font-bold text-slate-800">{{ finalIPCRRating }}</td>
              </tr>
              <tr class="bg-slate-50">
                <td colspan="6" class="border px-3 py-3 text-left font-semibold text-slate-700">Adjectival Rating</td>
                <td class="border px-3 py-3 text-center font-bold text-slate-800">{{ ipcrAdjectivalRating(finalIPCRRating) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

    </div>

    <!-- Rate Plan Modal -->
    <AppModal :show="isModalOpen" title="Rate Accomplishment" size="lg" @close="isModalOpen = false">
      <div class="space-y-4">
        <AppTextarea v-model="form.accomplishment" label="Accomplishment" :rows="2" />
        <AppInput v-model="form.mov_link" label="MOV Link" type="text" />
        <div class="grid grid-cols-4 gap-3">
          <AppInput v-model="form.quality" label="Q (1–5)" type="number" min="1" max="5" step="1" />
          <AppInput v-model="form.efficiency" label="E (1–5)" type="number" min="1" max="5" step="1" />
          <AppInput v-model="form.timeliness" label="T (1–5)" type="number" min="1" max="5" step="1" />
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Avg</label>
            <div class="rounded-lg border border-slate-200 px-2 py-2 text-sm bg-slate-50 text-indigo-700 font-semibold text-center">{{ liveAverage }}</div>
          </div>
        </div>
        <p class="text-xs text-slate-400">5 – Outstanding · 4 – Very Satisfactory · 3 – Satisfactory · 2 – Unsatisfactory · 1 – Poor</p>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="isModalOpen = false">Cancel</AppButton>
        <AppButton :loading="isSubmitting" @click="saveModal">{{ isSubmitting ? 'Saving…' : 'Save Rating' }}</AppButton>
      </template>
    </AppModal>

    <!-- Accomplishments Viewer Modal -->
    <AppModal :show="!!accViewerPlan" size="xl" @close="closeAccViewer">
      <template #header>
        <div class="min-w-0">
          <h2 class="text-base font-semibold text-slate-800">Accomplishments</h2>
          <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ accViewerPlan?.success_indicator }}</p>
        </div>
      </template>

      <div v-if="!accViewerPlan?.accomplishments?.length"
        class="py-16 text-center text-slate-400 text-sm">
        No accomplishments logged for this plan.
      </div>
      <div v-for="acc in accViewerPlan?.accomplishments" :key="acc.id"
        class="mb-4 pb-4 border-b border-slate-100 last:border-0">
        <div class="flex items-center gap-2 mb-1">
          <AppBadge color="indigo">{{ formatAccDate(acc.accomplishment_date) }}</AppBadge>
        </div>
        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ acc.description }}</p>
        <div v-if="acc.photos?.length" class="mt-1.5 flex flex-wrap gap-2">
          <a v-for="photo in acc.photos" :key="photo.id"
            :href="photo.google_drive_link" target="_blank"
            class="text-xs text-indigo-600 hover:underline flex items-center gap-1">
            {{ photo.file_name || 'Photo' }}
          </a>
        </div>
      </div>

      <template #footer>
        <span class="mr-auto text-xs text-slate-400">
          {{ accViewerPlan?.accomplishments_count }} accomplishment(s) total
        </span>
        <AppButton variant="secondary" @click="closeAccViewer">Close</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
