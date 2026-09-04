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
 * indicator row, or null when the indicator has no DOST tagging at all.
 */
export function dostAlignmentLabel(indicator) {
  const sub = indicator.sub_strategy
  if (!sub) return null

  const pillar = sub.strategy?.pillar?.name
  const strategy = sub.strategy?.name
  return [pillar, strategy, sub.description].filter(Boolean).join(" › ")
}
