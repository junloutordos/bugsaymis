<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { ArrowLeftIcon } from "@heroicons/vue/24/outline";
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass";
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating";

const props = defineProps({
  ipcr: Object,
  plans: Array,
  employee: Object,
  supervisor: Object,
  canManageIpcr: Boolean,
});

const divisionComments = ref(props.ipcr.remarks ?? "");
const isEditing = ref(!divisionComments.value);

const planRemarks = ref(
  Object.fromEntries((props.plans || []).map(p => [p.id, p.pivot?.remarks ?? ""]))
);

const savePlanRemark = (plan) => {
  router.put(
    route("division-chief-employee-ipcr-plan.remark", [props.ipcr.id, plan.id]),
    { remarks: planRemarks.value[plan.id] },
    {
      onSuccess: () => {
        plan.pivot.remarks = planRemarks.value[plan.id];
        Swal.fire({ icon: "success", title: "Saved", timer: 1200, showConfirmButton: false });
      },
      onError: () => Swal.fire({ icon: "error", title: "Error", text: "Failed to save remark." }),
    }
  );
};

const submitToPMT = () => {
  Swal.fire({
    title: "Submit to PMT?",
    text: "This will submit the rated IPCR to the Performance Management Team for review.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Yes, submit!",
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route("division-chief-employee-ipcr.submitToPMT", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Submitted!", "IPCR submitted to PMT for review.", "success"),
        onError: () => Swal.fire("Error", "Failed to submit to PMT.", "error"),
      });
    }
  });
};

const showReturnFromPMTModal = ref(false);
const returnFromPMTRemarks = ref("");

const confirmReturnFromPMT = () => {
  if (!returnFromPMTRemarks.value.trim()) {
    Swal.fire({ icon: "warning", title: "Remarks required", text: "Please provide remarks explaining why the IPCR is being returned." });
    return;
  }
  router.post(
    route("division-chief-employee-ipcr.returnFromPMT", props.ipcr.id),
    { remarks: returnFromPMTRemarks.value },
    {
      onSuccess: () => {
        showReturnFromPMTModal.value = false;
        returnFromPMTRemarks.value = "";
        Swal.fire("Returned!", "IPCR returned to employee for revision.", "success");
      },
      onError: () => Swal.fire("Error", "Failed to return IPCR.", "error"),
    }
  );
};

const returnAccomplishment = () => {
  Swal.fire({
    title: "Return Accomplishment for Revision?",
    text: "This will return the submitted accomplishment to the employee for revision.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, return it!",
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route("division-chief-employee-ipcr.returnAccomplishment", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Returned!", "Accomplishment returned to employee for revision.", "success"),
        onError: () => Swal.fire("Error", "Failed to return accomplishment.", "error"),
      });
    }
  });
};

const disapproveTargets = () => {
  Swal.fire({
    title: "Return for Revision?",
    text: "This will return the IPCR to the employee for revision.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, return it!",
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route("division-chief-employee-ipcr.disapprove", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Returned!", "IPCR returned for revision.", "success"),
        onError: () => Swal.fire("Error", "Failed to return IPCR.", "error"),
      });
    }
  });
};

const saveDivisionComments = () => {
  router.post(
    route("division-chief-employee-ipcr.savecomments", props.ipcr.id),
    { division_comments: divisionComments.value },
    {
      onSuccess: () => {
        Swal.fire({ icon: "success", title: "Saved", text: "Comments saved successfully." });
        isEditing.value = false;
      },
      onError: () => Swal.fire({ icon: "error", title: "Error", text: "Failed to save comments." }),
    }
  );
};

// ---------- Modal state ----------
const isModalOpen = ref(false);
const currentPlan = ref(null);
const form = ref({ accomplishment: "", mov_link: "", quality: null, efficiency: null, timeliness: null });

const canEdit = computed(() => props.ipcr.status === "Submitted for Rating")

