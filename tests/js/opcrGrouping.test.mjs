import assert from 'node:assert/strict'
import test from 'node:test'

import { groupIndicatorsByHierarchy } from '../../resources/js/Utils/OPCR/opcrGrouping.js'

function indicator({ id, pillar = null, strategy = null, subStrategy = null }) {
  return {
    id,
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

test('groups indicators by pillar -> strategy -> sub-strategy', () => {
  const indicators = [
    indicator({ id: 1, pillar: 'Pillar 1', strategy: 'Strategy 1', subStrategy: 'Sub A' }),
    indicator({ id: 2, pillar: 'Pillar 1', strategy: 'Strategy 1', subStrategy: 'Sub A' }),
    indicator({ id: 3, pillar: 'Pillar 1', strategy: 'Strategy 2', subStrategy: 'Sub B' }),
  ]

  const grouped = groupIndicatorsByHierarchy(indicators)

  assert.deepEqual(Object.keys(grouped), ['Pillar 1'])
  assert.deepEqual(Object.keys(grouped['Pillar 1']).sort(), ['Strategy 1', 'Strategy 2'])
  assert.equal(grouped['Pillar 1']['Strategy 1']['Sub A'].length, 2)
  assert.equal(grouped['Pillar 1']['Strategy 2']['Sub B'].length, 1)
})

test('untagged indicators (no sub_strategy) group under a top-level "Untagged" key', () => {
  const indicators = [indicator({ id: 1 })]

  const grouped = groupIndicatorsByHierarchy(indicators)

  assert.deepEqual(Object.keys(grouped), ['Untagged'])
  assert.equal(grouped['Untagged']['Untagged']['Untagged'].length, 1)
})

test('mixed tagged and untagged indicators both appear', () => {
  const indicators = [
    indicator({ id: 1, pillar: 'Pillar 1', strategy: 'Strategy 1', subStrategy: 'Sub A' }),
    indicator({ id: 2 }),
  ]

  const grouped = groupIndicatorsByHierarchy(indicators)

  assert.deepEqual(Object.keys(grouped).sort(), ['Pillar 1', 'Untagged'])
})
