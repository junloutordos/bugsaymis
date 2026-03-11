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
  ipcr: Object,
  employee: Object,
  supervisor: Object,
  plans: Array,
  workPlans: { type: Array, default: () => [] },
});

const { isSubmitting, submit } = useSubmit();

// ---------- Rating visibility ----------
const PRE_RATING_STATUSES = ['New Target', 'Draft', 'For Review', 'For Rating', 'Targets Approved', 'Returned for Revision'];
const showRatings = computed(() => !PRE_RATING_STATUSES.includes(props.ipcr.status));

// Show header block, footer, supervisor ratings, and print button for all post-rating stages
const PMT_STAGES = ['Rated & For PMT Review', 'Submitted to PMT', 'PMT Returned for Revision', 'Approved by PMT'];
const isAtPMTStage = computed(() => PMT_STAGES.includes(props.ipcr.status));

// ---------- Helpers ----------
const hasSupervisorRating = (pivot) => {
  if (!pivot) return false;
  return (
    pivot.sup_quality !== null &&
    pivot.sup_efficiency !== null &&
    pivot.sup_timeliness !== null
  );
};

// Returns formatted string or "—"
const computeAverage = (q, e, t) => {
  const values = [q, e, t].filter(v => v !== null && v !== "" && !isNaN(v)).map(Number);
  if (!values.length) return "—";
  return (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2);
};

// Returns numeric average or null (for internal calculations)
const computeNumericAverage = (q, e, t) => {
  const values = [q, e, t].filter(v => v !== null && v !== "" && !isNaN(v)).map(Number);
  if (!values.length) return null;
  return values.reduce((a, b) => a + b, 0) / values.length;
};

const formatAvg = (num) => {
  if (num === null || num === undefined || isNaN(num)) return "—";
  return Number(num).toFixed(2);
};

// ---------- Date / Rating period helpers ----------
/**
 * Extract a 4-digit year from rating_period string (e.g. "Jan - Dec 2025" -> "2025").
 * Returns empty string if none found.
 */
const extractYearFromRatingPeriod = (ratingPeriod) => {
  if (!ratingPeriod || typeof ratingPeriod !== "string") return "";
  const m = ratingPeriod.match(/(19|20)\d{2}/); // matches 1900-2099
  return m ? m[0] : "";
};

/**
 * Safely format a date-like value to "Month dd, yyyy".
 * Accepts ISO strings, timestamps, or Date-parseable strings.
 * Returns '—' if not present or invalid.
 */
const formatDateString = (value) => {
  if (!value) return "—";
  // If value is already a Date object
  let d;
  if (value instanceof Date) {
    d = value;
  } else {
    // Try parse numeric timestamp or string
    let parsed = Date.parse(value);
    // If parse fails, try to coerce numeric string (e.g. "/Date(1600000000000)/" or plain epoch)
    if (isNaN(parsed)) {
      const digits = String(value).match(/\d{9,}/); // epoch-ish
      if (digits) parsed = parseInt(digits[0], 10);
    }
    if (isNaN(parsed)) return value || "—"; // fallback to original raw string if nothing else
    d = new Date(parsed);
  }
  if (isNaN(d)) return "—";
  // Format: Month dd, yyyy (e.g. December 11, 2025)
  return d.toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" });
};

// ---------- Daily Accomplishments Viewer ----------
const accViewerPlan = ref(null); // plan whose daily accomplishments we're viewing

function openAccViewer(plan) {
  accViewerPlan.value = plan;
}
function closeAccViewer() {
  accViewerPlan.value = null;
}

function formatAccDate(d) {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" });
}

// ---------- Modal / Form State ----------
const isModalOpen = ref(false);
const currentPlan = ref(null);
const form = ref({
  accomplishment: "",
  mov_link: "",
  quality: null,
  efficiency: null,
  timeliness: null,
});

const canEditGlobally = computed(() => props.ipcr?.status === "Targets Approved");
const liveAverage = computed(() =>
  computeAverage(form.value.quality, form.value.efficiency, form.value.timeliness)
);

// ---------- Derived date / year computed props ----------
const ratingYear = computed(() => extractYearFromRatingPeriod(props.ipcr?.rating_period || ""));

// Formatted dates for signature / table (returns '—' if not present/invalid)
const formattedSubmittedForReviewAt = computed(() => formatDateString(props.ipcr?.submitted_for_review_at));
const formattedTargetApprovedAt = computed(() => formatDateString(props.ipcr?.target_approved_at));
const formattedSubmittedRatingAt = computed(() => formatDateString(props.ipcr?.submitted_rating_at));

// ---------- normalize function type (maps old -> new canonical labels) ----------
const normalizeFunctionType = (raw) => {
  if (!raw) return "Uncategorized";
  const t = String(raw).trim().toLowerCase();

  // Map possible legacy values to canonical new labels
  if (t === "strategic" || t === "strategic functions" || t === "strategic function") return "Strategic Functions";
  if (t === "core" || t === "core functions" || t === "core function") return "Core Functions";
  if (t === "support" || t === "support functions" || t === "support function") return "Support Functions";

  // If raw already is one of the canonical labels (case-insensitive)
  if (t === "strategic functions") return "Strategic Functions";
  if (t === "core functions") return "Core Functions";
  if (t === "support functions") return "Support Functions";

  // Otherwise, title-case the raw value and return as-is (keeps unknown/new categories)
  return String(raw).trim();
};

