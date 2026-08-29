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

// Marker formats written by WorkDistributionPlanClassifier for an
// auto-generated row's identity — a plain per-assignment Core/Support
// fallback ("<SourceClass>#<assignmentId>") or a materialized Teaching Load
// row ("<SourceClass>#<assignmentId>@<taggedPlanId>"). Both are technical
// identifiers, never meant to be shown to a user — a plan carrying one of
// these must have its own personalized individual_target instead.
const AUTO_GENERATED_MARKER = /#\d+(@\d+)?$/;

/**
 * Grouping key for the Sub-Outcome column. A materialized Teaching Load
 * row's marker is unique per (subject group, tagged plan) so that each
 * stays independently rateable — but two tagged plans for the SAME subject
 * group should still merge into one Sub-Outcome cell instead of rendering
 * as two separate blocks. Stripping the "@<taggedPlanId>" suffix collapses
 * them back to one grouping key while each plan's own row (and its own
 * marker, used elsewhere for identity/reconciliation) is untouched.
 */
function subOutcomeGroupKey(raw) {
  if (!raw) return "—";
  return String(raw).replace(/@\d+$/, "");
}

/**
 * Display text for a merged Sub-Outcome cell (rowspan across every plan
 * sharing that label). A load_source-tagged plan (e.g. a shared framework
 * row), a materialized per-subject Teaching Load row or per-assignment
 * fallback (marker-based sub_outcome), or a plan explicitly tagged on a
 * Designations-module Category/Designation (no sub_outcome identity of its
 * own at all — intentionally NULL) all get their individual_target
 * personalized per faculty member — when one exists in the group, it
 * replaces the static sub_outcome label (which for a marker-based row is
 * just its own technical marker, and for a NULL-sub_outcome row is nothing
 * at all — the group key falls back to "—"), shown once no matter how many
 * plans share the group. Falls back to the static label only when nothing
 * in the group is personalized this way.
 */
export function subOutcomeDisplayFor(pis, staticLabel) {
  const allPlans = Object.values(pis).flat();
  const tagged = allPlans.find((p) => {
    if (!p.pivot?.individual_target) return false;
    if (p.load_source) return true;
    const subOutcome = p.performance_indicator?.agency_outcome?.sub_outcome;
    if (!subOutcome) return true; // no static identity marker to fall back to — must use the personalized text
    return AUTO_GENERATED_MARKER.test(subOutcome);
  });
  return tagged ? tagged.pivot.individual_target : staticLabel;
}

export function groupPlansByOutcome(plans) {
  const groups = {};

  (plans || []).forEach((plan) => {
    const aoo = plan.performance_indicator?.agency_outcome;
    const functionType = normalizeFunctionType(aoo?.function_type);
    const outcome = aoo?.parent?.outcome ?? aoo?.outcome ?? "Uncategorized";
    const subOutcome = subOutcomeGroupKey(aoo?.sub_outcome);
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
