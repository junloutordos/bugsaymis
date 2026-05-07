<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  response:    Object,
  related:     { type: Object, default: null },
  avgSqd:      { type: Number, default: null },
  adjectival:  String,
  moduleLabel: String,
})

const sqdItems = [
  { key: 'sqd0', label: 'I am satisfied with the services that I availed.' },
  { key: 'sqd1', label: 'I spent reasonable amount of time for my transaction.' },
  { key: 'sqd2', label: "The office followed the transaction's requirements and steps based on the information provided." },
  { key: 'sqd3', label: 'The steps (including payment) I needed to do for my transaction were easy and simple.' },
  { key: 'sqd4', label: "I easily found information about my transaction from the office's website." },
  { key: 'sqd5', label: 'I paid a reasonable amount of fees for my transaction. (If service was free, mark the "N/A" column)' },
  { key: 'sqd6', label: 'I am confident my transaction was secure.' },
  { key: 'sqd7', label: "The office's support was available, and (if asked questions) support was quick to respond." },
  { key: 'sqd8', label: 'I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me.' },
]

const sqdLabels = { 1: 'Strongly Disagree', 2: 'Disagree', 3: 'Neither', 4: 'Agree', 5: 'Strongly Agree', 6: 'N/A' }

function sqdColor(val) {
  if (!val || val === 6) return 'bg-slate-100 text-slate-500'
  if (val >= 4) return 'bg-emerald-100 text-emerald-700'
  if (val === 3) return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-600'
}

function sqdBar(val) {
  if (!val || val === 6) return { width: '0%', color: 'bg-slate-200' }
  const pct = ((val / 5) * 100).toFixed(0) + '%'
  const color = val >= 4 ? 'bg-emerald-500' : val === 3 ? 'bg-amber-400' : 'bg-red-400'
  return { width: pct, color }
}

const adjectivalColor = computed(() => {
  const map = {
    'Excellent': 'bg-emerald-100 text-emerald-700', 'Very Good': 'bg-blue-100 text-blue-700',
    'Satisfactory': 'bg-violet-100 text-violet-700', 'Fair': 'bg-amber-100 text-amber-700', 'Poor': 'bg-red-100 text-red-600',
  }
  return map[props.adjectival] ?? 'bg-slate-100 text-slate-600'
})

const cc1Labels = {
  1: '1. I know what a CC is and I saw this office\'s CC.',
  2: '2. I know what a CC but I did NOT see this office\'s CC.',
  3: '3. I learned of the CC only when I saw this office\'s CC.',
  4: '4. I do not know what a CC is and I did not see one in this office.',
}
const cc2Labels = { 1: '1. Easy to see', 2: '2. Somewhat easy to see', 3: '3. Difficult to see', 4: '4. Not visible at all', 5: '5. N/A' }
const cc3Labels = { 1: '1. Helped very much', 2: '2. Somewhat helped', 3: '3. Did not help', 4: '4. N/A' }

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })
}

const serviceAvailed = computed(() => {
  const s = props.response.service_availed
  if (!s) return []
  return Array.isArray(s) ? s : [s]
})
</script>