// ---------- Default CSC Weights (canonical labels) ----------
const functionTypeWeights = {
  "Strategic Functions": 0.30,
  "Core Functions": 0.55,
  "Support Functions": 0.15,
  "Uncategorized": 0
};

// Desired ordering (canonical labels first)
const functionTypeOrder = {
  "Strategic Functions": 1,
  "Core Functions": 2,
  "Support Functions": 3,
  "Uncategorized": 4
};

const groupedPlansByFunction = computed(() => {
  const groups = {};

  (props.plans || []).forEach(plan => {
    const aoo = plan.performance_indicator?.agency_outcome;

    const rawFT = aoo?.function_type;
    const functionType = normalizeFunctionType(rawFT);

    const outcome = aoo?.outcome || "Uncategorized";
    const subOutcome = aoo?.sub_outcome || "—";
    const subAbbrev = subOutcome !== "—" ? subOutcome.slice(0, 4) : subOutcome;

    const piDesc = plan.performance_indicator?.description || "—";

    // Initialize buckets
    if (!groups[functionType]) groups[functionType] = {};
    if (!groups[functionType][outcome]) groups[functionType][outcome] = {};
    if (!groups[functionType][outcome][subAbbrev]) groups[functionType][outcome][subAbbrev] = {};

    // NEW: Group by Performance Indicator description
    if (!groups[functionType][outcome][subAbbrev][piDesc]) {
      groups[functionType][outcome][subAbbrev][piDesc] = [];
    }

    groups[functionType][outcome][subAbbrev][piDesc].push(plan);
  });

  // Apply ordering
  const sorted = {};

  Object.keys(functionTypeOrder).forEach(ft => {
    if (groups[ft]) sorted[ft] = groups[ft];
  });

  Object.keys(groups)
    .filter(ft => !functionTypeOrder[ft])
    .sort()
    .forEach(ft => (sorted[ft] = groups[ft]));

  // Sort nested keys alphabetically
  Object.keys(sorted).forEach(ft => {
    const outcomes = sorted[ft];
    const sortedOutcomes = {};

    Object.keys(outcomes)
      .sort()
      .forEach(outcome => {
        sortedOutcomes[outcome] = {};
        Object.keys(outcomes[outcome])
          .sort()
          .forEach(sub => {
            // Sort PI descriptions as well
            const pis = outcomes[outcome][sub];
            const sortedPI = {};

            Object.keys(pis)
              .sort()
              .forEach(piDesc => {
                sortedPI[piDesc] = pis[piDesc];
              });

            sortedOutcomes[outcome][sub] = sortedPI;
          });
      });

    sorted[ft] = sortedOutcomes;
  });

  return sorted;
});


// ---------- Modal control ----------
const openModal = (plan) => {
  if (!canEditGlobally.value) return;

  currentPlan.value = plan;
  const pivot = plan.pivot || {};
  const useSupervisor = hasSupervisorRating(pivot);

  form.value = {
    accomplishment: pivot.accomplishment || "",
    mov_link: pivot.mov_link || "",
    quality: useSupervisor ? pivot.sup_quality : (pivot.self_quality ?? null),
    efficiency: useSupervisor ? pivot.sup_efficiency : (pivot.self_efficiency ?? null),
    timeliness: useSupervisor ? pivot.sup_timeliness : (pivot.self_timeliness ?? null),
  };

  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  currentPlan.value = null;
};

const saveModal = async () => {
  if (!currentPlan.value) return;

  const pivot = currentPlan.value.pivot || {};
  if (hasSupervisorRating(pivot)) {
    Swal.fire({
      icon: "warning",
      title: "Supervisor Rating Present",
      text: "This plan has already been rated by your supervisor. Self ratings cannot be changed.",
    });
    return;
  }

  if (!form.value.accomplishment?.trim() || !form.value.mov_link?.trim()) {
    Swal.fire({
      icon: "warning",
      title: "Missing Required Fields",
      text: "Please fill in BOTH the Accomplishment and MOV Link before saving.",
      confirmButtonColor: "#2563eb"
    });
    return;
  }

  const payload = {
    accomplishment: form.value.accomplishment,
    mov_link: form.value.mov_link,
    self_quality: form.value.quality,
    self_efficiency: form.value.efficiency,
    self_timeliness: form.value.timeliness,
  };

  submit(
    (o) => router.put(
      route("employee-ipcr-plan.updateSelfRating", [props.ipcr.id, currentPlan.value.id]),
      payload,
      o
    ),
    {
      onSuccess: () => {
        const avg = computeAverage(form.value.quality, form.value.efficiency, form.value.timeliness);
        Object.assign(currentPlan.value.pivot, {
          accomplishment: form.value.accomplishment,
          mov_link: form.value.mov_link,
          self_quality: form.value.quality,
          self_efficiency: form.value.efficiency,
          self_timeliness: form.value.timeliness,
          self_average: avg
        });
        isModalOpen.value = false;
        currentPlan.value = null;

        Swal.fire({
          icon: "success",
          title: "Saved!",
          text: "Accomplishment and ratings saved successfully.",
          timer: 1800,
          showConfirmButton: false,
        });
      },
      onError: () => Swal.fire({
        icon: "error",
        title: "Error",
        text: "Failed to save. Please check your input.",
      })
    }
  );
};

