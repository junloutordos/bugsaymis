import assert from 'node:assert/strict'
import test from 'node:test'

import { groupPlansByOutcome, normalizeFunctionType, subOutcomeDisplayFor } from '../../resources/js/Utils/IPCR/outcomeGrouping.js'

function plan({ functionType, outcome, subOutcome, piDesc, parentOutcome = null, loadSource = null, individualTarget = null }) {
  return {
    load_source: loadSource,
    pivot: individualTarget ? { individual_target: individualTarget } : {},
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

test('groupPlansByOutcome merges materialized rows for the same subject group across different tagged plans', () => {
  const plans = [
    plan({ functionType: 'Core Functions', outcome: 'Core Functions', subOutcome: 'App\\Models\\FacultyLoading\\LoadAssignment#261@536', piDesc: 'Core Functions' }),
    plan({ functionType: 'Core Functions', outcome: 'Core Functions', subOutcome: 'App\\Models\\FacultyLoading\\LoadAssignment#261@537', piDesc: 'Core Functions' }),
    // A different subject group (261 vs 262) must stay separate.
    plan({ functionType: 'Core Functions', outcome: 'Core Functions', subOutcome: 'App\\Models\\FacultyLoading\\LoadAssignment#262@536', piDesc: 'Core Functions' }),
  ]

  const grouped = groupPlansByOutcome(plans)
  const subKeys = Object.keys(grouped['Core Functions']['Core Functions']).sort()

  assert.deepEqual(subKeys, [
    'App\\Models\\FacultyLoading\\LoadAssignment#261',
    'App\\Models\\FacultyLoading\\LoadAssignment#262',
  ])
  assert.equal(grouped['Core Functions']['Core Functions']['App\\Models\\FacultyLoading\\LoadAssignment#261']['Core Functions'].length, 2)
})

test('normalizeFunctionType maps legacy casing/spelling to canonical labels', () => {
  assert.equal(normalizeFunctionType('strategic'), 'Strategic Functions')
  assert.equal(normalizeFunctionType('Core function'), 'Core Functions')
  assert.equal(normalizeFunctionType(null), 'Uncategorized')
})

test('subOutcomeDisplayFor falls back to the static label when nothing in the group is load_source-tagged', () => {
  const pis = {
    'Indicator A': [plan({ piDesc: 'Indicator A' })],
  }

  assert.equal(subOutcomeDisplayFor(pis, 'B.1 Static Label'), 'B.1 Static Label')
})

test('subOutcomeDisplayFor shows the personalized subject list from a load_source-tagged plan instead of the static label', () => {
  const pis = {
    'Indicator A': [plan({ piDesc: 'Indicator A', loadSource: 'teaching', individualTarget: '• Mathematics 1\n• Science 1' })],
  }

  assert.equal(subOutcomeDisplayFor(pis, 'B.1 Static Label'), '• Mathematics 1\n• Science 1')
})

test('subOutcomeDisplayFor shows the subject list once even when multiple tagged plans share the group', () => {
  const pis = {
    'Indicator A': [plan({ piDesc: 'Indicator A', loadSource: 'teaching', individualTarget: '• Mathematics 1\n• Science 1' })],
    'Indicator B': [plan({ piDesc: 'Indicator B', loadSource: 'teaching', individualTarget: '• Mathematics 1\n• Science 1' })],
  }

  assert.equal(subOutcomeDisplayFor(pis, 'B.1 Static Label'), '• Mathematics 1\n• Science 1')
})

test('subOutcomeDisplayFor falls back to the static label when the load_source-tagged plan has no personalized target yet', () => {
  const pis = {
    'Indicator A': [plan({ piDesc: 'Indicator A', loadSource: 'teaching', individualTarget: null })],
  }

  assert.equal(subOutcomeDisplayFor(pis, 'B.1 Static Label'), 'B.1 Static Label')
})

test('subOutcomeDisplayFor shows a materialized Teaching Load row\'s own target instead of its raw marker', () => {
  const pis = {
    'Indicator A': [plan({
      piDesc: 'Indicator A',
      subOutcome: 'App\\Models\\FacultyLoading\\LoadAssignment#261@536',
      individualTarget: 'Elective: Design and Make Technology — Section A (5.00 u)',
    })],
  }

  assert.equal(
    subOutcomeDisplayFor(pis, 'App\\Models\\FacultyLoading\\LoadAssignment#261@536'),
    'Elective: Design and Make Technology — Section A (5.00 u)'
  )
})

test('subOutcomeDisplayFor falls back to the static label for a plain (untagged) auto-generated marker with no target', () => {
  const pis = {
    'Indicator A': [plan({
      piDesc: 'Indicator A',
      subOutcome: 'App\\Models\\FacultyLoading\\LoadAssignment#117',
      individualTarget: null,
    })],
  }

  assert.equal(
    subOutcomeDisplayFor(pis, 'App\\Models\\FacultyLoading\\LoadAssignment#117'),
    'App\\Models\\FacultyLoading\\LoadAssignment#117'
  )
})
