export const FUNCTION_TYPE_ORDER = {
  "Strategic Functions": 1,
  "Core Functions": 2,
  "Support Functions": 3,
  "Uncategorized": 4,
};

export function normalizeFunctionType(raw) {
  if (!raw) return "Uncategorized";
  const t = String(raw).trim().toLowerCase();
  if (t === "strategic" || t === "strategic functions" || t === "strategic function") return "Strategic Functions";
  if (t === "core" || t === "core functions" || t === "core function") return "Core Functions";
  if (t === "support" || t === "support functions" || t === "support function") return "Support Functions";
  return String(raw).trim();
}

// Marker format written by WorkDistributionPlanClassifier for a
// materialized Teaching Load row: "<SourceClass>#<assignmentId>@<taggedPlanId>".
const MATERIALIZED_MARKER = /#\d+@\d+$/;

/**
 * Display text for a merged Sub-Outcome cell (rowspan across every plan
 * sharing that label). A load_source-tagged plan (e.g. a shared framework
 * row) or a materialized per-subject Teaching Load row (marker-based
 * sub_outcome) has its individual_target personalized per faculty member —
 * when one exists in the group, it replaces the static sub_outcome label
 * (which for a materialized row is just its own technical marker), shown
 * once no matter how many plans share the group. Falls back to the static
 * label when nothing in the group is personalized this way, or the
 * personalized plan has no target yet.
 */
export function subOutcomeDisplayFor(pis, staticLabel) {
  const allPlans = Object.values(pis).flat();
  const tagged = allPlans.find((p) => {
    if (!p.pivot?.individual_target) return false;
    if (p.load_source) return true;
    return MATERIALIZED_MARKER.test(p.performance_indicator?.agency_outcome?.sub_outcome || '');
  });
  return tagged ? tagged.pivot.individual_target : staticLabel;
}

export function groupPlansByOutcome(plans) {
  const groups = {};

  (plans || []).forEach((plan) => {
    const aoo = plan.performance_indicator?.agency_outcome;
    const functionType = normalizeFunctionType(aoo?.function_type);
    const outcome = aoo?.parent?.outcome ?? aoo?.outcome ?? "Uncategorized";
    const subOutcome = aoo?.sub_outcome || "—";
    const piDesc = plan.performance_indicator?.description || "—";

    if (!groups[functionType]) groups[functionType] = {};
    if (!groups[functionType][outcome]) groups[functionType][outcome] = {};
    if (!groups[functionType][outcome][subOutcome]) groups[functionType][outcome][subOutcome] = {};
    if (!groups[functionType][outcome][subOutcome][piDesc]) groups[functionType][outcome][subOutcome][piDesc] = [];
    groups[functionType][outcome][subOutcome][piDesc].push(plan);
  });

  const sorted = {};
  Object.keys(FUNCTION_TYPE_ORDER).forEach((ft) => { if (groups[ft]) sorted[ft] = groups[ft]; });
  Object.keys(groups).filter((ft) => !FUNCTION_TYPE_ORDER[ft]).sort().forEach((ft) => (sorted[ft] = groups[ft]));

  Object.keys(sorted).forEach((ft) => {
    const sortedOutcomes = {};
    Object.keys(sorted[ft]).sort().forEach((outcome) => {
      sortedOutcomes[outcome] = {};
      Object.keys(sorted[ft][outcome]).sort().forEach((sub) => {
        const sortedPI = {};
        Object.keys(sorted[ft][outcome][sub]).sort().forEach((piDesc) => {
          sortedPI[piDesc] = sorted[ft][outcome][sub][piDesc];
        });
        sortedOutcomes[outcome][sub] = sortedPI;
      });
    });
    sorted[ft] = sortedOutcomes;
  });

  return sorted;
}