// ---------- Actions ----------
const submitForReview = () => {
  Swal.fire({
    title: "Submit Target?",
    text: "Are you sure you want to submit this target for review and approval?",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Yes, submit",
  }).then((result) => {
    if (result.isConfirmed) {
      submit.post(route("employee-ipcr.submitReview", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Submitted!", "Target submitted for review.", "success"),
        onError: () => Swal.fire("Error", "Failed to submit target.", "error")
      });
    }
  });
};

const submitForRating = () => {
  Swal.fire({
    title: "Submit Accomplishment?",
    text: "This will submit all your accomplishment entries for rating.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Submit",
  }).then((result) => {
    if (result.isConfirmed) {
      submit.post(route("employee-ipcr.submitRating", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Submitted!", "Accomplishments submitted for rating.", "success"),
        onError: () => Swal.fire("Error", "Failed to submit accomplishments.", "error")
      });
    }
  });
};

// ---------- UI Helpers ----------
const statusBadgeClass = ipcrStatusClass;

// ---------- Correct Summary Calculation with Sorting + Weights ----------
const summaryByFunctionType = computed(() => {
  const summary = {};

  (props.plans || []).forEach(plan => {
    const aoo = plan.performance_indicator?.agency_outcome;
    const rawFT = aoo?.function_type;
    const functionType = normalizeFunctionType(rawFT);

    if (!summary[functionType]) {
      summary[functionType] = {
        plansCount: 0,
        totalQ: 0, countQ: 0,
        totalE: 0, countE: 0,
        totalT: 0, countT: 0,
        totalA: 0, countA: 0,
        weight: functionTypeWeights[functionType] ?? 0
      };
    }

    const entry = summary[functionType];
    entry.plansCount++;

    const pivot = plan.pivot;
    if (!pivot) return;

    const useSup = hasSupervisorRating(pivot);

    const Q = useSup ? pivot.sup_quality : pivot.self_quality;
    const E = useSup ? pivot.sup_efficiency : pivot.self_efficiency;
    const T = useSup ? pivot.sup_timeliness : pivot.self_timeliness;

    if (Q !== null && Q !== "" && !isNaN(Q)) {
      entry.totalQ += Number(Q);
      entry.countQ++;
    }
    if (E !== null && E !== "" && !isNaN(E)) {
      entry.totalE += Number(E);
      entry.countE++;
    }
    if (T !== null && T !== "" && !isNaN(T)) {
      entry.totalT += Number(T);
      entry.countT++;
    }

    let planAvg = null;
    const avgFromPivot = useSup ? pivot.sup_average : pivot.self_average;

    if (avgFromPivot !== undefined && avgFromPivot !== null && avgFromPivot !== "" && !isNaN(avgFromPivot)) {
      planAvg = Number(avgFromPivot);
    } else {
      const computed = computeNumericAverage(Q, E, T);
      if (computed !== null) planAvg = computed;
    }

    if (planAvg !== null) {
      entry.totalA += Number(planAvg);
      entry.countA++;
    }
  });

  // -------- APPLY SORTING --------
  const sortedSummary = Object.keys(summary)
    .sort((a, b) => {
      return (functionTypeOrder[a] || 99) - (functionTypeOrder[b] || 99);
    })
    .reduce((obj, key) => {
      obj[key] = summary[key];
      return obj;
    }, {});

  return sortedSummary;
});

// ---------- COMPUTE OVERALL FINAL RATING ----------
const finalIPCRRating = computed(() => {
  let totalWeighted = 0;
  let totalWeight = 0;

  for (const [type, entry] of Object.entries(summaryByFunctionType.value)) {
    if (entry.countA === 0) continue;

    const avg = entry.totalA / entry.countA;
    const weight = entry.weight || 0;

    totalWeighted += avg * weight;
    totalWeight += weight;
  }

  // If totalWeight is 0 (no recognized function types with weight), avoid division by zero.
  // We follow CSC convention: totalWeighted already accounts for weight (weights are fractions),
  // so final rating is totalWeighted. If weights don't sum to 1, we still display totalWeighted
  // (consistent with earlier behavior). If you prefer to normalize by the sum of weights, change below.
  const finalVal = totalWeighted;

  // Normal rounding to 2 decimals (Option 1)
  return Number(finalVal).toFixed(2);
});

// Convert numeric rating to adjectival rating
const getAdjectivalRating = ipcrAdjectivalRating;

// ---------- Print & Export ----------
const printIPCR = () => window.print();

// ---------- Revision Workflow ----------
const showAddPlansModal = ref(false);
const selectedPlans = ref([]);
const planSearchQuery = ref("");

const filteredWorkPlans = computed(() => {
  const q = planSearchQuery.value.toLowerCase();
  const existingIds = new Set((props.plans || []).map(p => p.id));
  return props.workPlans.filter(p =>
    !existingIds.has(p.id) && (
      p.success_indicator?.toLowerCase().includes(q) ||
      p.performance_indicator?.description?.toLowerCase().includes(q)
    )
  );
});