<template>
  <Head :title="`CSM Response #${response.id}`" />
  <AdminLayout :title="`CSM Response #${response.id}`">
    <div class="space-y-5 max-w-3xl">

      <!-- Back + header -->
      <div class="flex items-center gap-3">
        <button @click="router.visit(route('csm.list'))"
          class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500">
          <ArrowLeftIcon class="h-4 w-4" />
        </button>
        <div>
          <h1 class="text-lg font-bold text-slate-800">CSM Response #{{ response.id }}</h1>
          <p class="text-xs text-slate-500">{{ fmtDate(response.created_at) }} · {{ moduleLabel }}</p>
        </div>
        <span :class="['ml-auto inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold', adjectivalColor]">
          {{ adjectival }} · {{ avgSqd?.toFixed(2) ?? '—' }} / 5
        </span>
      </div>

      <!-- Related request -->
      <div v-if="related" class="bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3 text-sm">
        <span class="font-semibold text-indigo-700">{{ related.module }}</span>
        <span class="text-indigo-500 mx-2">·</span>
        <span class="text-slate-700">{{ related.title }}</span>
        <span v-if="related.status" class="ml-2 text-xs text-slate-400">({{ related.status }})</span>
      </div>

      <!-- Respondent info -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Client Information</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
          <div><p class="text-xs text-slate-400">Respondent</p><p class="font-medium text-slate-800">{{ response.user?.name ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-400">Client Type</p><p class="text-slate-700 capitalize">{{ response.client_type }}</p></div>
          <div><p class="text-xs text-slate-400">Sex</p><p class="text-slate-700 uppercase">{{ response.sex ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-400">Age</p><p class="text-slate-700">{{ response.age ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-400">Region</p><p class="text-slate-700">{{ response.region_of_residence ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-400">Date of Transaction</p><p class="text-slate-700">{{ fmtDate(response.date_of_transaction) }}</p></div>
          <div class="sm:col-span-2"><p class="text-xs text-slate-400">Office Availed</p><p class="text-slate-700">{{ response.office_availed }}</p></div>
          <div>
            <p class="text-xs text-slate-400">Service(s) Availed</p>
            <p class="text-slate-700 text-xs">{{ serviceAvailed.join(', ') || '—' }}</p>
            <p v-if="response.service_availed_other" class="text-slate-500 text-xs">Others: {{ response.service_availed_other }}</p>
          </div>
        </div>
      </div>

      <!-- CC Questions -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Citizen's Charter Awareness</h2>
        <div class="space-y-2 text-sm">
          <div><span class="text-xs text-slate-400 font-medium">CC1: </span><span class="text-slate-700">{{ cc1Labels[response.cc1] ?? '—' }}</span></div>
          <div><span class="text-xs text-slate-400 font-medium">CC2: </span><span class="text-slate-700">{{ cc2Labels[response.cc2] ?? (response.cc1 == 4 ? 'N/A' : '—') }}</span></div>
          <div><span class="text-xs text-slate-400 font-medium">CC3: </span><span class="text-slate-700">{{ cc3Labels[response.cc3] ?? (response.cc1 == 4 ? 'N/A' : '—') }}</span></div>
        </div>
      </div>

      <!-- SQD Scorecard -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-slate-700">Service Quality Dimensions (SQD)</h2>
          <span class="text-xs text-slate-400">1 = Strongly Disagree · 5 = Strongly Agree · 6 = N/A</span>
        </div>
        <div class="space-y-3">
          <div v-for="item in sqdItems" :key="item.key" class="flex items-start gap-3">
            <span :class="['shrink-0 inline-flex items-center justify-center h-7 w-7 rounded-full text-xs font-bold mt-0.5', sqdColor(response[item.key])]">
              {{ response[item.key] ?? '—' }}
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-xs text-slate-600 leading-snug">{{ item.label }}</p>
              <div class="mt-1 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                <div :class="['h-full rounded-full transition-all', sqdBar(response[item.key]).color]"
                  :style="{ width: sqdBar(response[item.key]).width }"></div>
              </div>
            </div>
            <span class="shrink-0 text-[10px] text-slate-400 w-24 text-right leading-tight mt-1">
              {{ sqdLabels[response[item.key]] ?? '' }}
            </span>
          </div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
          <span class="text-xs text-slate-500">Overall Average (N/A excluded)</span>
          <span :class="['text-base font-bold', avgSqd >= 4 ? 'text-emerald-600' : avgSqd >= 3 ? 'text-amber-600' : 'text-red-500']">
            {{ avgSqd?.toFixed(2) ?? '—' }} / 5 — {{ adjectival }}
          </span>
        </div>
      </div>

      <!-- Suggestions -->
      <div v-if="response.suggestions" class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-2">Suggestions</h2>
        <p class="text-sm text-slate-700 leading-relaxed">{{ response.suggestions }}</p>
      </div>

    </div>
  </AdminLayout>
</template>
