<template>
  <Head title="My Error Reports" />
  <AdminLayout title="My Error Reports">
    <div class="space-y-5">

      <div>
        <h1 class="text-xl font-semibold text-slate-800">My Error Reports</h1>
        <p class="text-sm text-slate-500 mt-0.5">Track the status of errors you have reported</p>
      </div>

      <!-- Empty -->
      <div v-if="reports.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <BugAntIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">You haven't reported any errors yet</p>
        <p class="text-xs text-slate-400 mt-1">Use the "Report an Error" button in the top navigation bar.</p>
      </div>

      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Report</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Priority</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Submitted</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <template v-for="r in reports" :key="r.id">
              <tr class="hover:bg-slate-50/50 cursor-pointer" @click="toggle(r.id)">
                <td class="px-4 py-3">
                  <p class="font-mono text-xs text-slate-400">{{ r.report_no }}</p>
                  <p class="font-medium text-slate-800 truncate max-w-xs">{{ r.title }}</p>
                </td>
                <td class="px-4 py-3">
                  <span :class="statusBadge(r.status)"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                    {{ statusLabel(r.status) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span :class="priorityBadge(r.priority)"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold">
                    {{ r.priority.toUpperCase() }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(r.created_at) }}</td>
                <td class="px-4 py-3 text-right">
                  <ChevronDownIcon class="h-4 w-4 text-slate-400 inline transition-transform"
                    :class="expanded === r.id ? 'rotate-180' : ''" />
                </td>
              </tr>

              <!-- Expanded detail -->
              <tr v-if="expanded === r.id">
                <td colspan="5" class="px-4 py-4 bg-slate-50 border-t border-slate-100">
                  <div class="space-y-3 max-w-2xl">
                    <div>
                      <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Description</p>
                      <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ r.description }}</p>
                    </div>

                    <div v-if="r.action_taken" class="rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3">
                      <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide mb-1">MIS Action Taken</p>
                      <p class="text-sm text-emerald-800 whitespace-pre-wrap">{{ r.action_taken }}</p>
                    </div>

                    <div v-if="r.resolved_at" class="text-xs text-emerald-600 flex items-center gap-1">
                      <CheckCircleIcon class="h-3.5 w-3.5" /> Resolved on {{ formatDate(r.resolved_at) }}
                    </div>

                    <div v-if="r.assignee_name" class="text-xs text-slate-500">
                      Assigned to: <span class="font-medium text-slate-700">{{ r.assignee_name }}</span>
                    </div>

                    <div v-if="r.has_screenshot">
                      <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Your Screenshot</p>
                      <img :src="r.screenshot_url" alt="Screenshot"
                        class="rounded-lg border border-slate-200 max-h-64 object-contain bg-slate-50" />
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { BugAntIcon, CheckCircleIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'

defineProps({ reports: { type: Array, default: () => [] } })

const expanded = ref(null)
function toggle(id) {
  expanded.value = expanded.value === id ? null : id
}

function statusLabel(s) {
  return { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved' }[s] ?? s
}

function statusBadge(s) {
  return {
    open:        'bg-red-50 text-red-600',
    in_progress: 'bg-amber-50 text-amber-700',
    resolved:    'bg-emerald-50 text-emerald-700',
  }[s] ?? 'bg-slate-100 text-slate-500'
}

function priorityBadge(p) {
  return {
    critical: 'bg-red-600 text-white',
    high:     'bg-red-100 text-red-700',
    medium:   'bg-amber-100 text-amber-700',
    low:      'bg-slate-100 text-slate-500',
  }[p] ?? 'bg-slate-100 text-slate-500'
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