const isPlanSelected = (id) => selectedPlans.value.includes(id);
const togglePlanSelection = (plan) => {
  const idx = selectedPlans.value.indexOf(plan.id);
  if (idx >= 0) selectedPlans.value.splice(idx, 1);
  else selectedPlans.value.push(plan.id);
};

const submitAddPlans = () => {
  submit.post(
    route("employee-ipcr.addPlans", props.ipcr.id),
    { plan_ids: selectedPlans.value },
    {
      onSuccess: () => {
        showAddPlansModal.value = false;
        selectedPlans.value = [];
        planSearchQuery.value = "";
        Swal.fire({ icon: "success", title: "Plans Added", timer: 1500, showConfirmButton: false });
      },
      onError: () => Swal.fire({ icon: "error", title: "Failed", text: "Could not add plans." }),
    }
  );
};

const removePlan = (plan) => {
  Swal.fire({
    title: "Remove Plan?",
    text: `Remove "${plan.success_indicator}" from this IPCR?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, remove it!",
  }).then((result) => {
    if (result.isConfirmed) {
      submit.delete(route("employee-ipcr.removePlan", [props.ipcr.id, plan.id]), {
        onSuccess: () => Swal.fire("Removed!", "Plan removed successfully.", "success"),
        onError: () => Swal.fire("Error", "Failed to remove plan.", "error"),
      });
    }
  });
};

const resubmit = () => {
  Swal.fire({
    title: "Resubmit for Review?",
    text: "This will resubmit your IPCR for the division chief's review.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Yes, resubmit!",
  }).then((result) => {
    if (result.isConfirmed) {
      submit.post(route("employee-ipcr.resubmit", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Resubmitted!", "IPCR resubmitted for review.", "success"),
        onError: () => Swal.fire("Error", "Failed to resubmit.", "error"),
      });
    }
  });
};

</script>

<template>
  <Head :title="`IPCR #${ipcr.id} Plans`" />
  <AdminLayout :title="`IPCR: ${ipcr.title}`">
    <div>
      <!-- Back Button -->
      <button @click="$inertia.get(route('employee-ipcr.index'))" class="mb-4 flex items-center gap-2 text-blue-600 hover:text-blue-800">
        <ArrowLeftIcon class="w-5 h-5" /> Back to IPCR List
      </button>
      
        
      
      <!-- IPCR Details -->
      <div class="bg-white p-4 rounded-lg shadow mb-4">
        <h2 class="text-2xl font-semibold">{{ ipcr.title }}</h2>
        <p class="text-gray-600">Rating Period: {{ ipcr.rating_period }}</p>
        <div class="flex items-center gap-2 mt-1">
          <span class="text-gray-600">Status:</span>
          <span :class="statusBadgeClass(ipcr.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
            {{ ipcr.status }}
          </span>
        </div>
        

        <div class="mt-4 flex justify-end gap-2">
          <button
            v-if="ipcr.status === 'New Target'"
            @click="submitForReview"
            :disabled="isSubmitting"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <!-- Optional icon for submit -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ isSubmitting ? 'Processing…' : 'Submit for Review and Approval' }}
          </button>

          <button
            v-if="ipcr.status === 'Targets Approved'"
            @click="submitForRating"
            :disabled="isSubmitting"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <!-- Optional icon for submit -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ isSubmitting ? 'Processing…' : 'Submit for Rating of the Accomplishment' }}
          </button>

          <button
            v-if="ipcr.status === 'Returned for Revision'"
            @click="showAddPlansModal = true"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow flex items-center gap-2"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Plans
          </button>

          <button
            v-if="ipcr.status === 'Returned for Revision'"
            @click="resubmit"
            :disabled="isSubmitting"
            class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg shadow flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ isSubmitting ? 'Processing…' : 'Resubmit for Review' }}
          </button>

          <button
            v-if="isAtPMTStage || ipcr.status === 'Targets Approved'"
            @click="printIPCR"
            class="bg-green-600 text-white hover:bg-green-700 px-4 py-2 rounded-lg shadow flex items-center gap-2"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9v6h12V9M6 9V5h12v4M6 15v4h12v-4M6 15H4v4h16v-4h-2" />
            </svg>
            Print IPCR
          </button>
        </div>

      </div>
      
      <!-- Plans Table -->
      <div class="bg-white p-4 rounded-lg shadow" id="ipcr-printable">
        <div v-if="isAtPMTStage || ipcr.status === 'Targets Approved'">
        <div class="mb-4">
        <p class="text-l text-center font-semibold mb-2">Individual Performance Commitment and Review (IPCR) <br/>
        FY {{ ratingYear }}
        </p>
        <br/><br/>
        <p>
          I, <b class="uppercase">{{ employee.name }}</b> , <b class="uppercase">{{employee.position}}</b> of Philippine Science High School - Caraga Region Campus, commit to deliver 
          and agree to be rated on the attainment of the following targets in accordance with 
          the indicated measures for the period <b class="uppercase">{{ ipcr.rating_period }}</b>.
        </p>
        </div><br/><br/>
        <div class="grid grid-cols-10 gap-4 mb-4 text-right">

        <!-- COLUMN 1: Empty / spacer -->
        <div class="col-span-1"></div>
        <div class="col-span-1"></div>
        <div class="col-span-2"></div>
        
        <!-- COLUMN 2: Employee Name / Position / Date (bigger) -->
        <div class="col-span-4 text-center">
          <p class="font-medium"><b style="text-transform: uppercase;">{{ employee.name }}</b></p>
          <small class="block">{{ employee.position }}</small>
          <small class="block">Date: {{ formattedSubmittedForReviewAt }}</small>
        </div>

        

        <!-- COLUMN 4: Rating Scale -->
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
                <br/><br/>
                <b style="text-transform: uppercase;">{{supervisor?.name ?? '—'}}</b><br/>
                <small>{{supervisor?.position ?? '' }}</small>
              </td>
              <td colspan="2" class="border px-3 py-3 text-center">
                <br/>
                {{ formattedTargetApprovedAt }}
              </td>
              <td colspan="4" class="border px-3 py-3 text-center">
                <br/><br/>
                <b>ENGR. RAMIL A. SANCHEZ</b><br/>
                <small>Director III</small>
              </td>
              <td colspan="2" class="border px-3 py-3 text-center">
                <br/>
                {{ formattedTargetApprovedAt }}
              </td>
            </tr>
            
        </table>

        </div>
        <table class="min-w-full border border-gray-300 text-sm border-collapse">
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

              <!-- FUNCTION TYPE HEADER -->
              <tr class="bg-gray-300">
                <td style="text-transform: uppercase;" colspan="10"
                    class="px-4 py-2 font-bold text-gray-800 border border-gray-300">
                  {{ functionType }}
                </td>
              </tr>

              <!-- OPTIONAL STRATEGIC FUNCTION HEADERS -->
              <tr v-if="functionType === 'Strategic Functions'">
                <td colspan="12" class="px-4 py-2 font-bold text-gray-800 border border-gray-300">
                  DOST POINT AGENDA
                </td>
              </tr>
              <tr v-if="functionType === 'Strategic Functions'">
                <td colspan="12" class="px-4 py-2 font-bold text-gray-800 border border-gray-300">
                  INCREASED IN COMPETITIVENESS OF FILIPINOS IN SCIENCE AND ENGINEERING
                </td>
              </tr>

              <!-- OUTCOMES -->
              <template v-for="(subGroups, outcome) in outcomes" :key="outcome">
                <tr class="bg-gray-200">
                  <td colspan="10"
                      class="px-4 py-2 font-semibold text-gray-700 border border-gray-300">
                    {{ outcome }}
                  </td>
                </tr>

                <!-- SUBOUTCOME -->
                <template v-for="(pis, subAbbrev) in subGroups" :key="subAbbrev">

                  <!-- PERFORMANCE INDICATOR GROUPING -->
                  <template v-for="(piPlans, piDesc) in pis" :key="piDesc">

                    <!-- FIRST ROW of each Performance Indicator group -->
                    <tr class="hover:bg-gray-50">

                      <!-- SubOutcome merged -->
                      <td v-if="Object.keys(pis)[0] === piDesc"
                          :rowspan="Object.values(pis).reduce((total, arr) => total + arr.length, 0)"
                          class="px-4 py-2 font-medium text-gray-700 border border-gray-300">
                        {{ subAbbrev }}
                      </td>

                      <!-- Performance Indicator merged -->
                      <td :rowspan="piPlans.length"
                          class="px-4 py-2 border border-gray-300 font-medium">
                        {{ piDesc }}
                      </td>

                      <!-- FIRST PLAN ROW -->
                      <td class="px-4 py-2 border border-gray-300">{{ piPlans[0].success_indicator }}</td>

                      <td class="px-4 py-2 border border-gray-300">
                        <p class="cursor-pointer text-blue-600 hover:underline text-sm"
                           @click="openModal(piPlans[0])">
                          {{ piPlans[0].pivot?.accomplishment || '—' }}
                        </p>
                        <button
                          v-if="piPlans[0].accomplishments_count > 0"
                          @click="openAccViewer(piPlans[0])"
                          class="mt-1 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full hover:bg-indigo-100">
                          📋 {{ piPlans[0].accomplishments_count }} accomplishment{{ piPlans[0].accomplishments_count > 1 ? 's' : '' }}
                        </button>
                        <span v-else class="block mt-1 text-xs text-gray-400 italic">No accomplishments</span>
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <template v-if="piPlans[0].pivot?.mov_link">
                          <a :href="piPlans[0].pivot.mov_link" target="_blank"
                            class="text-blue-600 hover:underline break-all">
                            {{ piPlans[0].pivot.mov_link }}
                          </a>
                        </template>
                        <span v-else>—</span>
                      </td>

                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="!showRatings">—</template>
                        <template v-else-if="isAtPMTStage">
                          {{ piPlans[0].pivot?.sup_quality ?? "—" }}
                        </template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_quality ?? "—" }}</div>
                          <div>DC: {{ piPlans[0].pivot?.sup_quality ?? "—" }}</div>
                        </template>
                      </td>

                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="!showRatings">—</template>
                        <template v-else-if="isAtPMTStage">
                          {{ piPlans[0].pivot?.sup_efficiency ?? "—" }}
                        </template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_efficiency ?? "—" }}</div>
                          <div>DC: {{ piPlans[0].pivot?.sup_efficiency ?? "—" }}</div>
                        </template>
                      </td>

                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="!showRatings">—</template>
                        <template v-else-if="isAtPMTStage">
                          {{ piPlans[0].pivot?.sup_timeliness ?? "—" }}
                        </template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_timeliness ?? "—" }}</div>
                          <div>DC: {{ piPlans[0].pivot?.sup_timeliness ?? "—" }}</div>
                        </template>
                      </td>

                      <td class="px-4 py-2 text-center font-medium border border-gray-300">
                        <template v-if="!showRatings">—</template>
                        <template v-else-if="isAtPMTStage">
                          {{ computeAverage(piPlans[0].pivot?.sup_quality, piPlans[0].pivot?.sup_efficiency, piPlans[0].pivot?.sup_timeliness) }}
                        </template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_average ?? computeAverage(piPlans[0].pivot?.self_quality, piPlans[0].pivot?.self_efficiency, piPlans[0].pivot?.self_timeliness) }}</div>
                          <div>DC: {{ piPlans[0].pivot?.sup_average ?? computeAverage(piPlans[0].pivot?.sup_quality, piPlans[0].pivot?.sup_efficiency, piPlans[0].pivot?.sup_timeliness) }}</div>
                        </template>
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <div class="flex items-start gap-2">
                          <span class="flex-1">{{ piPlans[0].pivot?.remarks || "—" }}</span>
                          <button
                            v-if="ipcr.status === 'Returned for Revision'"
                            @click="removePlan(piPlans[0])"
                            class="text-red-600 hover:text-red-800 text-xs shrink-0"
                            title="Remove plan"
                          >Remove</button>
                        </div>
                      </td>
                    </tr>

                    <!-- REMAINING PLAN ROWS under this Performance Indicator -->
                    <tr v-for="plan in piPlans.slice(1)"
                        :key="plan.id"
                        class="hover:bg-gray-50">

                      <td class="px-4 py-2 border border-gray-300">{{ plan.success_indicator }}</td>

                      <td class="px-4 py-2 border border-gray-300">
                        <p class="cursor-pointer text-blue-600 hover:underline text-sm"
                           @click="openModal(plan)">
                          {{ plan.pivot?.accomplishment || '—' }}
                        </p>
                        <button
                          v-if="plan.accomplishments_count > 0"
                          @click="openAccViewer(plan)"
                          class="mt-1 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full hover:bg-indigo-100">
                          📋 {{ plan.accomplishments_count }} accomplishment{{ plan.accomplishments_count > 1 ? 's' : '' }}
                        </button>
                        <span v-else class="block mt-1 text-xs text-gray-400 italic">No accomplishments</span>
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <template v-if="plan.pivot?.mov_link">
                          <a :href="plan.pivot.mov_link" target="_blank"
                            class="text-blue-600 hover:underline break-all">
                            {{ plan.pivot.mov_link }}
                          </a>
                        </template>
                        <span v-else>—</span>
                      </td>

                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="!showRatings">—</template>
                        <template v-else-if="isAtPMTStage">
                          {{ plan.pivot?.sup_quality ?? "—" }}
                        </template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_quality ?? "—" }}</div>
                          <div>DC: {{ plan.pivot?.sup_quality ?? "—" }}</div>
                        </template>
                      </td>

                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="!showRatings">—</template>
                        <template v-else-if="isAtPMTStage">
                          {{ plan.pivot?.sup_efficiency ?? "—" }}
                        </template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_efficiency ?? "—" }}</div>
                          <div>DC: {{ plan.pivot?.sup_efficiency ?? "—" }}</div>
                        </template>
                      </td>

                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="!showRatings">—</template>
                        <template v-else-if="isAtPMTStage">
                          {{ plan.pivot?.sup_timeliness ?? "—" }}
                        </template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_timeliness ?? "—" }}</div>
                          <div>DC: {{ plan.pivot?.sup_timeliness ?? "—" }}</div>
                        </template>
                      </td>

                      <td class="px-4 py-2 text-center font-medium border border-gray-300">
                        <template v-if="!showRatings">—</template>
                        <template v-else-if="isAtPMTStage">
                          {{ computeAverage(plan.pivot?.sup_quality, plan.pivot?.sup_efficiency, plan.pivot?.sup_timeliness) }}
                        </template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_average ?? computeAverage(plan.pivot?.self_quality, plan.pivot?.self_efficiency, plan.pivot?.self_timeliness) }}</div>
                          <div>DC: {{ plan.pivot?.sup_average ?? computeAverage(plan.pivot?.sup_quality, plan.pivot?.sup_efficiency, plan.pivot?.sup_timeliness) }}</div>
                        </template>
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <div class="flex items-start gap-2">
                          <span class="flex-1">{{ plan.pivot?.remarks || "—" }}</span>
                          <button
                            v-if="ipcr.status === 'Returned for Revision'"
                            @click="removePlan(plan)"
                            class="text-red-600 hover:text-red-800 text-xs shrink-0"
                            title="Remove plan"
                          >Remove</button>
                        </div>
                      </td>

                    </tr>

                  </template>
                </template>
              </template>
            </template>
          </tbody>

        </table>

        <!-- Summary Table -->
        <br/>
        <div v-if="isAtPMTStage || ipcr.status === 'Targets Approved'">
        <table class="min-w-full border text-sm border-collapse">
        <thead class="bg-gray-100 text-gray-700 text-sm uppercase">
          <tr>
            <th rowspan="2" class="border px-4 py-2 text-center w-40">Output</th>
            <th colspan="4" class="border px-4 py-2 text-center">Rating</th>
            <th rowspan="2" class="border px-4 py-2 text-center w-24">% <br/> Weight</th>
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
          <tr
            v-for="(row, type) in summaryByFunctionType"
            :key="type"
            class="font-medium"
          >
            <!-- Output Type -->
            <td class="border px-3 py-2">{{ type }}</td>

            <!-- Q/E/T/A averages -->
            <td class="border px-3 py-2 text-center">
              {{ row.countQ ? formatAvg(row.totalQ / row.countQ) : "—" }}
            </td>

            <td class="border px-3 py-2 text-center">
              {{ row.countE ? formatAvg(row.totalE / row.countE) : "—" }}
            </td>

            <td class="border px-3 py-2 text-center">
              {{ row.countT ? formatAvg(row.totalT / row.countT) : "—" }}
            </td>

            <td class="border px-3 py-2 text-center">
              {{ row.countA ? formatAvg(row.totalA / row.countA) : "—" }}
            </td>

            <!-- % Weight -->
            <td class="border px-3 py-2 text-center">
              {{ (row.weight * 100).toFixed(0) }}%
            </td>

            <!-- Weighted Score -->
            <td class="border px-3 py-2 text-center">
              {{

                row.countA
                  ? formatAvg((row.totalA / row.countA) * row.weight)
                  : "—"
              }}
            </td>
          </tr>
          <tr class="bg-gray-50 text-gray-800">
            <td colspan="6" class="border px-3 py-3 text-left">TOTAL</td>

            <td class="border px-3 py-3 text-center font-bold">
              {{ finalIPCRRating }}
            </td>
          </tr>
          <!-- FINAL RATING ROW -->
          <tr class="bg-gray-50 text-gray-800">
            <td colspan="6" class="border px-3 py-3 text-left">Adjectival Rating</td>
            <td class="border px-3 py-3 text-center font-bold">
              {{ getAdjectivalRating(finalIPCRRating) }}
            </td>
          </tr>

          <tr class="bg-gray-50  text-gray-800">
            <td colspan="12" class="border px-3 py-3 text-left">Comments and Reccommendations for Development Purposes: <i class="font-bold">{{ ipcr.remarks }}</i></td>

            
          </tr>
          
        </tbody>
      </table>
      <table class="min-w-full border text-sm border-collapse">
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
              <br/><br/>
              <b style="text-transform: uppercase;">{{ employee.name }}</b><br/>
              <small>{{ employee.position }}</small>
            </td>
            <td colspan="1" class="border px-3 py-3 text-center">
              <br/><br/>
              {{ formattedSubmittedForReviewAt }}
            </td>
            <td colspan="3" class="border px-3 py-3 text-center">
              <br/><br/>
              <b style="text-transform: uppercase;">{{ supervisor?.name ?? '—' }}</b><br/>
              <small>{{ supervisor?.position ?? '' }}</small>
            </td>
            <td colspan="1" class="border px-3 py-3 text-center">
              <br/><br/>
              {{ formattedSubmittedRatingAt }}
            </td>
            <td colspan="3" class="border px-3 py-3 text-center">
              <br/><br/>
              <b>ENGR. RAMIL A. SANCHEZ</b><br/>
              <small>Director III</small>
            </td>
            <td colspan="2" class="border px-3 py-3 text-center">
              <br/><br/>
              ____________________
            </td>
          </tr>
          <tr class="font-bold text-gray-800">
            <td colspan="12" class="border px-3 text-left"><small><i>Legend: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1 - Effectiveness/Quality &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 2 - Efficiency &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3 - Timeliness &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4 - Average</i></small></td>
          </tr>
      </table>

      </div>
      </div>

      <!-- Modal -->
      <div v-if="isModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-96">
          <h3 class="text-lg font-semibold mb-4">Update Accomplishment</h3>
          <div class="flex flex-col gap-3">
            <label>Accomplishment:
              <input type="text" v-model="form.accomplishment" :disabled="hasSupervisorRating(currentPlan?.pivot)" class="border rounded w-full px-2 py-1"/>
            </label>
            <label>MOVs Link:
              <input type="text" v-model="form.mov_link" :disabled="hasSupervisorRating(currentPlan?.pivot)" class="border rounded w-full px-2 py-1"/>
            </label>
            <label>Quality:
              <input type="number" min="0" max="100" v-model="form.quality" :disabled="hasSupervisorRating(currentPlan?.pivot)" class="border rounded w-full px-2 py-1"/>
            </label>
            <label>Efficiency:
              <input type="number" min="0" max="100" v-model="form.efficiency" :disabled="hasSupervisorRating(currentPlan?.pivot)" class="border rounded w-full px-2 py-1"/>
            </label>
            <label>Timeliness:
              <input type="number" min="0" max="100" v-model="form.timeliness" :disabled="hasSupervisorRating(currentPlan?.pivot)" class="border rounded w-full px-2 py-1"/>
            </label>
            <div class="text-sm text-gray-600">Average: <strong>{{ liveAverage }}</strong></div>
            <div v-if="hasSupervisorRating(currentPlan?.pivot)" class="text-sm text-red-600">
              Supervisor ratings are present for this plan. Self editing is disabled.
            </div>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button @click="closeModal" class="px-4 py-2 border rounded bg-gray-200 hover:bg-gray-300">Close</button>
            <button v-if="!hasSupervisorRating(currentPlan?.pivot)" @click="saveModal" :disabled="isSubmitting" class="px-4 py-2 border rounded bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">{{ isSubmitting ? 'Saving…' : 'Save' }}</button>
          </div>
        </div>
      </div>

      <!-- Add Plans Modal (visible when Returned for Revision) -->
      <div v-if="showAddPlansModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg">
          <h3 class="text-lg font-semibold mb-3">Add Plans</h3>
          <input
            v-model="planSearchQuery"
            type="text"
            placeholder="Search plans..."
            class="w-full border rounded px-3 py-2 mb-3 text-sm"
          />
          <div class="max-h-64 overflow-auto border rounded p-2 space-y-1">
            <div
              v-for="plan in filteredWorkPlans"
              :key="plan.id"
              class="flex items-start gap-2 py-1"
            >
              <input
                type="checkbox"
                :id="`add-plan-${plan.id}`"
                :checked="isPlanSelected(plan.id)"
                @change="togglePlanSelection(plan)"
                class="mt-1"
              />
              <label :for="`add-plan-${plan.id}`" class="flex-1 cursor-pointer text-sm">
                <div class="font-medium">{{ plan.success_indicator }}</div>
                <div class="text-gray-500">{{ plan.performance_indicator?.description }}</div>
              </label>
            </div>
            <div v-if="filteredWorkPlans.length === 0" class="text-sm text-gray-500 py-2 text-center">
              No additional plans available.
            </div>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button
              @click="showAddPlansModal = false; selectedPlans = []; planSearchQuery = ''"
              class="px-4 py-2 border rounded bg-gray-200 hover:bg-gray-300 text-sm"
            >Cancel</button>
            <button
              @click="submitAddPlans"
              :disabled="!selectedPlans.length || isSubmitting"
              class="px-4 py-2 border rounded bg-blue-600 text-white hover:bg-blue-700 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
            >{{ isSubmitting ? 'Adding…' : 'Add Selected' }}</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>

  <!-- ── Daily Accomplishments Viewer Modal ───────────────────────────── -->
  <div v-if="accViewerPlan"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
    @click.self="closeAccViewer">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-start justify-between px-6 py-4 border-b">
        <div>
          <h2 class="text-base font-semibold text-gray-800">Accomplishments</h2>
          <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ accViewerPlan.success_indicator }}</p>
        </div>
        <button @click="closeAccViewer" class="text-gray-400 hover:text-gray-700 text-xl leading-none ml-4">✕</button>
      </div>

      <!-- Body -->
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

      <!-- Footer -->
      <div class="px-6 py-3 border-t text-right">
        <span class="text-xs text-gray-400 mr-4">
          {{ accViewerPlan.accomplishments_count }} accomplishment(s) total
        </span>
        <button @click="closeAccViewer"
          class="px-4 py-2 bg-gray-200 rounded-lg text-sm hover:bg-gray-300">
          Close
        </button>
      </div>
    </div>
  </div>

