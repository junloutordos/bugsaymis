const UNTAGGED = 'Untagged'

/**
 * Groups a flat OPCR indicator list into { pillar: { strategy: { subStrategy: [indicator, ...] } } }.
 * Indicators without a sub_strategy land under the single "Untagged" bucket at every level.
 */
export function groupIndicatorsByHierarchy(indicators) {
  const grouped = {}

  for (const indicator of indicators) {
    const subStrategy = indicator.sub_strategy
    const pillarName = subStrategy?.strategy?.pillar?.name ?? UNTAGGED
    const strategyName = subStrategy?.strategy?.name ?? UNTAGGED
    const subStrategyName = subStrategy?.description ?? UNTAGGED

    grouped[pillarName] ??= {}
    grouped[pillarName][strategyName] ??= {}
    grouped[pillarName][strategyName][subStrategyName] ??= []
    grouped[pillarName][strategyName][subStrategyName].push(indicator)
  }

  return grouped
}
