<script setup>
import { ref, computed } from 'vue'
import AppModal from '@/Components/AppModal.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { ChevronLeftIcon, ChevronRightIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show:         { type: Boolean, default: false },
  sectionLabel: { type: String, default: '' },
  days:         { type: Array, default: () => [] }, // [{ date, count, items: [...] }]
})

const emit = defineEmits(['close'])

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

function todayParts() {
  const d = new Date()
  return { month: d.getMonth() + 1, year: d.getFullYear() }
}

const calMonth = ref(todayParts().month)
const calYear  = ref(todayParts().year)

const dayMap = computed(() => {
  const map = new Map()
  for (const d of props.days) map.set(d.date, d)
  return map
})

function formatDate(d) {
  const y   = d.getFullYear()
  const m   = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const todayStr = formatDate(new Date())

const calendarDays = computed(() => {
  const first    = new Date(calYear.value, calMonth.value - 1, 1)
  const last     = new Date(calYear.value, calMonth.value, 0)
  const startDow = first.getDay()
  const cells    = []

  for (let i = 0; i < startDow; i++) {
    const d = new Date(calYear.value, calMonth.value - 1, -startDow + i + 1)
    cells.push({ date: formatDate(d), day: d.getDate(), current: false, entry: null })
  }
  for (let d = 1; d <= last.getDate(); d++) {
    const date = formatDate(new Date(calYear.value, calMonth.value - 1, d))
    cells.push({ date, day: d, current: true, entry: dayMap.value.get(date) ?? null })
  }
  const remainder = 42 - cells.length
  for (let i = 1; i <= remainder; i++) {
    const d = new Date(calYear.value, calMonth.value, i)
    cells.push({ date: formatDate(d), day: i, current: false, entry: null })
  }
  return cells
})

function navigateMonth(delta) {
  let m = calMonth.value + delta
  let y = calYear.value
  if (m < 1)  { m = 12; y-- }
  if (m > 12) { m = 1;  y++ }
  calMonth.value = m
  calYear.value  = y
}

const selectedDate  = ref(null)
const selectedEntry = computed(() => selectedDate.value ? dayMap.value.get(selectedDate.value) ?? null : null)

function selectDay(cell) {
  if (!cell.current) return
  selectedDate.value = cell.date
}

function close() {
  selectedDate.value = null
  emit('close')
}
</script>

<template>
  <AppModal :show="show" size="xl" title="Section Assessment Calendar"
    :subtitle="sectionLabel ? `${sectionLabel} — all subjects, current school year` : ''"
    @close="close">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <!-- Calendar (2/3) -->
      <div class="lg:col-span-2">
        <div class="flex items-center justify-between mb-3">
          <button @click="navigateMonth(-1)" class="p-1.5 rounded hover:bg-slate-100">
            <ChevronLeftIcon class="w-5 h-5 text-slate-500" />
          </button>
          <span class="text-sm font-semibold text-slate-700">
            {{ MONTH_NAMES[calMonth - 1] }} {{ calYear }}
          </span>
          <button @click="navigateMonth(1)" class="p-1.5 rounded hover:bg-slate-100">
            <ChevronRightIcon class="w-5 h-5 text-slate-500" />
          </button>
        </div>

        <div class="grid grid-cols-7 mb-1">
          <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"
            class="text-center text-xs font-semibold text-slate-400 py-1">
            {{ d }}
          </div>
        </div>

        <div class="grid grid-cols-7 gap-px bg-slate-100 rounded-lg overflow-hidden">
          <div
            v-for="cell in calendarDays" :key="cell.date"
            :class="[
              'bg-white min-h-[74px] p-1.5 text-xs cursor-pointer hover:bg-indigo-50 transition-colors',
              !cell.current ? 'opacity-30' : '',
              cell.date === todayStr ? 'ring-2 ring-inset ring-indigo-400' : '',
              selectedDate === cell.date ? 'bg-indigo-50' : '',
            ]"
            @click="selectDay(cell)"
          >
            <div class="flex items-center justify-between mb-1">
              <span :class="['font-medium', cell.date === todayStr ? 'text-indigo-600' : 'text-slate-700']">
                {{ cell.day }}
              </span>
              <span v-if="cell.entry"
                :class="['font-bold text-[10px]', cell.entry.count >= 3 ? 'text-red-500' : 'text-slate-400']"
                :title="cell.entry.count >= 3 ? 'Section is at the 3/day maximum' : `${cell.entry.count}/3 scheduled`">
                {{ cell.entry.count }}/3
              </span>
            </div>
            <div v-if="cell.entry" class="space-y-0.5 overflow-hidden">
              <div v-for="item in cell.entry.items.slice(0, 2)" :key="item.id"
                class="rounded px-1 py-0.5 truncate leading-tight text-[11px] bg-red-50 border border-red-100 text-red-700">
                [{{ item.category_code }}] {{ item.title }}
              </div>
              <div v-if="cell.entry.items.length > 2" class="text-slate-400 pl-1">
                +{{ cell.entry.items.length - 2 }} more
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Day detail panel (1/3) -->
      <div class="lg:col-span-1 flex flex-col border border-slate-100 rounded-xl p-3">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">
          {{ selectedDate ?? 'Select a date' }}
        </h3>

        <div v-if="!selectedEntry" class="flex-1 flex flex-col items-center justify-center text-slate-300 py-8">
          <CalendarDaysIcon class="w-10 h-10 mb-2" />
          <span class="text-xs">{{ selectedDate ? 'No assessments scheduled' : 'Click a day to view details' }}</span>
        </div>

        <div v-else class="space-y-2 overflow-y-auto max-h-[320px] pr-1">
          <AppBadge :color="selectedEntry.count >= 3 ? 'red' : 'slate'">
            {{ selectedEntry.count }} / 3 assessments
          </AppBadge>
          <div v-for="item in selectedEntry.items" :key="item.id"
            :class="['rounded-lg p-2.5 border', item.is_own_record ? 'border-indigo-100 bg-indigo-50/60' : 'border-slate-100 bg-slate-50/60']">
            <p class="text-sm font-medium text-slate-800 leading-tight">[{{ item.category_code }}] {{ item.title }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ item.subject_name }}</p>
            <p v-if="item.teacher_name" class="text-xs text-slate-400 italic">{{ item.teacher_name }}</p>
            <p v-if="item.is_own_record" class="text-[11px] text-indigo-500 mt-0.5">This class record</p>
          </div>
        </div>
      </div>
    </div>
  </AppModal>
</template>
