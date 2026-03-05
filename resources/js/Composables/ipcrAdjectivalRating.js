/**
 * Returns the CSC adjectival rating for a given IPCR average.
 * Scale: 5 = Outstanding | 4.0–4.99 = Very Satisfactory |
 *        3.0–3.99 = Satisfactory | 2.0–2.99 = Unsatisfactory | 1.0–1.99 = Poor
 */
export const ipcrAdjectivalRating = (rating) => {
  const n = parseFloat(rating)
  if (isNaN(n) || n === 0) return '—'
  if (n >= 5)   return 'Outstanding'
  if (n >= 4.0) return 'Very Satisfactory'
  if (n >= 3.0) return 'Satisfactory'
  if (n >= 2.0) return 'Unsatisfactory'
  if (n >= 1.0) return 'Poor'
  return '—'
}
