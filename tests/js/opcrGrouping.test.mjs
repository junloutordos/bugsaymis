import assert from 'node:assert/strict'
import test from 'node:test'

import { groupIndicatorsByProgram, dostAlignmentLabel } from '../../resources/js/Utils/OPCR/opcrGrouping.js'

function outcome(name, { pillar = null, strategy = null, subStrategy = null } = {}) {
  return {
    outcome: name,
    dost_pillar_names_joined: pillar,
    dost_strategy_names_joined: strategy,
    dost_sub_strategy_descriptions_joined: subStrategy,
  }
}

function indicator({ id, program = null, pillar = null, strategy = null, subStrategy = null, sourceOutcome = null }) {
  return {
    id,
    agency_outcome: program ? outcome(program, { pillar, strategy, subStrategy }) : null,
    performance_indicator: sourceOutcome ? { agency_outcome: sourceOutcome } : null,
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

test('dostAlignmentLabel prefers the source Performance Indicator\'s own child outcome tagging over the shared parent Program\'s', () => {
  const nce = indicator({
    id: 1,
    program: 'B. STEM Promotion Program', // parent's own (misleading, aggregate) tags
    pillar: 'DOST Pillar 5: Governance; DOST Pillar 1: Human Well-Being',
    strategy: 'Strategy 17; Strategy 1',
    sourceOutcome: outcome('B. STEM Promotion Program', { pillar: 'DOST Pillar 5: Governance', strategy: 'Strategy 17' }),
  })
  const gwa = indicator({
    id: 2,
    program: 'B. STEM Promotion Program',
    pillar: 'DOST Pillar 5: Governance; DOST Pillar 1: Human Well-Being',
    strategy: 'Strategy 17; Strategy 1',
    sourceOutcome: outcome('B. STEM Promotion Program', { pillar: 'DOST Pillar 1: Human Well-Being', strategy: 'Strategy 1' }),
  })

  assert.equal(dostAlignmentLabel(nce), 'DOST Pillar 5: Governance › Strategy 17')
  assert.equal(dostAlignmentLabel(gwa), 'DOST Pillar 1: Human Well-Being › Strategy 1')
})

test('dostAlignmentLabel falls back to the Program\'s own tags when there is no linked Performance Indicator', () => {
  const noSource = indicator({ id: 1, program: 'A', pillar: 'Pillar 1', strategy: 'Strategy 1' })

  assert.equal(dostAlignmentLabel(noSource), 'Pillar 1 › Strategy 1')
})

test('dostAlignmentLabel passes through a Program already tagged to multiple Pillars/Strategies', () => {
  const multiTagged = indicator({
    id: 1,
    program: 'B',
    pillar: 'Pillar A; Pillar B',
    strategy: 'Strategy A; Strategy B',
    subStrategy: 'Sub A; Sub B',
  })

  assert.equal(dostAlignmentLabel(multiTagged), 'Pillar A; Pillar B › Strategy A; Strategy B › Sub A; Sub B')
})
