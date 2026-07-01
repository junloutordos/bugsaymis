<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import {
  ClockIcon,
  ExclamationTriangleIcon,
  CircleStackIcon,
  QueueListIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  enabled: { type: Boolean, default: false },
  windowHours: { type: Number, default: 24 },
  slowRequests: { type: Array, default: () => [] },
  exceptions: { type: Array, default: () => [] },
  slowQueries: { type: Array, default: () => [] },
  queueThroughput: { type: Object, default: () => ({}) },
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
    </div>
  </AdminLayout>
</template>
