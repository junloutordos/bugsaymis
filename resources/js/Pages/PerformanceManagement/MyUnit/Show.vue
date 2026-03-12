<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { ArrowLeftIcon } from "@heroicons/vue/24/outline";
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";
import { useSubmit } from "@/Composables/useSubmit";
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass";
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating";

const props = defineProps({
  ipcr:     Object,
  plans:    Array,
  employee: Object,
  unitHead: Object,
});

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

// ---------- Status badge ----------
const statusBadge = ipcrStatusClass;

// ---------- Ratings shown at PMT stage ----------
const PMT_STAGES     = ["Rated & For PMT Review", "Submitted to PMT", "PMT Returned for Revision", "Approved by PMT"];
const showOnlySupRatings = computed(() => PMT_STAGES.includes(props.ipcr.status));
const isAtRatedStage     = computed(() => ["Submitted for Rating", ...PMT_STAGES].includes(props.ipcr.status));
</script>

<template>
  <Head :title="`IPCR – ${employee.name}`" />
  <AdminLayout :title="`My Unit – ${employee.name}`">

    <!-- Back -->
    <button
      @click="$inertia.get(route('my-unit-ipcr.index'))"
      class="mb-4 flex items-center gap-2 text-blue-600 hover:text-blue-800"
    >
      <ArrowLeftIcon class="w-5 h-5" /> Back to My Unit
    </button>

    <!-- IPCR card -->
    <div class="bg-white p-4 rounded-lg shadow mb-4">
      <div class="flex items-start justify-between flex-wrap gap-2">
        <div>
          <h2 class="text-xl font-semibold">{{ ipcr.title }}</h2>
          <p class="text-gray-500 text-sm">{{ employee.name }} — {{ employee.position }}</p>
          <p class="text-gray-500 text-sm">Rating Period: {{ ipcr.rating_period }}</p>
          <p class="text-xs text-gray-400 mt-0.5">Office: {{ employee.office?.name ?? "—" }}</p>
        </div>
        <span :class="statusBadge(ipcr.status)" class="px-3 py-1 text-sm font-semibold rounded-full self-start">
          {{ ipcr.status }}
        </span>
      </div>

      <!-- Rater note -->
      <div v-if="ipcr.status === 'Submitted for Rating'"
        class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        Plans marked <strong>Unit Head</strong> in your offices are available for rating. Click an accomplishment cell to rate.
      </div>
    </div>

    <!-- Plans Table -->
    <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
      <table class="min-w-full border border-gray-300 text-sm border-collapse">
        <thead class="bg-gray-100 text-gray-700 text-xs uppercase">
          <tr>
            <th rowspan="2" colspan="2" class="border px-4 py-2 text-center">Output</th>
            <th rowspan="2" class="border px-4 py-2">Success Indicators</th>
            <th rowspan="2" class="border px-4 py-2">Accomplishment</th>
            <th rowspan="2" class="border px-4 py-2">MOV Link</th>
            <th colspan="4" class="border px-4 py-2 text-center">Rating</th>
          </tr>
          <tr>
            <th class="border px-4 py-2 text-center">Q</th>
            <th class="border px-4 py-2 text-center">E</th>
            <th class="border px-4 py-2 text-center">T</th>
            <th class="border px-4 py-2 text-center">A</th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="!plans || plans.length === 0">
            <td colspan="9" class="border px-4 py-6 text-center text-gray-400">No plans assigned to this IPCR.</td>
          </tr>

          <template v-for="(outcomes, functionType) in groupedPlansByFunction" :key="functionType">
            <tr class="bg-gray-300">
              <td colspan="9" class="px-4 py-2 font-bold text-gray-800 border border-gray-300 uppercase">
                {{ functionType }}
              </td>
            </tr>

            <template v-for="(subGroups, outcome) in outcomes" :key="outcome">
              <tr class="bg-gray-200">
                <td colspan="9" class="px-4 py-2 font-semibold text-gray-700 border border-gray-300">
                  {{ outcome }}
                </td>
              </tr>

              <template v-for="(pis, subAbbrev) in subGroups" :key="subAbbrev">
                <template v-for="(piPlans, piDesc) in pis" :key="piDesc">

                  <!-- First row of PI group -->
                  <tr class="hover:bg-gray-50">
                    <td v-if="Object.keys(pis)[0] === piDesc"
                        :rowspan="Object.values(pis).reduce((t, a) => t + a.length, 0)"
                        class="px-4 py-2 font-medium text-gray-700 border border-gray-300 text-xs">
                      {{ subAbbrev !== '—' ? subAbbrev : '' }}
                    </td>
                    <td :rowspan="piPlans.length" class="px-4 py-2 border border-gray-300 font-medium text-xs">
                      {{ piDesc }}
                    </td>

                    <td class="px-4 py-2 border border-gray-300">
                      <div>{{ piPlans[0].success_indicator }}</div>
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
                      <span v-else class="block mt-1 text-xs text-gray-400 italic">No accomplishments</span>
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
                  <tr v-for="plan in piPlans.slice(1)" :key="plan.id" class="hover:bg-gray-50">
                    <td class="px-4 py-2 border border-gray-300">
                      <div>{{ plan.success_indicator }}</div>
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
                      <span v-else class="block mt-1 text-xs text-gray-400 italic">No accomplishments</span>
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

      <!-- Summary table (rated stages) -->
      <div v-if="isAtRatedStage" class="mt-6">
        <table class="min-w-full border text-sm border-collapse">
          <thead class="bg-gray-100 text-gray-700 text-xs uppercase">
            <tr>
              <th rowspan="2" class="border px-4 py-2 text-center w-40">Output</th>
              <th colspan="4" class="border px-4 py-2 text-center">Rating</th>
              <th rowspan="2" class="border px-4 py-2 text-center w-24">% Weight</th>
              <th rowspan="2" class="border px-4 py-2 text-center w-40">Weighted Score</th>
            </tr>
            <tr>
              <th class="border px-4 py-2 text-center w-16">Q</th>
              <th class="border px-4 py-2 text-center w-16">E</th>
              <th class="border px-4 py-2 text-center w-16">T</th>
              <th class="border px-4 py-2 text-center w-16">A</th>
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
            <tr class="bg-gray-50 font-semibold">
              <td colspan="6" class="border px-3 py-3 text-left">TOTAL</td>
              <td class="border px-3 py-3 text-center font-bold">{{ finalIPCRRating }}</td>
            </tr>
            <tr class="bg-gray-50">
              <td colspan="6" class="border px-3 py-3 text-left font-semibold">Adjectival Rating</td>
              <td class="border px-3 py-3 text-center font-bold">{{ ipcrAdjectivalRating(finalIPCRRating) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Rate Plan Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="text-lg font-semibold mb-4">Rate Accomplishment</h3>
        <div class="flex flex-col gap-3">
          <label class="text-sm font-medium">Accomplishment:
            <textarea v-model="form.accomplishment" rows="2" class="border rounded w-full px-2 py-1 mt-1 text-sm"></textarea>
          </label>
          <label class="text-sm font-medium">MOV Link:
            <input type="text" v-model="form.mov_link" class="border rounded w-full px-2 py-1 mt-1 text-sm" />
          </label>
          <div class="flex gap-3">
            <label class="flex-1 text-sm font-medium">Q (1–5)
              <input type="number" min="1" max="5" step="0.01" v-model="form.quality" class="border rounded w-full px-2 py-1 mt-1 text-sm" />
            </label>
            <label class="flex-1 text-sm font-medium">E (1–5)
              <input type="number" min="1" max="5" step="0.01" v-model="form.efficiency" class="border rounded w-full px-2 py-1 mt-1 text-sm" />
            </label>
            <label class="flex-1 text-sm font-medium">T (1–5)
              <input type="number" min="1" max="5" step="0.01" v-model="form.timeliness" class="border rounded w-full px-2 py-1 mt-1 text-sm" />
            </label>
            <div class="flex-1 text-sm font-medium">Avg
              <div class="border rounded px-2 py-1 mt-1 text-sm bg-gray-50 text-blue-700 font-semibold text-center">{{ liveAverage }}</div>
            </div>
          </div>
          <p class="text-xs text-gray-400">5 – Outstanding · 4 – Very Satisfactory · 3 – Satisfactory · 2 – Unsatisfactory · 1 – Poor</p>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button @click="isModalOpen = false" class="px-4 py-2 border rounded bg-gray-200 hover:bg-gray-300 text-sm">Cancel</button>
          <button @click="saveModal" :disabled="isSubmitting" class="px-4 py-2 border rounded bg-blue-600 text-white hover:bg-blue-700 text-sm disabled:opacity-50 disabled:cursor-not-allowed">{{ isSubmitting ? 'Saving…' : 'Save Rating' }}</button>
        </div>
      </div>
    </div>

    <!-- Accomplishments Viewer Modal -->
    <div v-if="accViewerPlan"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      @click.self="closeAccViewer">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col">
        <div class="flex items-start justify-between px-6 py-4 border-b">
          <div>
            <h2 class="text-base font-semibold text-gray-800">Accomplishments</h2>
            <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ accViewerPlan.success_indicator }}</p>
          </div>
          <button @click="closeAccViewer" class="text-gray-400 hover:text-gray-700 text-xl leading-none ml-4">✕</button>
        </div>

        <div class="overflow-y-auto flex-1 px-6 py-4">
          <div v-if="!accViewerPlan.accomplishments?.length"
            class="text-center text-gray-400 text-sm py-8">
            No accomplishments logged for this plan.
          </div>
          <div v-for="acc in accViewerPlan.accomplishments" :key="acc.id"
            class="mb-4 pb-4 border-b border-gray-100 last:border-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full">
                {{ formatAccDate(acc.accomplishment_date) }}
              </span>
            </div>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ acc.description }}</p>
            <div v-if="acc.photos?.length" class="mt-1.5 flex flex-wrap gap-2">
              <a v-for="photo in acc.photos" :key="photo.id"
                :href="photo.google_drive_link" target="_blank"
                class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                📎 {{ photo.file_name || 'Photo' }}
              </a>
            </div>
          </div>
        </div>

        <div class="px-6 py-3 border-t text-right">
          <span class="text-xs text-gray-400 mr-4">
            {{ accViewerPlan.accomplishments_count }} accomplishment(s) total
          </span>
          <button @click="closeAccViewer" class="px-4 py-2 bg-gray-200 rounded-lg text-sm hover:bg-gray-300">Close</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