// Determine whether the logged-in user can rate a specific plan
const canRatePlan = (plan) => {
  if (!canEdit.value) return false
  // Division Chief and OCD can rate any plan
  if (props.canManageIpcr) return true
  const ratedBy = plan.rated_by
  if (ratedBy === 'Unit Head') {
    return plan.offices?.some(o => o.unit_head == props.supervisor?.id) ?? false
  }
  if (ratedBy === 'Committee Head') {
    return plan.committees?.some(c => c.head_id == props.supervisor?.id) ?? false
  }
  if (ratedBy === 'Coordinator') {
    return plan.special_assignments?.some(a => a.coordinator_id == props.supervisor?.id) ?? false
  }
  return false
};

const computeAverage = (q, e, t) => {
  const values = [q, e, t].filter(v => v !== null && v !== "" && !isNaN(v)).map(Number);
  if (!values.length) return "—";
  return (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2);
};

const computeNumericAverage = (q, e, t) => {
  const values = [q, e, t].filter(v => v !== null && v !== "" && !isNaN(v)).map(Number);
  if (!values.length) return null;
  return values.reduce((a, b) => a + b, 0) / values.length;
};

const formatAvg = (num) => {
  if (num === null || num === undefined || isNaN(num)) return "—";
  return Number(num).toFixed(2);
};

const liveAverage = computed(() =>
  computeAverage(form.value.quality, form.value.efficiency, form.value.timeliness)
);

// ---------- Date helpers ----------
const extractYearFromRatingPeriod = (ratingPeriod) => {
  if (!ratingPeriod || typeof ratingPeriod !== "string") return "";
  const m = ratingPeriod.match(/(19|20)\d{2}/);
  return m ? m[0] : "";
};

const formatDateString = (value) => {
  if (!value) return "—";
  let d;
  if (value instanceof Date) {
    d = value;
  } else {
    let parsed = Date.parse(value);
    if (isNaN(parsed)) {
      const digits = String(value).match(/\d{9,}/);
      if (digits) parsed = parseInt(digits[0], 10);
    }
    if (isNaN(parsed)) return value || "—";
    d = new Date(parsed);
  }
  if (isNaN(d)) return "—";
  return d.toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" });
};

const ratingYear = computed(() => extractYearFromRatingPeriod(props.ipcr?.rating_period || ""));
const formattedSubmittedForReviewAt = computed(() => formatDateString(props.ipcr?.submitted_for_review_at));
const formattedTargetApprovedAt = computed(() => formatDateString(props.ipcr?.target_approved_at));
const formattedSubmittedRatingAt = computed(() => formatDateString(props.ipcr?.submitted_rating_at));

// ---------- Function type helpers ----------
const normalizeFunctionType = (raw) => {
  if (!raw) return "Uncategorized";
  const t = String(raw).trim().toLowerCase();
  if (t === "strategic" || t === "strategic functions" || t === "strategic function") return "Strategic Functions";
  if (t === "core" || t === "core functions" || t === "core function") return "Core Functions";
  if (t === "support" || t === "support functions" || t === "support function") return "Support Functions";
  return String(raw).trim();
};

const functionTypeWeights = { "Strategic Functions": 0.30, "Core Functions": 0.55, "Support Functions": 0.15, "Uncategorized": 0 };
const functionTypeOrder  = { "Strategic Functions": 1,    "Core Functions": 2,    "Support Functions": 3,    "Uncategorized": 4 };

// ---------- Stage flags ----------
const RATED_STAGES = ['Submitted for Rating', 'Rated & For PMT Review', 'Submitted to PMT', 'PMT Returned for Revision', 'Approved by PMT'];
const isAtRatedStage = computed(() => RATED_STAGES.includes(props.ipcr.status));

const PMT_STAGES = ['Rated & For PMT Review', 'Submitted to PMT', 'PMT Returned for Revision', 'Approved by PMT'];
const showOnlySupRatings = computed(() => PMT_STAGES.includes(props.ipcr.status));

