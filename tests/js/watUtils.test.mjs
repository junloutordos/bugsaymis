import assert from 'node:assert/strict'
import test from 'node:test'

import { checkWatCap, watCountsFromCalendarDays } from '../../resources/js/Utils/ClassRecord/watUtils.js'

test('uses consolidated WAT totals instead of counting each visible elective row', () => {
  const date = '2026-08-04'
  const counts = watCountsFromCalendarDays([{
    date,
    graded_count: 2,
    major_count: 0,
    items: [
      { subject_name: 'Elective: Agriculture', is_graded: true, is_major: false },
      { subject_name: 'Elective: Computer Science 5', is_graded: true, is_major: false },
      { subject_name: 'Elective: Design and Make Technology', is_graded: true, is_major: false },
      { subject_name: 'Social Science 5', is_graded: true, is_major: false },
    ],
  }])

  assert.deepEqual(counts.get(date), { graded: 2, major: 0 })
  assert.deepEqual(checkWatCap(counts, date, true, false), { ok: true, reason: null })
})

test('blocks an additional assessment after pending rows reach the daily maximum', () => {
  const date = '2026-08-04'
  const counts = watCountsFromCalendarDays([{
    date,
    graded_count: 3,
    major_count: 0,
    items: Array.from({ length: 5 }, () => ({ is_graded: true, is_major: false })),
  }])

  const result = checkWatCap(counts, date, true, false)

  assert.equal(result.ok, false)
  assert.match(result.reason, /already has 3 graded assessments/)
})

test('honors explicit zero consolidated totals', () => {
  const date = '2026-08-04'
  const counts = watCountsFromCalendarDays([{
    date,
    graded_count: 0,
    major_count: 0,
    items: [{ is_graded: true, is_major: true }],
  }])

  assert.deepEqual(counts.get(date), { graded: 0, major: 0 })
})
