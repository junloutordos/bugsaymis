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

/**
 * Display text for a merged Sub-Outcome cell (rowspan across every plan
 * sharing that label). A load_source-tagged plan (e.g. Teaching Load) has
 * its individual_target personalized per faculty member (e.g. every subject
 * taught, listed as a bullet) — when one exists in the group, it replaces
 * the static sub_outcome label, shown once no matter how many tagged plans
 * share the group. Falls back to the static label when nothing in the
 * group is load_source-tagged, or the tagged plan has no target yet.
 */
export function subOutcomeDisplayFor(pis, staticLabel) {
  const allPlans = Object.values(pis).flat();
  const tagged = allPlans.find((p) => p.load_source && p.pivot?.individual_target);
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
