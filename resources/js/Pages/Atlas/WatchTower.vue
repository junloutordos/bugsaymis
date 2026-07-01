<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import {
  ClockIcon,
  ExclamationTriangleIcon,
  CircleStackIcon,
  QueueListIcon,
  ServerStackIcon,
  CpuChipIcon,
  ChartBarIcon,
  ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline'
import {
  Chart as ChartJS,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
} from 'chart.js'
import { Line } from 'vue-chartjs'

ChartJS.register(LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend)

const props = defineProps({
  enabled: { type: Boolean, default: false },
  windowHours: { type: Number, default: 24 },
  slowRequests: { type: Array, default: () => [] },
  exceptions: { type: Array, default: () => [] },
  slowQueries: { type: Array, default: () => [] },
  queueThroughput: { type: Object, default: () => ({}) },
  infra: { type: Object, default: () => ({}) },
})

function formatTime(unixSeconds) {
  return new Date(unixSeconds * 1000).toLocaleString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: 'numeric', minute: '2-digit', second: '2-digit',
  })
}

function durationBadge(ms) {
  if (ms >= 2000) return 'red'
  if (ms >= 1000) return 'amber'
  return 'slate'
}

function hourLabel(unixSeconds) {
  return new Date(unixSeconds * 1000).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })
}

function toChartData(points, label, color) {
  const list = points ?? []
  return {
    labels: list.map((p) => hourLabel(p.timestamp)),
    datasets: [{
      label,
      data: list.map((p) => p.value),
      borderColor: color,
      backgroundColor: color,
      tension: 0.3,
      pointRadius: 0,
      borderWidth: 2,
    }],
  }
}

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
  scales: {
    x: { ticks: { maxTicksLimit: 6, font: { size: 9 } } },
    y: { ticks: { font: { size: 9 } } },
  },
}

function latest(points) {
  const list = points ?? []
  return list.length ? list[list.length - 1].value : null
}

const alb = computed(() => props.infra?.alb ?? null)
const ecsWeb = computed(() => props.infra?.ecsWeb ?? null)
const ecsWorker = computed(() => props.infra?.ecsWorker ?? null)
const rds = computed(() => props.infra?.rds ?? null)
const traces = computed(() => props.infra?.traces ?? null)
</script>

