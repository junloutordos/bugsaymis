/**
 * Frontend mirror of App\Services\ClassRecord\WatRuleService (partial —
 * only the rules the Setup tab needs to evaluate client-side).
 */

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
