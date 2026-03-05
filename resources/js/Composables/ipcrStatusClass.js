/**
 * Returns the Tailwind badge classes for a given IPCR status.
 * Single source of truth — import this in all PM module files.
 */
export const ipcrStatusClass = (status) => {
  switch (status) {
    case 'New Target':                return 'bg-blue-100 text-blue-700'
    case 'For Review':                return 'bg-yellow-100 text-yellow-700'
    case 'Targets Approved':          return 'bg-green-100 text-green-700'
    case 'Submitted for Rating':      return 'bg-orange-100 text-orange-700'
    case 'Rated & For PMT Review':    return 'bg-violet-100 text-violet-700'
    case 'Submitted to PMT':          return 'bg-purple-100 text-purple-700'
    case 'PMT Returned for Revision': return 'bg-rose-100 text-rose-700'
    case 'Approved by PMT':           return 'bg-teal-100 text-teal-700'
    case 'Returned for Revision':     return 'bg-red-100 text-red-700'
    case 'Rejected':                  return 'bg-red-200 text-red-800'
    default:                          return 'bg-gray-100 text-gray-700'
  }
}
