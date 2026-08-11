// Must stay in sync with App\Services\Learn\CourseCoverService::PRESET_KEYS —
// the key here is what's stored in learn_courses.cover_preset; the visual
// definition (gradient class) lives only here, never in the backend.
export const COURSE_COVER_PRESETS = [
  { key: 'indigo-diagonal', label: 'Indigo diagonal', class: 'bg-gradient-to-br from-indigo-600 to-indigo-900' },
  { key: 'sky-wave', label: 'Sky wave', class: 'bg-gradient-to-tr from-sky-500 to-indigo-700' },
  { key: 'navy-radial', label: 'Navy radial', class: 'bg-[radial-gradient(circle_at_30%_20%,#0867DB,#0A2A5E)]' },
  { key: 'slate-grid', label: 'Slate grid', class: 'bg-slate-800' },
  { key: 'indigo-sunrise', label: 'Indigo sunrise', class: 'bg-gradient-to-b from-indigo-400 to-indigo-800' },
  { key: 'ocean-deep', label: 'Ocean deep', class: 'bg-gradient-to-br from-blue-600 via-indigo-700 to-slate-900' },
]

export const DEFAULT_COVER_PRESET_KEY = COURSE_COVER_PRESETS[0].key
