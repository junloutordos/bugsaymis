/**
 * Frontend mirror of App\Services\ClassRecord\GradeComputationService.
 * Pure functions — no side effects, fully testable.
 */

/**
 * Compute aggregate score for one grading category.
 * Null scores are treated as 0.
 */
export function computeCategoryScore(scores, maxScores, weight) {
  const total    = scores.reduce((sum, s) => sum + (s ?? 0), 0)
  const maxTotal = maxScores.reduce((sum, m) => sum + m, 0)
  const pct      = maxTotal > 0 ? total / maxTotal : 0
  return {
    total,
    maxTotal,
    percentage:         pct,
    weightedPercentage: pct * weight,
  }
}

/**
 * Look up GE and adjectival for a given percentage score (0–100).
 * Clamps to the stanine table range (39–100).
 */
export function lookupGradeEquivalent(percentageScore, stanineLookup) {
  const pct = Math.max(39, Math.min(100, Math.floor(percentageScore)))
  const row = stanineLookup.find(r => Number(r.percentage) === pct)
  if (!row) return { gradeEquivalent: 4.510, adjectivalEquivalent: 'Failed' }
  return {
    gradeEquivalent:      Number(row.grade_equivalent),
    adjectivalEquivalent: row.adjectival_equivalent,
  }
}

/**
 * Compute the running (rolling) grade after applying the quarter formula.
 *
 * Q1: finalGrade = currentGE
 * Q2–Q4: finalGrade = floor3((currentGE × 2/3) + (prev × 1/3))
 *
 * Uses Math.floor to 3dp to match Excel behaviour (not Math.round).
 */
export function computeRunningGrade(currentGE, previousGrade, quarter) {
  if (quarter === 1) {
    return currentGE
  }
  // Q1 never computed → use 5.0 (Failed) as fallback previous grade
  const prev = (previousGrade === null || previousGrade === undefined) ? 5.0 : previousGrade
  const raw  = (currentGE * 2 / 3) + (prev * 1 / 3)
  return Math.floor(raw * 1000) / 1000
}

/**
 * Full computation for one student in one quarter.
 *
 * @param {Array} categories  - [{ id, code, weight, assessments: [{ id, maxScore }] }]
 * @param {Object} scoresMap  - { [`${assessmentId}`]: number|null }
 * @param {Array} stanineLookup
 * @param {number|null} previousGrade - running grade from previous quarter
 * @param {number} quarter  - 1-4
 */
export function computeStudentGrade(categories, scoresMap, stanineLookup, previousGrade, quarter) {
  const categoryResults = categories.map(cat => {
    const rawScores = cat.assessments.map(a => scoresMap[a.id] ?? null)
    const maxScores = cat.assessments.map(a => Number(a.maxScore ?? a.max_score))
    const result    = computeCategoryScore(rawScores, maxScores, Number(cat.weight))
    return { catId: cat.id, catCode: cat.code, weight: Number(cat.weight), ...result }
  })

  const twPct        = categoryResults.reduce((s, r) => s + r.weightedPercentage, 0)
  const percentScore = Math.round(twPct * 100 * 100) / 100  // round to 2dp

  const { gradeEquivalent, adjectivalEquivalent } = lookupGradeEquivalent(percentScore, stanineLookup)

  const runningGrade = computeRunningGrade(gradeEquivalent, previousGrade, quarter)
  const runningLookup = lookupGradeEquivalent(
    // Convert running GE back to a percentage for the lookup — we find nearest match
    ge2Pct(runningGrade, stanineLookup),
    stanineLookup,
  )

  return {
    categoryResults,
    twPct,
    percentScore,
    gradeEquivalent,
    adjectivalEquivalent,
    runningGrade,
    runningGradeGE:     runningGrade,
    runningAdjectival:  runningLookup.adjectivalEquivalent,
  }
}

/**
 * Convert a GE back to a percentage by finding the closest row in the stanine table.
 * Used to look up the adjectival of a running grade.
 */
function ge2Pct(ge, stanineLookup) {
  let closest = null
  let minDiff = Infinity
  for (const row of stanineLookup) {
    const diff = Math.abs(Number(row.grade_equivalent) - ge)
    if (diff < minDiff) { minDiff = diff; closest = row }
  }
  return closest ? Number(closest.percentage) : 39
}

/**
 * Compute the final annual grade from 4 quarterly GEs.
 * Formula: simple average of all 4 quarterly GEs, rounded to 3dp.
 * Mirrors GradeComputationService::computeFinalGrade().
 */
export function computeFinalGrade(q1GE, q2GE, q3GE, q4GE, stanineLookup) {
  const finalGE = Math.round(((q1GE + q2GE + q3GE + q4GE) / 4) * 1000) / 1000
  const { adjectivalEquivalent } = lookupGradeEquivalent(
    // Convert GE back to percentage via nearest stanine row for the lookup
    (() => {
      let closest = null, minDiff = Infinity
      for (const row of stanineLookup) {
        const diff = Math.abs(Number(row.grade_equivalent) - finalGE)
        if (diff < minDiff) { minDiff = diff; closest = row }
      }
      return closest ? Number(closest.percentage) : 39
    })(),
    stanineLookup,
  )
  return { finalGE, adjectivalEquivalent }
}

/** CSS class for adjectival badge coloring */
export function adjectivalColor(adj) {
  if (!adj) return 'text-slate-400'
  const a = adj.toLowerCase()
  if (a === 'excellent' || a === 'very good') return 'text-emerald-600 font-semibold'
  if (a === 'good' || a === 'satisfactory')   return 'text-blue-600'
  if (a === 'fair')                           return 'text-amber-600'
  if (a.includes('condition'))                return 'text-orange-600'
  if (a === 'failed')                         return 'text-red-600 font-semibold'
  return 'text-slate-500'
}

/** Format a decimal as a string rounded to n places */
export function fmtPct(val, places = 2) {
  if (val === null || val === undefined || isNaN(val)) return '—'
  return (val * 100).toFixed(places) + '%'
}

export function fmtGE(val) {
  if (val === null || val === undefined) return '—'
  return Number(val).toFixed(3)
}
