<template>
  <div class="relative">
    <button ref="triggerEl" type="button"
      :disabled="disabled"
      @click="toggle"
      class="w-full flex items-center justify-between gap-1.5 rounded border border-slate-200 bg-white px-2 py-1 text-sm text-left focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400">
      <span :class="modelValue ? 'text-slate-800' : 'text-slate-400'">
        {{ displayLabel }}
      </span>
      <span class="flex items-center gap-0.5 shrink-0">
        <XMarkIcon v-if="modelValue && !disabled" class="h-3.5 w-3.5 text-slate-400 hover:text-slate-600"
          @click.stop="clear" />
        <CalendarDaysIcon class="h-4 w-4 text-slate-400" />
      </span>
    </button>

    <Teleport to="body">
      <div v-if="open" ref="panelEl"
        :style="panelStyle"
        class="fixed z-[70] w-64 rounded-xl bg-white shadow-xl ring-1 ring-slate-200 p-3 select-none">
        <div class="flex items-center justify-between mb-2">
          <button type="button" @click="shiftMonth(-1)"
            class="p-1 rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-700">
            <ChevronLeftIcon class="h-4 w-4" />
          </button>
          <span class="text-sm font-semibold text-slate-800">{{ monthLabel }}</span>
          <button type="button" @click="shiftMonth(1)"
            class="p-1 rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-700">
            <ChevronRightIcon class="h-4 w-4" />
          </button>
        </div>

        <div class="grid grid-cols-7 mb-1">
          <span v-for="d in DOW_LABELS" :key="d"
            class="text-center text-[10px] font-semibold text-slate-400 uppercase">{{ d }}</span>
        </div>

        <div class="grid grid-cols-7 gap-y-0.5">
          <button v-for="cell in gridCells" :key="cell.date" type="button"
            :disabled="cell.disabled"
            @click="pick(cell)"
            :class="['h-8 w-8 mx-auto flex items-center justify-center rounded-full text-xs transition-colors',
              cell.date === modelValue
                ? 'bg-indigo-600 text-white font-semibold'
                : cell.disabled
                  ? 'text-slate-300 cursor-not-allowed line-through decoration-slate-200'
                  : cell.inMonth
                    ? 'text-slate-700 hover:bg-indigo-50'
                    : 'text-slate-400 hover:bg-slate-50',
              cell.isToday && cell.date !== modelValue ? 'ring-1 ring-inset ring-indigo-300' : '']">
            {{ cell.day }}
          </button>
        </div>

        <div v-if="disabledWeekdays.length" class="mt-2 pt-2 border-t border-slate-100 text-[11px] text-slate-400 leading-snug">
          Greyed days have no scheduled class.
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: { type: String, default: '' },
  /** Weekday names ('Monday'…'Sunday') that cannot be picked. */
  disabledWeekdays: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Pick a date' },
})

const emit = defineEmits(['update:modelValue', 'change'])

const DOW_LABELS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']
const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

const open = ref(false)
const triggerEl = ref(null)
const panelEl = ref(null)
const panelStyle = ref({})

const today = new Date()
const viewYear = ref(today.getFullYear())
const viewMonth = ref(today.getMonth())

function toDateString(y, m, d) {
  return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
}

const todayStr = toDateString(today.getFullYear(), today.getMonth(), today.getDate())

const displayLabel = computed(() => {
  if (!props.modelValue) return props.placeholder
  const [y, m, d] = props.modelValue.split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
})

const monthLabel = computed(() =>
  new Date(viewYear.value, viewMonth.value, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
)

const gridCells = computed(() => {
  const first = new Date(viewYear.value, viewMonth.value, 1)
  const start = new Date(first)
  start.setDate(first.getDate() - ((first.getDay() + 6) % 7)) // back to Monday

  const cells = []
  const cursor = new Date(start)
  for (let i = 0; i < 42; i++) {
    const date = toDateString(cursor.getFullYear(), cursor.getMonth(), cursor.getDate())
    cells.push({
      date,
      day: cursor.getDate(),
      inMonth: cursor.getMonth() === viewMonth.value,
      isToday: date === todayStr,
      disabled: props.disabledWeekdays.includes(DAY_NAMES[cursor.getDay()]),
    })
    cursor.setDate(cursor.getDate() + 1)
  }
  // Drop a trailing all-out-of-month week
  return cells.slice(35).every(c => !c.inMonth) ? cells.slice(0, 35) : cells
})

function syncViewToValue() {
  if (!props.modelValue) return
  const [y, m] = props.modelValue.split('-').map(Number)
  viewYear.value = y
  viewMonth.value = m - 1
}

watch(() => props.modelValue, syncViewToValue)

async function toggle() {
  if (open.value) {
    close()
    return
  }
  syncViewToValue()
  open.value = true
  await nextTick()
  positionPanel()
  window.addEventListener('mousedown', onWindowMousedown, true)
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('scroll', close, true)
  window.addEventListener('resize', close)
}

function positionPanel() {
  const rect = triggerEl.value?.getBoundingClientRect()
  const panel = panelEl.value?.getBoundingClientRect()
  if (!rect || !panel) return
  const below = rect.bottom + 4
  const top = below + panel.height > window.innerHeight
    ? Math.max(8, rect.top - panel.height - 4)
    : below
  const left = Math.min(rect.left, window.innerWidth - panel.width - 8)
  panelStyle.value = { top: `${top}px`, left: `${Math.max(8, left)}px` }
}

function close() {
  open.value = false
  window.removeEventListener('mousedown', onWindowMousedown, true)
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('scroll', close, true)
  window.removeEventListener('resize', close)
}

function onWindowMousedown(e) {
  if (panelEl.value?.contains(e.target) || triggerEl.value?.contains(e.target)) return
  close()
}

function onKeydown(e) {
  if (e.key === 'Escape') close()
}

function shiftMonth(delta) {
  const d = new Date(viewYear.value, viewMonth.value + delta, 1)
  viewYear.value = d.getFullYear()
  viewMonth.value = d.getMonth()
}

function pick(cell) {
  if (cell.disabled) return
  emit('update:modelValue', cell.date)
  emit('change')
  close()
}

function clear() {
  emit('update:modelValue', '')
  emit('change')
}

onBeforeUnmount(close)
</script>