<template>
  <Head title="Atlas WatchTower" />
  <AdminLayout title="Atlas WatchTower">
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Atlas WatchTower</h1>
          <p class="text-sm text-slate-500">
            App telemetry — last {{ windowHours }} hours. Powered by Laravel Pulse.
          </p>
        </div>
        <AppBadge :color="enabled ? 'green' : 'slate'">
          {{ enabled ? 'Recording' : 'Disabled' }}
        </AppBadge>
      </div>

      <div v-if="!enabled" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Atlas WatchTower is currently disabled (<code>PULSE_ENABLED=false</code>). No new telemetry is being recorded.
      </div>

      <!-- Queue throughput -->
      <AppCard>
        <template #header>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <QueueListIcon class="h-4 w-4" /> Queue Throughput
          </div>
        </template>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 text-center">
          <div v-for="key in ['queued', 'processing', 'processed', 'released', 'failed']" :key="key">
            <div class="text-2xl font-semibold" :class="key === 'failed' ? 'text-red-600' : 'text-slate-800'">
              {{ queueThroughput[key] ?? 0 }}
            </div>
            <div class="text-xs text-slate-500 capitalize">{{ key }}</div>
          </div>
        </div>
      </AppCard>

      <!-- Slow Requests -->
      <AppCard>
        <template #header>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <ClockIcon class="h-4 w-4" /> Slow Requests
          </div>
        </template>
        <div v-if="!slowRequests.length" class="text-sm text-slate-400 py-4 text-center">No slow requests recorded.</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-slate-500 uppercase tracking-wide border-b border-slate-100">
              <th class="py-2">Method</th>
              <th class="py-2">Path</th>
              <th class="py-2">Duration</th>
              <th class="py-2">When</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in slowRequests" :key="i" class="border-b border-slate-50">
              <td class="py-2 font-mono text-xs">{{ r.method }}</td>
              <td class="py-2 font-mono text-xs truncate max-w-xs">{{ r.path }}</td>
              <td class="py-2"><AppBadge :color="durationBadge(r.duration_ms)">{{ r.duration_ms }}ms</AppBadge></td>
              <td class="py-2 text-xs text-slate-500">{{ formatTime(r.timestamp) }}</td>
            </tr>
          </tbody>
        </table>
      </AppCard>

      <!-- Exceptions -->
      <AppCard>
        <template #header>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <ExclamationTriangleIcon class="h-4 w-4" /> Exceptions
          </div>
        </template>
        <div v-if="!exceptions.length" class="text-sm text-slate-400 py-4 text-center">No exceptions recorded.</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-slate-500 uppercase tracking-wide border-b border-slate-100">
              <th class="py-2">Class</th>
              <th class="py-2">Location</th>
              <th class="py-2">Last seen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(e, i) in exceptions" :key="i" class="border-b border-slate-50">
              <td class="py-2 font-mono text-xs">{{ e.class }}</td>
              <td class="py-2 font-mono text-xs truncate max-w-xs">{{ e.location }}</td>
              <td class="py-2 text-xs text-slate-500">{{ formatTime(e.timestamp) }}</td>
            </tr>
          </tbody>
        </table>
      </AppCard>

      <!-- Slow Queries -->
      <AppCard>
        <template #header>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <CircleStackIcon class="h-4 w-4" /> Slow Queries
          </div>
        </template>
        <div v-if="!slowQueries.length" class="text-sm text-slate-400 py-4 text-center">No slow queries recorded.</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-slate-500 uppercase tracking-wide border-b border-slate-100">
              <th class="py-2">SQL</th>
              <th class="py-2">Duration</th>
              <th class="py-2">When</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(q, i) in slowQueries" :key="i" class="border-b border-slate-50">
              <td class="py-2 font-mono text-xs truncate max-w-md">{{ q.sql }}</td>
              <td class="py-2"><AppBadge :color="durationBadge(q.duration_ms)">{{ q.duration_ms }}ms</AppBadge></td>
              <td class="py-2 text-xs text-slate-500">{{ formatTime(q.timestamp) }}</td>
            </tr>
          </tbody>
        </table>
      </AppCard>

      <div class="flex items-center justify-between pt-2">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Infrastructure (AWS)</h2>
        <a
          href="https://ap-southeast-1.console.aws.amazon.com/cloudwatch/home?region=ap-southeast-1#dashboards:name=AtlasWatchTower"
          target="_blank"
          class="text-xs text-indigo-600 hover:text-indigo-700 flex items-center gap-1"
        >
          Full CloudWatch dashboard <ArrowTopRightOnSquareIcon class="h-3 w-3" />
        </a>
      </div>

      <!-- Traces (X-Ray) -->
      <AppCard>
        <template #header>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <ChartBarIcon class="h-4 w-4" /> Traces (last {{ traces?.windowMinutes ?? 60 }} min)
          </div>
        </template>
        <div v-if="!traces" class="text-sm text-slate-400 py-4 text-center">No trace data available.</div>
        <div v-else class="grid grid-cols-3 gap-4 text-center">
          <div>
            <div class="text-2xl font-semibold text-slate-800">{{ traces.total }}</div>
            <div class="text-xs text-slate-500">Total traces</div>
          </div>
          <div>
            <div class="text-2xl font-semibold" :class="traces.errors > 0 ? 'text-red-600' : 'text-slate-800'">{{ traces.errors }}</div>
            <div class="text-xs text-slate-500">Errors/faults</div>
          </div>
          <div>
            <div class="text-2xl font-semibold text-slate-800">{{ traces.avgDurationMs }}ms</div>
            <div class="text-xs text-slate-500">Avg duration</div>
          </div>
        </div>
        <a
          href="https://ap-southeast-1.console.aws.amazon.com/xray/home?region=ap-southeast-1#/service-map"
          target="_blank"
          class="mt-3 inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-700"
        >
          View X-Ray service map <ArrowTopRightOnSquareIcon class="h-3 w-3" />
        </a>
      </AppCard>

      <!-- ALB -->
      <AppCard v-if="alb">
        <template #header>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <ServerStackIcon class="h-4 w-4" /> Load Balancer (24h)
          </div>
        </template>
        <div class="grid grid-cols-3 gap-4 text-center mb-4">
          <div>
            <div class="text-lg font-semibold text-slate-800">{{ latest(alb.requests) ?? '—' }}</div>
            <div class="text-xs text-slate-500">Requests (last hr)</div>
          </div>
          <div>
            <div class="text-lg font-semibold" :class="(latest(alb.errors5xx) ?? 0) > 0 ? 'text-red-600' : 'text-slate-800'">{{ latest(alb.errors5xx) ?? '—' }}</div>
            <div class="text-xs text-slate-500">5xx (last hr)</div>
          </div>
          <div>
            <div class="text-lg font-semibold text-slate-800">{{ latest(alb.latencyP99) ?? '—' }}s</div>
            <div class="text-xs text-slate-500">p99 latency</div>
          </div>
        </div>
        <div class="h-40">
          <Line :data="toChartData(alb.requests, 'Requests', '#6366f1')" :options="chartOptions" />
        </div>
      </AppCard>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- ECS Web -->
        <AppCard v-if="ecsWeb">
          <template #header>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
              <CpuChipIcon class="h-4 w-4" /> ECS — Web service (24h)
            </div>
          </template>
          <div class="grid grid-cols-2 gap-4 text-center mb-4">
            <div>
              <div class="text-lg font-semibold text-slate-800">{{ latest(ecsWeb.cpu) ?? '—' }}%</div>
              <div class="text-xs text-slate-500">CPU</div>
            </div>
            <div>
              <div class="text-lg font-semibold text-slate-800">{{ latest(ecsWeb.memory) ?? '—' }}%</div>
              <div class="text-xs text-slate-500">Memory</div>
            </div>
          </div>
          <div class="h-32">
            <Line :data="toChartData(ecsWeb.cpu, 'CPU %', '#10b981')" :options="chartOptions" />
          </div>
        </AppCard>

        <!-- ECS Worker -->
        <AppCard v-if="ecsWorker">
          <template #header>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
              <CpuChipIcon class="h-4 w-4" /> ECS — Worker service (24h)
            </div>
          </template>
          <div class="grid grid-cols-2 gap-4 text-center mb-4">
            <div>
              <div class="text-lg font-semibold text-slate-800">{{ latest(ecsWorker.cpu) ?? '—' }}%</div>
              <div class="text-xs text-slate-500">CPU</div>
            </div>
            <div>
              <div class="text-lg font-semibold text-slate-800">{{ latest(ecsWorker.memory) ?? '—' }}%</div>
              <div class="text-xs text-slate-500">Memory</div>
            </div>
          </div>
          <div class="h-32">
            <Line :data="toChartData(ecsWorker.cpu, 'CPU %', '#10b981')" :options="chartOptions" />
          </div>
        </AppCard>
      </div>

      <!-- RDS -->
      <AppCard v-if="rds">
        <template #header>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <CircleStackIcon class="h-4 w-4" /> RDS (24h)
          </div>
        </template>
        <div class="grid grid-cols-4 gap-4 text-center mb-4">
          <div>
            <div class="text-lg font-semibold text-slate-800">{{ latest(rds.cpu) ?? '—' }}%</div>
            <div class="text-xs text-slate-500">CPU</div>
          </div>
          <div>
            <div class="text-lg font-semibold text-slate-800">{{ latest(rds.connections) ?? '—' }}</div>
            <div class="text-xs text-slate-500">Connections</div>
          </div>
          <div>
            <div class="text-lg font-semibold text-slate-800">{{ latest(rds.freeableMemoryMb) ?? '—' }} MB</div>
            <div class="text-xs text-slate-500">Freeable memory</div>
          </div>
          <div>
            <div class="text-lg font-semibold text-slate-800">{{ latest(rds.freeStorageGb) ?? '—' }} GB</div>
            <div class="text-xs text-slate-500">Free storage</div>
          </div>
        </div>
        <div class="h-32">
          <Line :data="toChartData(rds.cpu, 'CPU %', '#f59e0b')" :options="chartOptions" />
        </div>
      </AppCard>

      <div v-if="!alb && !ecsWeb && !ecsWorker && !rds && !traces" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500 text-center">
        Infrastructure metrics are unavailable right now — check the
        <a href="https://ap-southeast-1.console.aws.amazon.com/cloudwatch/home?region=ap-southeast-1#dashboards:name=AtlasWatchTower" target="_blank" class="text-indigo-600 hover:underline">CloudWatch dashboard</a>
        directly.
      </div>
    </div>
  </AdminLayout>
</template>
