/**
 * Returns the CSC SPMS adjectival rating for a given IPCR average.
 * Bands mirror IPCRWorkflowService::adjectivalRating() — keep them in sync:
 * 4.51–5.00 Outstanding | 3.51–4.50 Very Satisfactory | 2.51–3.50 Satisfactory |
 * 1.51–2.50 Unsatisfactory | 1.00–1.50 Poor
 */
export const ipcrAdjectivalRating = (rating) => {
  const n = parseFloat(rating)
  if (isNaN(n) || n === 0) return '—'
  if (n >= 4.51) return 'Outstanding'
  if (n >= 3.51) return 'Very Satisfactory'
  if (n >= 2.51) return 'Satisfactory'
  if (n >= 1.51) return 'Unsatisfactory'
  if (n >= 1.0)  return 'Poor'
  return '—'
}
