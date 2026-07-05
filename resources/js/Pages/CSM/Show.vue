<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
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
  if (val >= 4) return 'bg-success-50 text-success-700'
  if (val === 3) return 'bg-warning-50 text-warning-700'
  return 'bg-danger-50 text-danger-600'
}

function sqdBar(val) {
  if (!val || val === 6) return { width: '0%', color: 'bg-slate-200' }
  const pct = ((val / 5) * 100).toFixed(0) + '%'
  const color = val >= 4 ? 'bg-success-600' : val === 3 ? 'bg-warning-500' : 'bg-danger-600'
  return { width: pct, color }
}

function adjectivalBadgeColor(adj) {
  const map = {
    'Excellent': 'green', 'Very Good': 'blue',
    'Satisfactory': 'purple', 'Fair': 'amber', 'Poor': 'red',
  }
  return map[adj] ?? 'slate'
}

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
        <AppIconButton label="Back" variant="secondary" @click="router.visit(route('csm.list'))">
          <ArrowLeftIcon class="h-4 w-4" />
        </AppIconButton>
        <div>
          <h1 class="text-lg font-bold text-slate-800">CSM Response #{{ response.id }}</h1>
          <p class="text-xs text-slate-500">{{ fmtDate(response.created_at) }} · {{ moduleLabel }}</p>
        </div>
        <AppBadge class="ml-auto !px-3 !py-1 !text-sm" :color="adjectivalBadgeColor(adjectival)">
          {{ adjectival }} · {{ avgSqd?.toFixed(2) ?? '—' }} / 5
        </AppBadge>
      </div>

      <!-- Related request -->
      <div v-if="related" class="bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3 text-sm">
        <span class="font-semibold text-indigo-700">{{ related.module }}</span>
        <span class="text-indigo-500 mx-2">·</span>
        <span class="text-slate-700">{{ related.title }}</span>
        <span v-if="related.status" class="ml-2 text-xs text-slate-400">({{ related.status }})</span>
      </div>

      <!-- Respondent info -->
      <AppCard title="Client Information">
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
      </AppCard>

      <!-- CC Questions -->
      <AppCard title="Citizen's Charter Awareness">
        <div class="space-y-2 text-sm">
          <div><span class="text-xs text-slate-400 font-medium">CC1: </span><span class="text-slate-700">{{ cc1Labels[response.cc1] ?? '—' }}</span></div>
          <div><span class="text-xs text-slate-400 font-medium">CC2: </span><span class="text-slate-700">{{ cc2Labels[response.cc2] ?? (response.cc1 == 4 ? 'N/A' : '—') }}</span></div>
          <div><span class="text-xs text-slate-400 font-medium">CC3: </span><span class="text-slate-700">{{ cc3Labels[response.cc3] ?? (response.cc1 == 4 ? 'N/A' : '—') }}</span></div>
        </div>
      </AppCard>

      <!-- SQD Scorecard -->
      <AppCard>
        <template #header>
          <h2 class="text-sm font-semibold text-slate-700">Service Quality Dimensions (SQD)</h2>
          <span class="text-xs text-slate-400">1 = Strongly Disagree · 5 = Strongly Agree · 6 = N/A</span>
        </template>
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
          <span :class="['text-base font-bold', avgSqd >= 4 ? 'text-success-700' : avgSqd >= 3 ? 'text-warning-600' : 'text-danger-600']">
            {{ avgSqd?.toFixed(2) ?? '—' }} / 5 — {{ adjectival }}
          </span>
        </div>
      </AppCard>

      <!-- Suggestions -->
      <AppCard v-if="response.suggestions" title="Suggestions">
        <p class="text-sm text-slate-700 leading-relaxed">{{ response.suggestions }}</p>
      </AppCard>

    </div>
  </AdminLayout>
</template>