// ---------- Grouped plans (function type → outcome → sub-outcome → PI description) ----------
const groupedPlansByFunction = computed(() => {
  const groups = {};

  (props.plans || []).forEach(plan => {
    const aoo = plan.performance_indicator?.agency_outcome;
    const functionType = normalizeFunctionType(aoo?.function_type);
    const outcome  = aoo?.outcome   || "Uncategorized";
    const subOutcome = aoo?.sub_outcome || "—";
    const subAbbrev = subOutcome !== "—" ? subOutcome.slice(0, 4) : subOutcome;
    const piDesc = plan.performance_indicator?.description || "—";

    if (!groups[functionType]) groups[functionType] = {};
    if (!groups[functionType][outcome]) groups[functionType][outcome] = {};
    if (!groups[functionType][outcome][subAbbrev]) groups[functionType][outcome][subAbbrev] = {};
    if (!groups[functionType][outcome][subAbbrev][piDesc]) groups[functionType][outcome][subAbbrev][piDesc] = [];
    groups[functionType][outcome][subAbbrev][piDesc].push(plan);
  });

  const sorted = {};
  Object.keys(functionTypeOrder).forEach(ft => { if (groups[ft]) sorted[ft] = groups[ft]; });
  Object.keys(groups).filter(ft => !functionTypeOrder[ft]).sort().forEach(ft => (sorted[ft] = groups[ft]));

  Object.keys(sorted).forEach(ft => {
    const sortedOutcomes = {};
    Object.keys(sorted[ft]).sort().forEach(outcome => {
      sortedOutcomes[outcome] = {};
      Object.keys(sorted[ft][outcome]).sort().forEach(sub => {
        const sortedPI = {};
        Object.keys(sorted[ft][outcome][sub]).sort().forEach(piDesc => {
          sortedPI[piDesc] = sorted[ft][outcome][sub][piDesc];
        });
        sortedOutcomes[outcome][sub] = sortedPI;
      });
    });
    sorted[ft] = sortedOutcomes;
  });

  return sorted;
});

// ---------- Summary by function type (supervisor ratings only) ----------
const summaryByFunctionType = computed(() => {
  const summary = {};

  (props.plans || []).forEach(plan => {
    const aoo = plan.performance_indicator?.agency_outcome;
    const functionType = normalizeFunctionType(aoo?.function_type);

    if (!summary[functionType]) {
      summary[functionType] = { plansCount: 0, totalQ: 0, countQ: 0, totalE: 0, countE: 0, totalT: 0, countT: 0, totalA: 0, countA: 0, weight: functionTypeWeights[functionType] ?? 0 };
    }

    const entry = summary[functionType];
    entry.plansCount++;
    const pivot = plan.pivot;
    if (!pivot) return;

    const Q = pivot.sup_quality;
    const E = pivot.sup_efficiency;
    const T = pivot.sup_timeliness;

    if (Q !== null && Q !== "" && !isNaN(Q)) { entry.totalQ += Number(Q); entry.countQ++; }
    if (E !== null && E !== "" && !isNaN(E)) { entry.totalE += Number(E); entry.countE++; }
    if (T !== null && T !== "" && !isNaN(T)) { entry.totalT += Number(T); entry.countT++; }

    let planAvg = null;
    if (pivot.sup_average !== undefined && pivot.sup_average !== null && pivot.sup_average !== "" && !isNaN(pivot.sup_average)) {
      planAvg = Number(pivot.sup_average);
    } else {
      const c = computeNumericAverage(Q, E, T);
      if (c !== null) planAvg = c;
    }
    if (planAvg !== null) { entry.totalA += Number(planAvg); entry.countA++; }
  });

  return Object.keys(summary)
    .sort((a, b) => (functionTypeOrder[a] || 99) - (functionTypeOrder[b] || 99))
    .reduce((obj, key) => { obj[key] = summary[key]; return obj; }, {});
});

const finalIPCRRating = computed(() => {
  let totalWeighted = 0;
  for (const [, entry] of Object.entries(summaryByFunctionType.value)) {
    if (entry.countA === 0) continue;
    totalWeighted += (entry.totalA / entry.countA) * (entry.weight || 0);
  }
  return Number(totalWeighted).toFixed(2);
});

const getAdjectivalRating = ipcrAdjectivalRating;

