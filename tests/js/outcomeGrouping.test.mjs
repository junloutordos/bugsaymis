import assert from 'node:assert/strict'
import test from 'node:test'

import { groupPlansByOutcome, normalizeFunctionType } from '../../resources/js/Utils/IPCR/outcomeGrouping.js'

function plan({ functionType, outcome, subOutcome, piDesc, parentOutcome = null }) {
  return {
    performance_indicator: {
      description: piDesc,
      agency_outcome: {
        function_type: functionType,
        outcome,
        sub_outcome: subOutcome,
        parent: parentOutcome ? { outcome: parentOutcome } : null,
      },
    },
  }
}

test('groups by the parent outcome instead of each child row\'s own outcome text', () => {
  const plans = [
    plan({ functionType: 'Strategic Functions', outcome: 'A. STEM Secondary Education on Scholarship Basis Program', subOutcome: 'A.1', piDesc: 'Indicator 1', parentOutcome: 'A. STEM Secondary Education on Scholarship Basis Program' }),
    plan({ functionType: 'Strategic Functions', outcome: 'A. STEM Secondary Education on Scholarship Basis Program', subOutcome: 'A.2', piDesc: 'Indicator 2', parentOutcome: 'A. STEM Secondary Education on Scholarship Basis Program' }),
  ]

  const grouped = groupPlansByOutcome(plans)

  assert.deepEqual(Object.keys(grouped['Strategic Functions']), ['A. STEM Secondary Education on Scholarship Basis Program'])
  assert.deepEqual(
    Object.keys(grouped['Strategic Functions']['A. STEM Secondary Education on Scholarship Basis Program']).sort(),
    ['A.1', 'A.2']
  )
})

test('does not truncate sub_outcome text and keeps two long sub-outcomes distinct', () => {
  const plans = [
    plan({ functionType: 'Core Functions', outcome: 'B. Something', subOutcome: 'B.1 Long descriptive sub-outcome text one', piDesc: 'Indicator A' }),
    plan({ functionType: 'Core Functions', outcome: 'B. Something', subOutcome: 'B.1 Long descriptive sub-outcome text two', piDesc: 'Indicator B' }),
  ]

  const grouped = groupPlansByOutcome(plans)
  const subKeys = Object.keys(grouped['Core Functions']['B. Something']).sort()

  assert.deepEqual(subKeys, [
    'B.1 Long descriptive sub-outcome text one',
    'B.1 Long descriptive sub-outcome text two',
  ])
})

test('normalizeFunctionType maps legacy casing/spelling to canonical labels', () => {
  assert.equal(normalizeFunctionType('strategic'), 'Strategic Functions')
  assert.equal(normalizeFunctionType('Core function'), 'Core Functions')
  assert.equal(normalizeFunctionType(null), 'Uncategorized')
})
