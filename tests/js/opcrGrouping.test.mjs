import assert from 'node:assert/strict'
import test from 'node:test'

import { groupIndicatorsByProgram, dostAlignmentLabel } from '../../resources/js/Utils/OPCR/opcrGrouping.js'

function indicator({ id, program = null, pillar = null, strategy = null, subStrategy = null }) {
  return {
    id,
    agency_outcome: program ? { outcome: program } : null,
    sub_strategy: subStrategy
      ? {
          description: subStrategy,
          strategy: {
            name: strategy,
            pillar: { name: pillar },
          },
        }
      : null,
  }
}

test('groups indicators by Program', () => {
  const indicators = [
    indicator({ id: 1, program: 'A. STEM Secondary Education' }),
    indicator({ id: 2, program: 'A. STEM Secondary Education' }),
    indicator({ id: 3, program: 'B. STEM Promotion Program' }),
  ]

  const grouped = groupIndicatorsByProgram(indicators)

  assert.deepEqual(Object.keys(grouped).sort(), ['A. STEM Secondary Education', 'B. STEM Promotion Program'])
  assert.equal(grouped['A. STEM Secondary Education'].length, 2)
  assert.equal(grouped['B. STEM Promotion Program'].length, 1)
})

test('an indicator missing a program (should not happen now that it is required, but defensively handled) groups under "Untagged"', () => {
  const indicators = [indicator({ id: 1 })]

  const grouped = groupIndicatorsByProgram(indicators)

  assert.deepEqual(Object.keys(grouped), ['Untagged'])
})

test('the same Strategy can appear under two different Programs without breaking grouping', () => {
  const indicators = [
    indicator({ id: 1, program: 'A. STEM Secondary Education', pillar: 'Pillar 1', strategy: 'Strategy 1', subStrategy: 'Sub A' }),
    indicator({ id: 2, program: 'B. STEM Promotion Program', pillar: 'Pillar 1', strategy: 'Strategy 1', subStrategy: 'Sub A' }),
  ]

  const grouped = groupIndicatorsByProgram(indicators)

  assert.equal(grouped['A. STEM Secondary Education'][0].id, 1)
  assert.equal(grouped['B. STEM Promotion Program'][0].id, 2)
})

test('dostAlignmentLabel builds a Pillar > Strategy > Sub-Strategy breadcrumb', () => {
  const withTagging = indicator({ id: 1, program: 'A', pillar: 'Pillar 1', strategy: 'Strategy 1', subStrategy: 'Sub A' })

  assert.equal(dostAlignmentLabel(withTagging), 'Pillar 1 › Strategy 1 › Sub A')
})

test('dostAlignmentLabel returns null when the indicator has no DOST tagging', () => {
  const untagged = indicator({ id: 1, program: 'A' })

  assert.equal(dostAlignmentLabel(untagged), null)
})