// ---------- Modal control ----------
const openModal = (plan) => {
  if (!canRatePlan(plan)) return;
  currentPlan.value = plan;
  form.value = {
    accomplishment: plan.pivot?.accomplishment || "",
    mov_link: plan.pivot?.mov_link || "",
    quality: plan.pivot?.sup_quality ?? plan.pivot?.self_quality ?? null,
    efficiency: plan.pivot?.sup_efficiency ?? plan.pivot?.self_efficiency ?? null,
    timeliness: plan.pivot?.sup_timeliness ?? plan.pivot?.self_timeliness ?? null,
  };
  isModalOpen.value = true;
};

const saveModal = async () => {
  if (!currentPlan.value) return;
  if (!form.value.accomplishment?.trim() || !form.value.mov_link?.trim()) {
    Swal.fire({ icon: "warning", title: "Missing Required Fields", text: "Please fill in BOTH the Accomplishment and MOV Link before saving.", confirmButtonColor: "#2563eb" });
    return;
  }
  router.put(
    route("division-chief-employee-ipcr-plan.rateIPCRPlan", [props.ipcr.id, currentPlan.value.id]),
    { accomplishment: form.value.accomplishment, mov_link: form.value.mov_link, sup_quality: form.value.quality, sup_efficiency: form.value.efficiency, sup_timeliness: form.value.timeliness },
    {
      onSuccess: () => {
        const avg = computeAverage(form.value.quality, form.value.efficiency, form.value.timeliness);
        currentPlan.value.pivot.accomplishment = form.value.accomplishment;
        currentPlan.value.pivot.mov_link       = form.value.mov_link;
        currentPlan.value.pivot.sup_quality    = form.value.quality;
        currentPlan.value.pivot.sup_efficiency = form.value.efficiency;
        currentPlan.value.pivot.sup_timeliness = form.value.timeliness;
        currentPlan.value.pivot.sup_average    = avg;
        isModalOpen.value = false;
        Swal.fire({ icon: "success", title: "Saved!", text: "Accomplishment and supervisor ratings saved successfully.", timer: 2000, showConfirmButton: false });
      },
      onError: () => Swal.fire({ icon: "error", title: "Error", text: "Failed to save. Please check your input." }),
    }
  );
};

// ---------- Status badge ----------
const statusBadgeClass = ipcrStatusClass;

const printIPCR = () => window.print();

// ---------- Submit actions ----------
const saveRatings = () => {
  Swal.fire({ title: "Save Ratings?", text: "Are you sure you want to save these ratings?", icon: "question", showCancelButton: true, confirmButtonText: "Yes, save it!" })
    .then(result => {
      if (result.isConfirmed) router.post(route("division-chief-employee-ipcr.saveratings", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Saved!", "Ratings for the rating period successfully saved!", "success"),
        onError:   () => Swal.fire("Error", "Failed to save ratings.", "error"),
      });
    });
};

const approveTargets = () => {
  Swal.fire({ title: "Approve Targets?", text: "Are you sure you want to approve these targets?", icon: "question", showCancelButton: true, confirmButtonText: "Yes, approve it!" })
    .then(result => {
      if (result.isConfirmed) router.post(route("division-chief-employee-ipcr.targetsapproval", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Approved!", "Targets for the rating period successfully approved!", "success"),
        onError:   () => Swal.fire("Error", "Failed to approve targets.", "error"),
      });
    });
};
</script>