</template>

<style>
@media print {
  @page {
    size: A4 landscape;
    margin: 10mm;
  }

  body {
    font-size: 8px !important;
     padding-top: 0 !important;
     padding-bottom: 0 !important;
     margin: 0 !important;
  }

  body * {
    visibility: hidden !important;
    font-size: 8px !important;
     padding-top: 0 !important;
     padding-bottom: 0 !important;
  }

  #ipcr-printable,
  #ipcr-printable * {
    visibility: visible !important;
    font-size: 8px !important;
    padding-top: 0 !important;
     padding-bottom: 0 !important;
  }

  #ipcr-printable {
    position: absolute;
    font-size: 8px !important;
    left: 0;
    top: 0;
    width: 100% !important;
  }

  /* --------------------------------------- */
  /*       KEEP HEADER/OTHER SECTIONS TOGETHER */
  /* --------------------------------------- */
  .keep-together {
    display: block !important;
    page-break-before: avoid !important;
    page-break-after: avoid !important;

    break-before: avoid !important;
    break-after: avoid !important;

    -webkit-region-break-before: avoid !important;
    -webkit-region-break-after: avoid !important;
  }

  /* --------------------------------------- */
  /*       TABLE STYLES                       */
  /* --------------------------------------- */
  table {
    width: 100% !important;
    border-collapse: collapse !important;
    /* Allow table rows to break across pages */
    page-break-inside: auto !important;
    font-size: 8px !important;
  }

  table, th, td {
    border: 1px solid #000 !important;
  }

  tr {
    page-break-inside: avoid; /* prevent single rows from breaking */
    page-break-after: auto;
  }

  .no-print {
    display: none !important;
  }
}
</style>
