<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, BarElement, CategoryScale, LinearScale } from 'chart.js'

ChartJS.register(Title, Tooltip, BarElement, CategoryScale, LinearScale)

const stats = ref(null)

onMounted(async () => {
  const { data } = await axios.get(route('sos.stats'))
  stats.value = data
})

const monthlyChartData = computed(() => {
  if (!stats.value) return { labels: [], datasets: [] }
  return {
    labels: Object.keys(stats.value.by_month),
    datasets: [{ label: 'Alerts', backgroundColor: '#4f46e5', data: Object.values(stats.value.by_month) }],
  }
})
</script>

<template>
  <div v-if="!stats" class="text-sm text-slate-400">Loading stats…</div>
  <div v-else class="space-y-6">
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div v-for="(count, type) in stats.by_type" :key="type" class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ type.replace('_', ' ') }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ count }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500">Avg. time to first claim</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.avg_first_claim_minutes ?? '—' }}<span v-if="stats.avg_first_claim_minutes" class="text-sm font-normal"> min</span></p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500">Avg. time to resolution</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.avg_resolution_minutes ?? '—' }}<span v-if="stats.avg_resolution_minutes" class="text-sm font-normal"> min</span></p>
      </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4">
      <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Alerts by month</h4>
      <Bar :data="monthlyChartData" :options="{ responsive: true, plugins: { legend: { display: false } } }" />
    </div>
  </div>
</template>
