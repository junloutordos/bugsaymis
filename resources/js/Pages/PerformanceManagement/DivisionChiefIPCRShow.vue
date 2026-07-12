<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppButton from "@/Components/AppButton.vue";
import AppCard from "@/Components/AppCard.vue";
import AppBadge from "@/Components/AppBadge.vue";
import AppModal from "@/Components/AppModal.vue";
import AppInput from "@/Components/AppInput.vue";
import AppTextarea from "@/Components/AppTextarea.vue";
import { ArrowLeftIcon, DocumentTextIcon } from "@heroicons/vue/24/outline";
import { ref, computed } from "vue";
import Swal from "sweetalert2";
import { useSubmit } from "@/Composables/useSubmit";
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating";

const props = defineProps({
  ipcr: Object,
  plans: Array,
  employee: Object,
  supervisor: Object,
  ocdUser: { type: Object, default: null },
  canManageIpcr: Boolean,
  canEndorse: Boolean,
  isMutable:     { type: Boolean, default: true },
  periodClosed:  { type: Boolean, default: false },
  hrDeadline:    { type: String,  default: null },
});

// Read-only banner: Director Signed (final) or rating period closed
const readOnlyReason = computed(() => {
  if (props.ipcr?.status === "Director Signed") return "This IPCR has been signed by the Director and is final.";
  if (props.periodClosed) return "The rating period for this IPCR is closed. This record is read-only.";
  return null;
});

// CSC SPMS: IPCR due to HR within 15 days after the rating period ends
const hrDeadlineLabel = computed(() => {
  if (!props.hrDeadline) return null;
  return new Date(props.hrDeadline).toLocaleDateString("en-PH", { year: "numeric", month: "long", day: "numeric" });
});
const hrDeadlinePast = computed(() => props.hrDeadline && new Date(props.hrDeadline) < new Date());

const divisionComments = ref(props.ipcr.remarks ?? "");
const isEditing = ref(!divisionComments.value);

const planRemarks = ref(
  Object.fromEntries((props.plans || []).map(p => [p.id, p.pivot?.remarks ?? ""]))
);

const { isSubmitting, submit } = useSubmit();

