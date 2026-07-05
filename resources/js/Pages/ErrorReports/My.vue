<template>
  <Head title="My Error Reports" />
  <AdminLayout title="My Error Reports">
    <div class="space-y-5">

      <AppPageHeader title="My Error Reports" subtitle="Track the status of errors you have reported" />

      <!-- Table -->
      <AppTable :is-empty="reports.length === 0" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Report</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Priority</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Submitted</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <template v-for="r in reports" :key="r.id">
          <tr class="hover:bg-slate-50/50 cursor-pointer" @click="toggle(r.id)">
            <td class="px-4 py-3">
              <p class="font-mono text-xs text-slate-400">{{ r.report_no }}</p>
              <p class="font-medium text-slate-800 truncate max-w-xs">{{ r.title }}</p>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="statusColor(r.status)">{{ statusLabel(r.status) }}</AppBadge>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="priorityColor(r.priority)">{{ r.priority.toUpperCase() }}</AppBadge>
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

                <div v-if="r.action_taken" class="rounded-lg bg-success-50 border border-success-100 px-4 py-3">
                  <p class="text-xs font-semibold text-success-700 uppercase tracking-wide mb-1">MIS Action Taken</p>
                  <p class="text-sm text-success-700 whitespace-pre-wrap">{{ r.action_taken }}</p>
                </div>

                <div v-if="r.resolved_at" class="text-xs text-success-600 flex items-center gap-1">
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

        <template #mobileCard>
          <div v-for="r in reports" :key="r.id" class="p-4 space-y-2" @click="toggle(r.id)">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-mono text-xs text-slate-400">{{ r.report_no }}</p>
                <p class="font-medium text-slate-800 truncate">{{ r.title }}</p>
              </div>
              <ChevronDownIcon class="h-4 w-4 text-slate-400 shrink-0 transition-transform"
                :class="expanded === r.id ? 'rotate-180' : ''" />
            </div>
            <div class="flex items-center gap-2">
              <AppBadge :color="statusColor(r.status)">{{ statusLabel(r.status) }}</AppBadge>
              <AppBadge :color="priorityColor(r.priority)">{{ r.priority.toUpperCase() }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ formatDate(r.created_at) }}</p>

            <div v-if="expanded === r.id" class="pt-2 space-y-3 border-t border-slate-100">
              <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Description</p>
                <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ r.description }}</p>
              </div>

              <div v-if="r.action_taken" class="rounded-lg bg-success-50 border border-success-100 px-4 py-3">
                <p class="text-xs font-semibold text-success-700 uppercase tracking-wide mb-1">MIS Action Taken</p>
                <p class="text-sm text-success-700 whitespace-pre-wrap">{{ r.action_taken }}</p>
              </div>

              <div v-if="r.resolved_at" class="text-xs text-success-600 flex items-center gap-1">
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
          </div>
        </template>

        <template #empty>
          <EmptyState
            :icon="BugAntIcon"
            title="You haven't reported any errors yet"
            subtitle='Use the &quot;Report an Error&quot; button in the top navigation bar.'
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { BugAntIcon, CheckCircleIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'

defineProps({ reports: { type: Array, default: () => [] } })

const expanded = ref(null)
function toggle(id) {
  expanded.value = expanded.value === id ? null : id
}

function statusLabel(s) {
  return { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved' }[s] ?? s
}

function statusColor(s) {
  return {
    open:        'red',
    in_progress: 'amber',
    resolved:    'green',
  }[s] ?? 'slate'
}

function priorityColor(p) {
  return {
    critical: 'red',
    high:     'orange',
    medium:   'amber',
    low:      'slate',
  }[p] ?? 'slate'
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
