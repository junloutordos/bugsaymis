/**
 * Frontend mirror of App\Services\ClassRecord\WatRuleService (partial —
 * only the rules the Setup tab needs to evaluate client-side).
 */

// Mirrors WatRuleService::DAILY_GRADED_MAX / DAILY_MAJOR_MAX /
// WEEKLY_GRADED_MAX / WEEKLY_MAJOR_MAX. Visual/UX feedback only — the
// server (ClassRecordAssessmentController::upsert / applyToSections) is the
// authoritative check and re-validates independently on save.
export const WAT_LIMITS = {
  dailyGraded:  3,
  dailyMajor:   2,
  weeklyGraded: 15,
  weeklyMajor:  6,
}

/**
 * Converts section-calendar days into the count map consumed by WAT checks.
 * Prefer the server's consolidated totals because visible elective/science
 * rows that share a configured block count as one WAT unit. The item-based
 * fallback keeps the helper compatible with callers that only provide rows.
 *
 * @param {Array<{date:string, graded_count?:number, major_count?:number, count?:number, items?:Array}>} days
 * @returns {Map<string, {graded:number, major:number}>}
 */
export function watCountsFromCalendarDays(days) {
  const map = new Map()
  for (const day of days ?? []) {
    const graded = day.graded_count
      ?? day.items?.filter(item => item.is_graded !== false).length
      ?? day.count
      ?? 0
    const major = day.major_count
      ?? day.items?.filter(item => item.is_major).length
      ?? 0

    map.set(day.date, { graded, major })
  }
  return map
}

/** Monday (YYYY-MM-DD) of the week containing dateStr (YYYY-MM-DD). */
export function weekStartOf(dateStr) {
  const d = new Date(`${dateStr}T00:00:00`)
  const monday = new Date(d)
  monday.setDate(d.getDate() - ((d.getDay() + 6) % 7))
  const y = monday.getFullYear()
  const m = String(monday.getMonth() + 1).padStart(2, '0')
  const day = String(monday.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/**
 * Given a Map<date, {graded, major}> of per-day counts already plotted for
 * a section (server baseline + live unsaved draft — see Show.vue's
 * dateCounts computed), returns whether placing one more graded/major
 * assessment on `date` would breach the daily or weekly cap. Weekly total is
 * summed across the 5 weekdays sharing `date`'s Monday–Friday window.
 *
 * @param {Map<string, {graded:number, major:number}>} dateCounts
 * @param {string} date        YYYY-MM-DD
 * @param {boolean} isGraded
 * @param {boolean} isMajor
 * @returns {{ok: boolean, reason: ?string}}
 */
export function checkWatCap(dateCounts, date, isGraded, isMajor) {
  if (!isGraded) return { ok: true, reason: null }

  const day = dateCounts.get(date) ?? { graded: 0, major: 0 }
  if (day.graded + 1 > WAT_LIMITS.dailyGraded) {
    return { ok: false, reason: `Section already has ${WAT_LIMITS.dailyGraded} graded assessments on ${date} — pick another date.` }
  }
  if (isMajor && day.major + 1 > WAT_LIMITS.dailyMajor) {
    return { ok: false, reason: `Section already has ${WAT_LIMITS.dailyMajor} major assessments on ${date} — pick another date.` }
  }

  const monday = weekStartOf(date)
  let weekGraded = 0
  let weekMajor  = 0
  for (let i = 0; i < 5; i++) {
    const d = new Date(`${monday}T00:00:00`)
    d.setDate(d.getDate() + i)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    const c = dateCounts.get(key) ?? { graded: 0, major: 0 }
    weekGraded += c.graded
    weekMajor  += c.major
  }
  if (weekGraded + 1 > WAT_LIMITS.weeklyGraded) {
    return { ok: false, reason: `Section already has ${WAT_LIMITS.weeklyGraded} graded assessments this week — pick another week.` }
  }
  if (isMajor && weekMajor + 1 > WAT_LIMITS.weeklyMajor) {
    return { ok: false, reason: `Section already has ${WAT_LIMITS.weeklyMajor} major assessments this week — pick another week.` }
  }

  return { ok: true, reason: null }
}

/**
 * True when today falls within the Monday–Sunday week containing
 * activityDate (YYYY-MM-DD). Mirrors
 * WatRuleService::isWithinScheduledWeek() — a plotted assessment is only
 * "announced to students" in a way that matters once its week has arrived;
 * outside that window a direct delete is allowed instead of routing through
 * the ACIDAA "Request Deletion" approval flow.
 */
export function isWithinScheduledWeek(activityDate) {
  if (!activityDate) return false

  const activity = new Date(`${activityDate}T00:00:00`)
  if (Number.isNaN(activity.getTime())) return false

  // ISO week: Monday = start, Sunday = end
  const dayOfWeek = activity.getDay() // 0 = Sunday .. 6 = Saturday
  const diffToMonday = (dayOfWeek + 6) % 7

  const weekStart = new Date(activity)
  weekStart.setDate(activity.getDate() - diffToMonday)
  weekStart.setHours(0, 0, 0, 0)

  const weekEnd = new Date(weekStart)
  weekEnd.setDate(weekStart.getDate() + 6)
  weekEnd.setHours(23, 59, 59, 999)

  const now = new Date()
  return now >= weekStart && now <= weekEnd
}