const savePlanRemark = (plan) => {
  submit.put(
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

const showReturnFromPMTModal = ref(false);
const returnFromPMTRemarks = ref("");

const confirmReturnFromPMT = () => {
  if (!returnFromPMTRemarks.value.trim()) {
    Swal.fire({ icon: "warning", title: "Remarks required", text: "Please provide remarks explaining why the IPCR is being returned." });
    return;
  }
  submit.post(
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
    confirmButtonColor: "#dc2626",
    confirmButtonText: "Yes, return it!",
  }).then((result) => {
    if (result.isConfirmed) {
      submit.post(route("division-chief-employee-ipcr.returnAccomplishment", props.ipcr.id), {}, {
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
    confirmButtonColor: "#dc2626",
    confirmButtonText: "Yes, return it!",
  }).then((result) => {
    if (result.isConfirmed) {
      submit.post(route("division-chief-employee-ipcr.disapprove", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Returned!", "IPCR returned for revision.", "success"),
        onError: () => Swal.fire("Error", "Failed to return IPCR.", "error"),
      });
    }
  });
};

const saveDivisionComments = () => {
  submit.post(
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

const canEdit = computed(() => props.isMutable && props.ipcr.status === "Submitted for Rating")

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

const ratingYear = computed(() =>
  props.ipcr?.period?.year ?? extractYearFromRatingPeriod(props.ipcr?.rating_period || "")
);
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
  submit.put(
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
      onError: (err) => Swal.fire({
        icon: "error",
        title: "Could not save",
        text: Object.values(err ?? {}).flat().join("\n") || "Failed to save. Please check your input.",
      }),
    }
  );
};

// ---------- Accomplishments viewer ----------
const accViewerPlan = ref(null);

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

// ---------- Status badge color mapping (AppBadge) ----------
const ipcrBadgeColor = (status) => {
  const map = {
    "New Target":                "blue",
    "For Review":                 "amber",
    "Targets Approved":           "green",
    "Submitted for Rating":       "orange",
    "Rated & For PMT Review":     "purple",
    "Submitted to PMT":           "purple",
    "PMT Returned for Revision":  "red",
    "Submitted to HR":            "blue",
    "Approved by PMT":            "green",
    "Director Signed":            "green",
    "Returned for Revision":      "red",
    "Rejected":                   "red",
  };
  return map[status] ?? "slate";
};

const printIPCR = () => window.print();

// ---------- Rating completeness (multi-rater awareness for CID) ----------
const allPlansRated = computed(() =>
  (props.plans ?? []).every(p => p.pivot?.sup_average !== null && p.pivot?.sup_average !== undefined)
)
const unratedPlans = computed(() =>
  (props.plans ?? []).filter(p => p.pivot?.sup_average === null || p.pivot?.sup_average === undefined)
)
const ratingStatusChip = (plan) => {
  const rated = plan.pivot?.sup_average !== null && plan.pivot?.sup_average !== undefined
  return {
    rated,
    label: rated ? 'Rated ✓' : `Awaiting ${plan.rated_by || 'Division Chief'}`,
    cls:   rated ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700',
  }
}

// ---------- Submit actions ----------
const saveRatings = () => {
  const text = allPlansRated.value
    ? "Are you sure you want to save these ratings?"
    : `${unratedPlans.value.length} plan(s) still have no supervisor rating. Save anyway?`
  Swal.fire({ title: "Save Ratings?", text, icon: "question", showCancelButton: true, confirmButtonText: "Yes, save it!" })
    .then(result => {
      if (result.isConfirmed) submit.post(route("division-chief-employee-ipcr.saveratings", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Saved!", "Ratings for the rating period successfully saved!", "success"),
        onError:   () => Swal.fire("Error", "Failed to save ratings.", "error"),
      });
    });
};

const approveTargets = () => {
  Swal.fire({ title: "Approve Targets?", text: "Are you sure you want to approve these targets?", icon: "question", showCancelButton: true, confirmButtonText: "Yes, approve it!" })
    .then(result => {
      if (result.isConfirmed) submit.post(route("division-chief-employee-ipcr.targetsapproval", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Approved!", "Targets for the rating period successfully approved!", "success"),
        onError:   () => Swal.fire("Error", "Failed to approve targets.", "error"),
      });
    });
};
</script>

<template>
  <Head :title="`IPCR #${ipcr.id} Plans`" />

  <AdminLayout :title="`IPCR: ${ipcr.title}`">
    <div class="p-6 space-y-5">

      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
          <AppButton as="link" variant="ghost" size="sm" :href="route('employee-ipcr.index')">
            <ArrowLeftIcon class="w-4 h-4" /> Back to IPCR List
          </AppButton>
        </div>
      </div>

      <!-- IPCR Details Card -->
      <AppCard :padded="false">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h1 class="text-xl font-semibold text-slate-800">{{ ipcr.title }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">Rating Period: {{ ipcr.rating_period }}</p>
          </div>
          <div class="flex items-center gap-2">
            <AppBadge :color="ipcrBadgeColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
          </div>
        </div>
        <div v-if="readOnlyReason" class="px-5 pt-4">
          <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 no-print">
            {{ readOnlyReason }}
          </div>
        </div>
        <div
          v-else-if="hrDeadlineLabel && !['Submitted to HR', 'Submitted to PMT', 'Approved by PMT', 'Director Signed'].includes(ipcr.status)"
          class="px-5 pt-4"
        >
          <div :class="['rounded-lg border px-4 py-3 text-sm no-print', hrDeadlinePast ? 'border-danger-200 bg-danger-50 text-danger-700' : 'border-indigo-100 bg-indigo-50 text-indigo-700']">
            Due to HR by <strong>{{ hrDeadlineLabel }}</strong> (CSC SPMS: 15 days after the rating period ends).
            <span v-if="hrDeadlinePast" class="font-semibold">This deadline has passed.</span>
          </div>
        </div>
        <div class="p-5 flex flex-wrap gap-2">
          <template v-if="isMutable && canManageIpcr && ipcr.status === 'Submitted for Rating'">
            <AppButton :loading="isSubmitting" :disabled="isSubmitting" @click="saveRatings">
              {{ isSubmitting ? 'Processing…' : 'Save Ratings' }}
            </AppButton>
            <span v-if="!allPlansRated"
              class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-xs font-medium bg-warning-50 text-warning-700 border border-warning-100">
              ⚠ {{ unratedPlans.length }} plan(s) still awaiting rating
            </span>
          </template>
          <AppButton v-if="isMutable && canManageIpcr && ipcr.status === 'For Review'" :loading="isSubmitting" :disabled="isSubmitting" @click="approveTargets">
            {{ isSubmitting ? 'Processing…' : 'Approve Targets' }}
          </AppButton>
          <AppButton v-if="isMutable && canManageIpcr && ipcr.status === 'For Review'" variant="danger" :loading="isSubmitting" :disabled="isSubmitting" @click="disapproveTargets">
            {{ isSubmitting ? 'Processing…' : 'Return for Revision' }}
          </AppButton>
          <AppButton v-if="isMutable && canManageIpcr && ipcr.status === 'Submitted for Rating'" variant="danger" :loading="isSubmitting" :disabled="isSubmitting" @click="returnAccomplishment">
            {{ isSubmitting ? 'Processing…' : 'Return Accomplishment for Revision' }}
          </AppButton>
          <span v-if="(canManageIpcr || canEndorse) && ipcr.status === 'Rated & For PMT Review'"
            class="inline-flex items-center text-sm text-cyan-700 bg-cyan-50 border border-cyan-200 px-3 py-2 rounded-lg">
            Rated — {{ canEndorse ? 'use the' : 'the Division Chief will use the' }} <strong class="mx-1">Division index page</strong> to batch-submit to HR.
          </span>
          <AppButton v-if="isMutable && canEndorse && ipcr.status === 'PMT Returned for Revision'" variant="danger" @click="showReturnFromPMTModal = true">
            Return to Employee
          </AppButton>
          <AppButton v-if="isAtRatedStage" variant="secondary" @click="printIPCR">
            Print / View PDF
          </AppButton>
        </div>
      </AppCard>

      <!-- Plans Section -->
      <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70" id="ipcr-printable">

        <!-- Header block (rated stages only) -->
        <div v-if="isAtRatedStage" class="p-5 border-b border-slate-100">
          <p class="text-base text-center font-semibold text-slate-800 mb-4">
            Individual Performance Commitment and Review (IPCR)<br/>
            FY {{ ratingYear }}
          </p>
          <p class="text-sm text-slate-700 mb-4">
            I, <b class="uppercase">{{ employee.name }}</b>, <b class="uppercase">{{ employee.position }}</b>
            of Philippine Science High School - Caraga Region Campus, commit to deliver
            and agree to be rated on the attainment of the following targets in accordance with
            the indicated measures for the period <b class="uppercase">{{ ipcr.rating_period }}</b>.
          </p>
          <div class="grid grid-cols-10 gap-4 mb-4 text-right">
            <div class="col-span-4"></div>
            <div class="col-span-4 text-center">
              <p class="font-semibold text-slate-800 uppercase">{{ employee.name }}</p>
              <small class="block text-slate-500">{{ employee.position }}</small>
              <small class="block text-slate-500">Date: {{ formattedSubmittedForReviewAt }}</small>
            </div>
            <div class="col-span-2 text-left">
              <small class="block text-slate-600">5 - Outstanding</small>
              <small class="block text-slate-600">4 - Very Satisfactory</small>
              <small class="block text-slate-600">3 - Satisfactory</small>
              <small class="block text-slate-600">2 - Unsatisfactory</small>
              <small class="block text-slate-600">1 - Poor</small>
            </div>
          </div>
          <table class="min-w-full border text-sm border-collapse">
            <tr class="font-semibold text-slate-700">
              <td colspan="4" class="border px-3 text-left">Reviewed by:</td>
              <td colspan="2" class="border px-3 text-left">Date:</td>
              <td colspan="4" class="border px-3 text-left">Approved by:</td>
              <td colspan="2" class="border px-3 text-left">Date:</td>
            </tr>
            <tr>
              <td colspan="4" class="border px-3 py-3 text-center">
                <br/><br/>
                <b class="uppercase">{{ supervisor.name }}</b><br/>
                <small class="text-slate-500">{{ supervisor.position }}</small>
              </td>
              <td colspan="2" class="border px-3 py-3 text-center text-slate-700">
                <br/>{{ formattedTargetApprovedAt }}
              </td>
              <td colspan="4" class="border px-3 py-3 text-center">
                <br/><br/>
                <b>{{ ocdUser?.name?.toUpperCase() ?? '—' }}</b><br/>
                <small class="text-slate-500">{{ ocdUser?.position ?? '' }}</small>
              </td>
              <td colspan="2" class="border px-3 py-3 text-center text-slate-700">
                <br/>{{ formattedTargetApprovedAt }}
              </td>
            </tr>
          </table>
        </div>

        <!-- Plans Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm border-collapse border border-slate-200">
            <thead class="bg-slate-50/80">
              <tr>
                <th rowspan="2" colspan="2" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">Output</th>
                <th rowspan="2" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">Success Indicators</th>
                <th rowspan="2" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">Actual Accomplishment</th>
                <th rowspan="2" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">Means of Verification</th>
                <th colspan="4" class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">Rating</th>
                <th rowspan="2" class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">Remarks</th>
              </tr>
              <tr>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">Q</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">E</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">T</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border border-slate-200">A</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="!plans || plans.length === 0">
                <td colspan="10" class="py-16 text-center text-slate-400 text-sm border border-slate-200">
                  No plans assigned to this IPCR.
                </td>
              </tr>

              <template v-for="(outcomes, functionType) in groupedPlansByFunction" :key="functionType">
                <!-- Function Type Header -->
                <tr class="bg-slate-200">
                  <td colspan="10" class="px-4 py-2 font-bold text-slate-800 border border-slate-200 uppercase">
                    {{ functionType }}
                  </td>
                </tr>

                <template v-for="(subGroups, outcome) in outcomes" :key="outcome">
                  <!-- Outcome Header -->
                  <tr class="bg-slate-100">
                    <td colspan="10" class="px-4 py-2 font-semibold text-slate-700 border border-slate-200">
                      {{ outcome }}
                    </td>
                  </tr>

                  <template v-for="(pis, subAbbrev) in subGroups" :key="subAbbrev">
                    <template v-for="(piPlans, piDesc) in pis" :key="piDesc">

                      <!-- First row of each PI group -->
                      <tr class="hover:bg-indigo-50/40">
                        <td v-if="Object.keys(pis)[0] === piDesc"
                            :rowspan="Object.values(pis).reduce((total, arr) => total + arr.length, 0)"
                            class="px-4 py-3 font-medium text-slate-700 border border-slate-200 text-sm">
                          {{ subAbbrev !== '—' ? subAbbrev : '' }}
                        </td>

                        <td :rowspan="piPlans.length" class="px-4 py-3 border border-slate-200 font-medium text-slate-700 text-sm">
                          {{ piDesc }}
                        </td>

                        <td class="px-4 py-3 border border-slate-200 text-sm text-slate-700">
                          <div>{{ piPlans[0].success_indicator }}</div>
                          <p v-if="piPlans[0].pivot?.individual_target" class="mt-1 text-xs text-slate-500 whitespace-pre-line">{{ piPlans[0].pivot.individual_target }}</p>
                          <div class="text-xs text-slate-400 mt-1 flex items-center gap-2 flex-wrap">
                            <span>Rater: {{ piPlans[0].rated_by || 'Division Chief' }}<template v-if="piPlans[0].offices?.length"> — {{ piPlans[0].offices.map(o => o.name).join(', ') }}</template><template v-if="piPlans[0].committees?.length"> — {{ piPlans[0].committees.map(c => c.name).join(', ') }}</template><template v-if="piPlans[0].special_assignments?.length"> — {{ piPlans[0].special_assignments.map(a => a.name).join(', ') }}</template></span>
                            <AppBadge :color="ratingStatusChip(piPlans[0]).rated ? 'green' : 'amber'">
                              {{ ratingStatusChip(piPlans[0]).label }}
                            </AppBadge>
                          </div>
                        </td>

                        <td class="px-4 py-3 border border-slate-200 text-sm text-slate-700">
                          <p :class="canRatePlan(piPlans[0]) ? 'text-indigo-600 cursor-pointer hover:underline' : 'text-slate-400 cursor-default'"
                             @click="canRatePlan(piPlans[0]) ? openModal(piPlans[0]) : null">
                            {{ piPlans[0].pivot?.accomplishment || '—' }}
                          </p>
                          <button
                            v-if="piPlans[0].accomplishments_count > 0"
                            type="button"
                            @click="openAccViewer(piPlans[0])"
                            class="mt-1 inline-flex items-center gap-1 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full hover:bg-indigo-100">
                            <DocumentTextIcon class="w-3 h-3" />
                            {{ piPlans[0].accomplishments_count }} accomplishment{{ piPlans[0].accomplishments_count > 1 ? 's' : '' }}
                          </button>
                        </td>

                        <td class="px-4 py-3 border border-slate-200 text-sm text-slate-700">
                          <a v-if="piPlans[0].pivot?.mov_link" :href="piPlans[0].pivot.mov_link" target="_blank"
                             class="text-indigo-600 hover:underline break-all text-xs">
                            {{ piPlans[0].pivot.mov_link }}
                          </a>
                          <span v-else class="text-slate-400">—</span>
                        </td>

                        <td class="px-4 py-3 text-center border border-slate-200 text-sm text-slate-700">
                          <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_quality ?? "—" }}</template>
                          <template v-else>
                            <div class="text-xs text-slate-400">Self: {{ piPlans[0].pivot?.self_quality ?? "—" }}</div>
                            <div>DC: {{ piPlans[0].pivot?.sup_quality ?? "—" }}</div>
                          </template>
                        </td>
                        <td class="px-4 py-3 text-center border border-slate-200 text-sm text-slate-700">
                          <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_efficiency ?? "—" }}</template>
                          <template v-else>
                            <div class="text-xs text-slate-400">Self: {{ piPlans[0].pivot?.self_efficiency ?? "—" }}</div>
                            <div>DC: {{ piPlans[0].pivot?.sup_efficiency ?? "—" }}</div>
                          </template>
                        </td>
                        <td class="px-4 py-3 text-center border border-slate-200 text-sm text-slate-700">
                          <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_timeliness ?? "—" }}</template>
                          <template v-else>
                            <div class="text-xs text-slate-400">Self: {{ piPlans[0].pivot?.self_timeliness ?? "—" }}</div>
                            <div>DC: {{ piPlans[0].pivot?.sup_timeliness ?? "—" }}</div>
                          </template>
                        </td>
                        <td class="px-4 py-3 text-center font-medium border border-slate-200 text-sm text-slate-700">
                          <template v-if="showOnlySupRatings">{{ piPlans[0].pivot?.sup_average ?? "—" }}</template>
                          <template v-else>
                            <div class="text-xs text-slate-400">Self: {{ piPlans[0].pivot?.self_average ?? "—" }}</div>
                            <div>DC: {{ piPlans[0].pivot?.sup_average ?? "—" }}</div>
                          </template>
                        </td>
                        <td class="px-4 py-3 border border-slate-200 text-sm text-slate-700">
                          <template v-if="['For Review', 'Submitted for Rating'].includes(ipcr.status)">
                            <AppTextarea v-model="planRemarks[piPlans[0].id]" :rows="2" placeholder="Add remark..." />
                            <AppButton size="sm" class="mt-1" :disabled="isSubmitting" @click="savePlanRemark(piPlans[0])">Save</AppButton>
                          </template>
                          <span v-else class="text-slate-600">{{ piPlans[0].pivot?.remarks ?? "—" }}</span>
                        </td>
                      </tr>

                      <!-- Remaining rows in this PI group -->
                      <tr v-for="plan in piPlans.slice(1)" :key="plan.id" class="hover:bg-indigo-50/40">
                        <td class="px-4 py-3 border border-slate-200 text-sm text-slate-700">
                          <div>{{ plan.success_indicator }}</div>
                          <p v-if="plan.pivot?.individual_target" class="mt-1 text-xs text-slate-500 whitespace-pre-line">{{ plan.pivot.individual_target }}</p>
                          <div class="text-xs text-slate-400 mt-1">
                            Rater: {{ plan.rated_by || 'Division Chief' }}<template v-if="plan.offices?.length"> — {{ plan.offices.map(o => o.name).join(', ') }}</template><template v-if="plan.committees?.length"> — {{ plan.committees.map(c => c.name).join(', ') }}</template><template v-if="plan.special_assignments?.length"> — {{ plan.special_assignments.map(a => a.name).join(', ') }}</template>
                          </div>
                        </td>
                        <td class="px-4 py-3 border border-slate-200 text-sm text-slate-700">
                          <p :class="canRatePlan(plan) ? 'text-indigo-600 cursor-pointer hover:underline' : 'text-slate-400 cursor-default'"
                             @click="canRatePlan(plan) ? openModal(plan) : null">
                            {{ plan.pivot?.accomplishment || '—' }}
                          </p>
                          <button
                            v-if="plan.accomplishments_count > 0"
                            type="button"
                            @click="openAccViewer(plan)"
                            class="mt-1 inline-flex items-center gap-1 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full hover:bg-indigo-100">
                            <DocumentTextIcon class="w-3 h-3" />
                            {{ plan.accomplishments_count }} accomplishment{{ plan.accomplishments_count > 1 ? 's' : '' }}
                          </button>
                        </td>
                        <td class="px-4 py-3 border border-slate-200 text-sm">
                          <a v-if="plan.pivot?.mov_link" :href="plan.pivot.mov_link" target="_blank"
                             class="text-indigo-600 hover:underline break-all text-xs">{{ plan.pivot.mov_link }}</a>
                          <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-center border border-slate-200 text-sm text-slate-700">
                          <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_quality ?? "—" }}</template>
                          <template v-else>
                            <div class="text-xs text-slate-400">Self: {{ plan.pivot?.self_quality ?? "—" }}</div>
                            <div>DC: {{ plan.pivot?.sup_quality ?? "—" }}</div>
                          </template>
                        </td>
                        <td class="px-4 py-3 text-center border border-slate-200 text-sm text-slate-700">
                          <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_efficiency ?? "—" }}</template>
                          <template v-else>
                            <div class="text-xs text-slate-400">Self: {{ plan.pivot?.self_efficiency ?? "—" }}</div>
                            <div>DC: {{ plan.pivot?.sup_efficiency ?? "—" }}</div>
                          </template>
                        </td>
                        <td class="px-4 py-3 text-center border border-slate-200 text-sm text-slate-700">
                          <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_timeliness ?? "—" }}</template>
                          <template v-else>
                            <div class="text-xs text-slate-400">Self: {{ plan.pivot?.self_timeliness ?? "—" }}</div>
                            <div>DC: {{ plan.pivot?.sup_timeliness ?? "—" }}</div>
                          </template>
                        </td>
                        <td class="px-4 py-3 text-center font-medium border border-slate-200 text-sm text-slate-700">
                          <template v-if="showOnlySupRatings">{{ plan.pivot?.sup_average ?? "—" }}</template>
                          <template v-else>
                            <div class="text-xs text-slate-400">Self: {{ plan.pivot?.self_average ?? "—" }}</div>
                            <div>DC: {{ plan.pivot?.sup_average ?? "—" }}</div>
                          </template>
                        </td>
                        <td class="px-4 py-3 border border-slate-200 text-sm text-slate-700">
                          <template v-if="['For Review', 'Submitted for Rating'].includes(ipcr.status)">
                            <AppTextarea v-model="planRemarks[plan.id]" :rows="2" placeholder="Add remark..." />
                            <AppButton size="sm" class="mt-1" :disabled="isSubmitting" @click="savePlanRemark(plan)">Save</AppButton>
                          </template>
                          <span v-else class="text-slate-600">{{ plan.pivot?.remarks ?? "—" }}</span>
                        </td>
                      </tr>

                    </template>
                  </template>
                </template>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Comments -->
        <div class="p-5 border-t border-slate-100">
          <AppTextarea
            v-model="divisionComments"
            label="Comments and Recommendations for Development Purposes:"
            :readonly="!isEditing && divisionComments"
            :rows="2"
            class="mb-2"
            placeholder="Add comments and suggestions for improvement..."
          />
          <div class="flex gap-2">
            <AppButton
              v-if="divisionComments && !isEditing && ipcr.status === 'Submitted for Rating'"
              variant="secondary"
              @click="isEditing = true">
              Edit
            </AppButton>
            <AppButton
              v-if="isEditing"
              :loading="isSubmitting"
              :disabled="isSubmitting"
              @click="saveDivisionComments">
              {{ isSubmitting ? 'Saving…' : 'Save Comments' }}
            </AppButton>
          </div>
        </div>

        <!-- Summary Table + Footer (rated stages) -->
        <div v-if="isAtRatedStage" class="p-5 border-t border-slate-100 space-y-4">
          <div class="overflow-x-auto">
            <table class="min-w-full border text-sm border-collapse">
              <thead class="bg-slate-50/80">
                <tr>
                  <th rowspan="2" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-40">Output</th>
                  <th colspan="4" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Rating</th>
                  <th rowspan="2" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-24">% Weight</th>
                  <th rowspan="2" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-40">Overall Weighted Score</th>
                </tr>
                <tr>
                  <th class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-16">Q</th>
                  <th class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-16">E</th>
                  <th class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-16">T</th>
                  <th class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-16">A</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(row, type) in summaryByFunctionType" :key="type" class="font-medium hover:bg-indigo-50/40">
                  <td class="border border-slate-200 px-3 py-2 text-sm text-slate-700">{{ type }}</td>
                  <td class="border border-slate-200 px-3 py-2 text-center text-sm text-slate-700">{{ row.countQ ? formatAvg(row.totalQ / row.countQ) : "—" }}</td>
                  <td class="border border-slate-200 px-3 py-2 text-center text-sm text-slate-700">{{ row.countE ? formatAvg(row.totalE / row.countE) : "—" }}</td>
                  <td class="border border-slate-200 px-3 py-2 text-center text-sm text-slate-700">{{ row.countT ? formatAvg(row.totalT / row.countT) : "—" }}</td>
                  <td class="border border-slate-200 px-3 py-2 text-center text-sm text-slate-700">{{ row.countA ? formatAvg(row.totalA / row.countA) : "—" }}</td>
                  <td class="border border-slate-200 px-3 py-2 text-center text-sm text-slate-700">{{ (row.weight * 100).toFixed(0) }}%</td>
                  <td class="border border-slate-200 px-3 py-2 text-center text-sm text-slate-700">{{ row.countA ? formatAvg((row.totalA / row.countA) * row.weight) : "—" }}</td>
                </tr>
                <tr class="bg-slate-50">
                  <td colspan="6" class="border border-slate-200 px-3 py-3 text-left text-sm font-semibold text-slate-700">TOTAL</td>
                  <td class="border border-slate-200 px-3 py-3 text-center font-bold text-slate-800">{{ finalIPCRRating }}</td>
                </tr>
                <tr class="bg-slate-50">
                  <td colspan="6" class="border border-slate-200 px-3 py-3 text-left text-sm font-semibold text-slate-700">Adjectival Rating</td>
                  <td class="border border-slate-200 px-3 py-3 text-center font-bold text-slate-800">{{ getAdjectivalRating(finalIPCRRating) }}</td>
                </tr>
                <tr class="bg-slate-50">
                  <td colspan="7" class="border border-slate-200 px-3 py-3 text-left text-sm text-slate-700">
                    Comments and Recommendations for Development Purposes:
                    <i class="font-semibold">{{ ipcr.remarks }}</i>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Footer signature table -->
          <table class="min-w-full border text-sm border-collapse">
            <tr class="text-slate-700">
              <td colspan="2" class="border border-slate-200 px-3 text-left text-xs font-semibold">Discuss with:</td>
              <td colspan="1" class="border border-slate-200 px-3 text-left text-xs font-semibold">Date:</td>
              <td colspan="3" class="border border-slate-200 px-3 text-left text-xs font-semibold">Assessed by:</td>
              <td colspan="1" class="border border-slate-200 px-3 text-left text-xs font-semibold">Date:</td>
              <td colspan="3" class="border border-slate-200 px-3 text-left text-xs font-semibold">Final Rating by:</td>
              <td colspan="2" class="border border-slate-200 px-3 text-left text-xs font-semibold">Date:</td>
            </tr>
            <tr>
              <td colspan="2" class="border border-slate-200 px-3 py-3 text-center">
                <br/><br/>
                <b class="uppercase text-slate-800">{{ employee.name }}</b><br/>
                <small class="text-slate-500">{{ employee.position }}</small>
              </td>
              <td colspan="1" class="border border-slate-200 px-3 py-3 text-center text-slate-700">
                <br/><br/>{{ formattedSubmittedForReviewAt }}
              </td>
              <td colspan="3" class="border border-slate-200 px-3 py-3 text-center">
                <br/><br/>
                <b class="uppercase text-slate-800">{{ supervisor.name }}</b><br/>
                <small class="text-slate-500">{{ supervisor.position }}</small>
              </td>
              <td colspan="1" class="border border-slate-200 px-3 py-3 text-center text-slate-700">
                <br/><br/>{{ formattedSubmittedRatingAt }}
              </td>
              <td colspan="3" class="border border-slate-200 px-3 py-3 text-center">
                <br/><br/>
                <b class="text-slate-800">{{ ocdUser?.name?.toUpperCase() ?? '—' }}</b><br/>
                <small class="text-slate-500">{{ ocdUser?.position ?? '' }}</small>
              </td>
              <td colspan="2" class="border border-slate-200 px-3 py-3 text-center text-slate-700">
                <br/><br/>____________________
              </td>
            </tr>
            <tr>
              <td colspan="12" class="border border-slate-200 px-3 py-2 text-left">
                <small class="text-slate-500"><i>Legend: &nbsp;&nbsp;1 - Effectiveness/Quality &nbsp;&nbsp; 2 - Efficiency &nbsp;&nbsp;3 - Timeliness &nbsp;&nbsp;4 - Average</i></small>
              </td>
            </tr>
          </table>
        </div>

      </div>

      <!-- Rate Accomplishment Modal -->
      <AppModal :show="isModalOpen" title="Rate Accomplishment" @close="isModalOpen = false">
        <div class="space-y-4">
          <AppTextarea v-model="form.accomplishment" label="Accomplishment" :rows="2" />
          <AppInput v-model="form.mov_link" label="MOV Link" type="text" />
          <div class="grid grid-cols-3 gap-3">
            <AppInput v-model="form.quality" label="Quality (1–5)" type="number" min="1" max="5" step="1" />
            <AppInput v-model="form.efficiency" label="Efficiency (1–5)" type="number" min="1" max="5" step="1" />
            <AppInput v-model="form.timeliness" label="Timeliness (1–5)" type="number" min="1" max="5" step="1" />
          </div>
          <div class="text-right text-sm font-semibold text-slate-700">Average: {{ liveAverage }}</div>
        </div>
        <template #footer>
          <AppButton variant="secondary" @click="isModalOpen = false">Cancel</AppButton>
          <AppButton :loading="isSubmitting" :disabled="isSubmitting" @click="saveModal">
            {{ isSubmitting ? 'Saving…' : 'Save' }}
          </AppButton>
        </template>
      </AppModal>

    </div>

    <!-- Return to Employee (from PMT) Modal -->
    <AppModal
      :show="showReturnFromPMTModal"
      title="Return IPCR to Employee"
      @close="showReturnFromPMTModal = false; returnFromPMTRemarks = ''"
    >
      <div class="space-y-4">
        <p class="text-sm text-slate-500">
          The PMT has requested a revision. Please provide remarks explaining what needs to be corrected.
          The employee will need to re-submit their accomplishments.
        </p>
        <AppTextarea
          v-model="returnFromPMTRemarks"
          label="Remarks / Justification"
          required
          :rows="4"
          placeholder="Explain why the IPCR is being returned for revision..."
        />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showReturnFromPMTModal = false; returnFromPMTRemarks = ''">Cancel</AppButton>
        <AppButton variant="danger" :loading="isSubmitting" :disabled="isSubmitting" @click="confirmReturnFromPMT">
          {{ isSubmitting ? 'Processing…' : 'Return to Employee' }}
        </AppButton>
      </template>
    </AppModal>

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
        <span class="mr-auto text-xs text-slate-400 self-center">
          {{ accViewerPlan?.accomplishments_count }} accomplishment(s) total
        </span>
        <AppButton variant="secondary" @click="closeAccViewer">Close</AppButton>
      </template>
    </AppModal>

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