<template>
  <Head :title="`IPCR #${ipcr.id} Plans`" />

  <AdminLayout :title="`IPCR: ${ipcr.title}`">
    <div>

      <!-- Back Button -->
      <button
        @click="$inertia.get(route('employee-ipcr.index'))"
        class="mb-4 flex items-center gap-2 text-blue-600 hover:text-blue-800"
      >
        <ArrowLeftIcon class="w-5 h-5" /> Back to IPCR List
      </button>

      <!-- IPCR Details Card -->
      <div class="bg-white p-4 rounded-lg shadow mb-4">
        <h2 class="text-2xl font-semibold">{{ ipcr.title }}</h2>
        <p class="text-gray-600">Rating Period: {{ ipcr.rating_period }}</p>
        <div class="flex items-center gap-2 mt-1">
          <span class="text-gray-600">Status:</span>
          <span :class="statusBadgeClass(ipcr.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
            {{ ipcr.status }}
          </span>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <button v-if="canManageIpcr && ipcr.status === 'Submitted for Rating'" @click="saveRatings"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow">
            Save Ratings
          </button>
          <button v-if="canManageIpcr && ipcr.status === 'For Review'" @click="approveTargets"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow">
            Approve Targets
          </button>
          <button v-if="canManageIpcr && ipcr.status === 'For Review'" @click="disapproveTargets"
            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg shadow">
            Return for Revision
          </button>
          <button v-if="canManageIpcr && ipcr.status === 'Submitted for Rating'" @click="returnAccomplishment"
            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg shadow">
            Return Accomplishment for Revision
          </button>
          <button v-if="canManageIpcr && ipcr.status === 'Rated & For PMT Review'" @click="submitToPMT"
            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg shadow">
            Submit to PMT
          </button>
          <button v-if="canManageIpcr && ipcr.status === 'PMT Returned for Revision'" @click="showReturnFromPMTModal = true"
            class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg shadow">
            Return to Employee
          </button>
          <button v-if="isAtRatedStage" @click="printIPCR"
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 border px-4 py-2 rounded-lg">
            Print / View PDF
          </button>
        </div>
      </div>

      <!-- Plans Section -->
      <div class="bg-white p-4 rounded-lg shadow" id="ipcr-printable">

        <!-- Header block (rated stages only) -->
        <div v-if="isAtRatedStage" class="mb-4">
          <p class="text-l text-center font-semibold mb-2">
            Individual Performance Commitment and Review (IPCR) <br/>
            FY {{ ratingYear }}
          </p>
          <br/><br/>
          <p>
            I, <b class="uppercase">{{ employee.name }}</b>, <b class="uppercase">{{ employee.position }}</b>
            of Philippine Science High School - Caraga Region Campus, commit to deliver
            and agree to be rated on the attainment of the following targets in accordance with
            the indicated measures for the period <b class="uppercase">{{ ipcr.rating_period }}</b>.
          </p>
          <br/><br/>
          <div class="grid grid-cols-10 gap-4 mb-4 text-right">
            <div class="col-span-4"></div>
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
                <br/><br/>
                <b style="text-transform: uppercase;">{{ supervisor.name }}</b><br/>
                <small>{{ supervisor.position }}</small>
              </td>
              <td colspan="2" class="border px-3 py-3 text-center">
                <br/>{{ formattedTargetApprovedAt }}
              </td>
              <td colspan="4" class="border px-3 py-3 text-center">
                <br/><br/>
                <b>ENGR. RAMIL A. SANCHEZ</b><br/>
                <small>Director III</small>
              </td>
              <td colspan="2" class="border px-3 py-3 text-center">
                <br/>{{ formattedTargetApprovedAt }}
              </td>
            </tr>
          </table>
        </div>

        <!-- Plans Table -->
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
              <th class="border px-4 py-2 text-center">Q</th>
              <th class="border px-4 py-2 text-center">E</th>
              <th class="border px-4 py-2 text-center">T</th>
              <th class="border px-4 py-2 text-center">A</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="!plans || plans.length === 0">
              <td colspan="10" class="text-center py-4 text-gray-500 border border-gray-300">
                No plans assigned to this IPCR.
              </td>
            </tr>

            <template v-for="(outcomes, functionType) in groupedPlansByFunction" :key="functionType">
              <!-- Function Type Header -->
              <tr class="bg-gray-300">
                <td colspan="10" class="px-4 py-2 font-bold text-gray-800 border border-gray-300"
                    style="text-transform: uppercase;">
                  {{ functionType }}
                </td>
              </tr>

              <template v-for="(subGroups, outcome) in outcomes" :key="outcome">
                <!-- Outcome Header -->
                <tr class="bg-gray-200">
                  <td colspan="10" class="px-4 py-2 font-semibold text-gray-700 border border-gray-300">
                    {{ outcome }}
                  </td>
                </tr>

                <template v-for="(pis, subAbbrev) in subGroups" :key="subAbbrev">
                  <template v-for="(piPlans, piDesc) in pis" :key="piDesc">

                    <!-- First row of each PI group -->
                    <tr class="hover:bg-gray-50">
                      <!-- Sub-outcome merged cell -->
                      <td v-if="Object.keys(pis)[0] === piDesc"
                          :rowspan="Object.values(pis).reduce((total, arr) => total + arr.length, 0)"
                          class="px-4 py-2 font-medium text-gray-700 border border-gray-300">
                        {{ subAbbrev !== '—' ? subAbbrev : '' }}
                      </td>

                      <!-- PI description merged cell -->
                      <td :rowspan="piPlans.length" class="px-4 py-2 border border-gray-300 font-medium">
                        {{ piDesc }}
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <div>{{ piPlans[0].success_indicator }}</div>
                        <div class="text-xs text-gray-400 mt-1">
                          Rater: {{ piPlans[0].rated_by || 'Division Chief' }}<template v-if="piPlans[0].offices?.length"> — {{ piPlans[0].offices.map(o => o.name).join(', ') }}</template><template v-if="piPlans[0].committees?.length"> — {{ piPlans[0].committees.map(c => c.name).join(', ') }}</template><template v-if="piPlans[0].special_assignments?.length"> — {{ piPlans[0].special_assignments.map(a => a.name).join(', ') }}</template>
                        </div>
                      </td>

                      <td class="px-4 py-2 border border-gray-300"
                          :class="canRatePlan(piPlans[0]) ? 'text-blue-600 cursor-pointer hover:underline' : 'text-gray-400 cursor-default'"
                          @click="canRatePlan(piPlans[0]) ? openModal(piPlans[0]) : null">
                        {{ piPlans[0].pivot?.accomplishment || '—' }}
                      </td>

                      <td class="px-4 py-2 border border-gray-300">
                        <a v-if="piPlans[0].pivot?.mov_link" :href="piPlans[0].pivot.mov_link" target="_blank"
                           class="text-blue-600 hover:underline break-all">
                          {{ piPlans[0].pivot.mov_link }}
                        </a>
                        <span v-else>—</span>
                      </td>

                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_quality ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_quality ?? "—" }}</div>
                          <div>DC: {{ piPlans[0].pivot?.sup_quality ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_efficiency ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_efficiency ?? "—" }}</div>
                          <div>DC: {{ piPlans[0].pivot?.sup_efficiency ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_timeliness ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_timeliness ?? "—" }}</div>
                          <div>DC: {{ piPlans[0].pivot?.sup_timeliness ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center font-medium border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_average ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ piPlans[0].pivot?.self_average ?? "—" }}</div>
                          <div>DC: {{ piPlans[0].pivot?.sup_average ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 border border-gray-300">
                        <template v-if="['For Review', 'Submitted for Rating'].includes(ipcr.status)">
                          <textarea v-model="planRemarks[piPlans[0].id]" rows="2"
                            class="w-full border rounded px-2 py-1 text-sm" placeholder="Add remark..."></textarea>
                          <button @click="savePlanRemark(piPlans[0])"
                            class="mt-1 text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">Save</button>
                        </template>
                        <span v-else>{{ piPlans[0].pivot?.remarks ?? "—" }}</span>
                      </td>
                    </tr>

                    <!-- Remaining rows in this PI group -->
                    <tr v-for="plan in piPlans.slice(1)" :key="plan.id" class="hover:bg-gray-50">
                      <td class="px-4 py-2 border border-gray-300">
                        <div>{{ plan.success_indicator }}</div>
                        <div class="text-xs text-gray-400 mt-1">
                          Rater: {{ plan.rated_by || 'Division Chief' }}<template v-if="plan.offices?.length"> — {{ plan.offices.map(o => o.name).join(', ') }}</template><template v-if="plan.committees?.length"> — {{ plan.committees.map(c => c.name).join(', ') }}</template><template v-if="plan.special_assignments?.length"> — {{ plan.special_assignments.map(a => a.name).join(', ') }}</template>
                        </div>
                      </td>
                      <td class="px-4 py-2 border border-gray-300"
                          :class="canRatePlan(plan) ? 'text-blue-600 cursor-pointer hover:underline' : 'text-gray-400 cursor-default'"
                          @click="canRatePlan(plan) ? openModal(plan) : null">
                        {{ plan.pivot?.accomplishment || '—' }}
                      </td>
                      <td class="px-4 py-2 border border-gray-300">
                        <a v-if="plan.pivot?.mov_link" :href="plan.pivot.mov_link" target="_blank"
                           class="text-blue-600 hover:underline break-all">{{ plan.pivot.mov_link }}</a>
                        <span v-else>—</span>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_quality ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_quality ?? "—" }}</div>
                          <div>DC: {{ plan.pivot?.sup_quality ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_efficiency ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_efficiency ?? "—" }}</div>
                          <div>DC: {{ plan.pivot?.sup_efficiency ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_timeliness ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_timeliness ?? "—" }}</div>
                          <div>DC: {{ plan.pivot?.sup_timeliness ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 text-center font-medium border border-gray-300">
                        <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_average ?? "—" }}</template>
                        <template v-else>
                          <div class="text-xs text-gray-400">Self: {{ plan.pivot?.self_average ?? "—" }}</div>
                          <div>DC: {{ plan.pivot?.sup_average ?? "—" }}</div>
                        </template>
                      </td>
                      <td class="px-4 py-2 border border-gray-300">
                        <template v-if="['For Review', 'Submitted for Rating'].includes(ipcr.status)">
                          <textarea v-model="planRemarks[plan.id]" rows="2"
                            class="w-full border rounded px-2 py-1 text-sm" placeholder="Add remark..."></textarea>
                          <button @click="savePlanRemark(plan)"
                            class="mt-1 text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">Save</button>
                        </template>
                        <span v-else>{{ plan.pivot?.remarks ?? "—" }}</span>
                      </td>
                    </tr>

                  </template>
                </template>
              </template>
            </template>
          </tbody>
        </table>

        <!-- Comments -->
        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Comments and Recommendations for Development Purposes:
          </label>
          <textarea
            v-model="divisionComments"
            :readonly="!isEditing && divisionComments"
            rows="2"
            class="w-full border rounded px-3 py-2 mb-2 bg-white"
            placeholder="Add comments and suggestions for improvement..."
          ></textarea>
          <div class="flex gap-2">
            <button
              v-if="divisionComments && !isEditing && ipcr.status === 'Submitted for Rating'"
              @click="isEditing = true"
              class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg shadow"
            >Edit</button>
            <button
              v-if="isEditing"
              @click="saveDivisionComments"
              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
            >Save Comments</button>
          </div>
        </div>

        <!-- Summary Table + Footer (rated stages) -->
        <div v-if="isAtRatedStage" class="mt-6">
          <table class="min-w-full border text-sm border-collapse">
            <thead class="bg-gray-100 text-gray-700 text-sm uppercase">
              <tr>
                <th rowspan="2" class="border px-4 py-2 text-center w-40">Output</th>
                <th colspan="4" class="border px-4 py-2 text-center">Rating</th>
                <th rowspan="2" class="border px-4 py-2 text-center w-24">% Weight</th>
                <th rowspan="2" class="border px-4 py-2 text-center w-40">Overall Weighted Score</th>
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
              <tr class="bg-gray-50 text-gray-800">
                <td colspan="6" class="border px-3 py-3 text-left font-semibold">TOTAL</td>
                <td class="border px-3 py-3 text-center font-bold">{{ finalIPCRRating }}</td>
              </tr>
              <tr class="bg-gray-50 text-gray-800">
                <td colspan="6" class="border px-3 py-3 text-left font-semibold">Adjectival Rating</td>
                <td class="border px-3 py-3 text-center font-bold">{{ getAdjectivalRating(finalIPCRRating) }}</td>
              </tr>
              <tr class="bg-gray-50 text-gray-800">
                <td colspan="7" class="border px-3 py-3 text-left">
                  Comments and Recommendations for Development Purposes:
                  <i class="font-bold">{{ ipcr.remarks }}</i>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Footer signature table -->
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
                <br/><br/>{{ formattedSubmittedForReviewAt }}
              </td>
              <td colspan="3" class="border px-3 py-3 text-center">
                <br/><br/>
                <b style="text-transform: uppercase;">{{ supervisor.name }}</b><br/>
                <small>{{ supervisor.position }}</small>
              </td>
              <td colspan="1" class="border px-3 py-3 text-center">
                <br/><br/>{{ formattedSubmittedRatingAt }}
              </td>
              <td colspan="3" class="border px-3 py-3 text-center">
                <br/><br/>
                <b>ENGR. RAMIL A. SANCHEZ</b><br/>
                <small>Director III</small>
              </td>
              <td colspan="2" class="border px-3 py-3 text-center">
                <br/><br/>____________________
              </td>
            </tr>
            <tr class="text-gray-800">
              <td colspan="12" class="border px-3 text-left">
                <small><i>Legend: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1 - Effectiveness/Quality &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 2 - Efficiency &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3 - Timeliness &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4 - Average</i></small>
              </td>
            </tr>
          </table>
        </div>

      </div>

      <!-- Rate Accomplishment Modal -->
      <div v-if="isModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-96">
          <h3 class="text-lg font-semibold mb-4">Rate Accomplishment</h3>
          <div class="flex flex-col gap-3">
            <label>Accomplishment:
              <textarea v-model="form.accomplishment" class="border rounded w-full px-2 py-1" rows="2"></textarea>
            </label>
            <label>MOV Link:
              <input type="text" v-model="form.mov_link" class="border rounded w-full px-2 py-1" />
            </label>
            <label>Quality:
              <input type="number" min="0" max="5" v-model="form.quality" class="border rounded w-full px-2 py-1" />
            </label>
            <label>Efficiency:
              <input type="number" min="0" max="5" v-model="form.efficiency" class="border rounded w-full px-2 py-1" />
            </label>
            <label>Timeliness:
              <input type="number" min="0" max="5" v-model="form.timeliness" class="border rounded w-full px-2 py-1" />
            </label>
            <div class="text-right font-medium mt-1">Average: {{ liveAverage }}</div>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button @click="isModalOpen = false" class="px-4 py-2 border rounded bg-gray-200 hover:bg-gray-300">Cancel</button>
            <button @click="saveModal" class="px-4 py-2 border rounded bg-blue-600 text-white hover:bg-blue-700">Save</button>
          </div>
        </div>
      </div>

    </div>

    <!-- Return to Employee (from PMT) Modal -->
    <div v-if="showReturnFromPMTModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-full max-w-lg">
        <h2 class="text-lg font-semibold mb-1">Return IPCR to Employee</h2>
        <p class="text-sm text-gray-500 mb-4">
          The PMT has requested a revision. Please provide remarks explaining what needs to be corrected.
          The employee will need to re-submit their accomplishments.
        </p>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Remarks / Justification <span class="text-red-500">*</span>
        </label>
        <textarea
          v-model="returnFromPMTRemarks"
          rows="4"
          class="w-full border rounded px-3 py-2 text-sm focus:ring-rose-500 focus:border-rose-500"
          placeholder="Explain why the IPCR is being returned for revision..."
        ></textarea>
        <div class="mt-4 flex justify-end gap-2">
          <button @click="showReturnFromPMTModal = false; returnFromPMTRemarks = ''"
            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
          <button @click="confirmReturnFromPMT"
            class="px-4 py-2 bg-rose-600 text-white rounded hover:bg-rose-700">Return to Employee</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<style>
@media print {
  @page { size: A4 landscape; margin: 10mm; }

  body * { visibility: hidden !important; }
  #ipcr-printable,
  #ipcr-printable * { visibility: visible !important; }

  #ipcr-printable {
    position: absolute;
    top: 0; left: 0;
    width: 100%;
    font-size: 8px;
    font-family: Arial, sans-serif;
    color: #000;
    background: white;
  }

  #ipcr-printable table { border-collapse: collapse; width: 100%; }
  #ipcr-printable th,
  #ipcr-printable td { border: 1px solid #000 !important; padding: 3px 6px; }
}
</style>
