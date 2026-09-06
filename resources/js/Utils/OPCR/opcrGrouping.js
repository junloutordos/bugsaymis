const UNTAGGED = "Untagged"

/**
 * Groups a flat OPCR indicator list by PSHS Program — the primary/required
 * tag on every indicator. Pillar/Strategy/Sub-Strategy are secondary,
 * optional alignment notes displayed per-row, not a grouping level: the
 * same Strategy can tag into multiple different Programs, so grouping by
 * the DOST hierarchy first no longer reflects a stable structure.
 */
export function groupIndicatorsByProgram(indicators) {
  const grouped = {}

  for (const indicator of indicators) {
    const programName = indicator.agency_outcome?.outcome ?? UNTAGGED
    grouped[programName] ??= []
    grouped[programName].push(indicator)
  }

  return grouped
}

/**
 * Builds the "Pillar / Strategy / Sub-Strategy" alignment label for one
 * indicator row, or null when there's no DOST tagging at all. Sourced from
 * the linked Performance Indicator's own outcome node (which may be a
 * specific child under the Program, distinct from its siblings) via its
 * many-to-many DOST Strategic Plan tagging (AgencyOutcome::$appends) — not
 * from the OPCR indicator's own Program field, which always holds the
 * top-level Program and would otherwise collapse every indicator under one
 * Program to the same aggregate tags. Falls back to the Program's own tags
 * only when there's no linked Performance Indicator.
 */
export function dostAlignmentLabel(indicator) {
  const outcome = indicator.performance_indicator?.agency_outcome ?? indicator.agency_outcome
  const pillar = outcome?.dost_pillar_names_joined
  const strategy = outcome?.dost_strategy_names_joined
  const subStrategy = outcome?.dost_sub_strategy_descriptions_joined
  if (!pillar && !strategy && !subStrategy) return null

  return [pillar, strategy, subStrategy].filter(Boolean).join(" › ")
}
