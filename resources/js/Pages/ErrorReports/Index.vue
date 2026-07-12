<template>
  <Head title="Error Reports" />
  <AdminLayout title="Error Reports">
    <div class="space-y-5">

      <AppPageHeader title="Error Reports" subtitle="Review and resolve user-reported system errors" />

      <!-- Stat cards -->
      <div class="grid grid-cols-3 gap-3">
        <AppCard :padded="false">
          <div class="px-4 py-3 flex items-center gap-3">
            <div class="h-9 w-9 rounded-full bg-danger-50 flex items-center justify-center shrink-0">
              <BugAntIcon class="h-5 w-5 text-danger-500" />
            </div>
            <div>
              <p class="text-2xl font-bold text-danger-600">{{ counts.open }}</p>
              <p class="text-xs text-slate-500">Open</p>
            </div>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-4 py-3 flex items-center gap-3">
            <div class="h-9 w-9 rounded-full bg-warning-50 flex items-center justify-center shrink-0">
              <ArrowPathIcon class="h-5 w-5 text-warning-500" />
            </div>
            <div>
              <p class="text-2xl font-bold text-warning-600">{{ counts.in_progress }}</p>
              <p class="text-xs text-slate-500">In Progress</p>
            </div>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-4 py-3 flex items-center gap-3">
            <div class="h-9 w-9 rounded-full bg-success-50 flex items-center justify-center shrink-0">
              <CheckCircleIcon class="h-5 w-5 text-success-500" />
            </div>
            <div>
              <p class="text-2xl font-bold text-success-600">{{ counts.resolved }}</p>
              <p class="text-xs text-slate-500">Resolved</p>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="filters.status" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
        </select>
        <select v-model="filters.priority" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Priorities</option>
          <option value="critical">Critical</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
        <input v-model="filters.search" type="search" placeholder="Search reports…" @keyup.enter="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 w-52 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />

        <template #actions>
          <AppButton size="sm" @click="applyFilters">Filter</AppButton>
        </template>
      </AppFilterBar>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Empty -->
      <AppCard v-if="reports.length === 0">
        <EmptyState title="No reports found" :icon="BugAntIcon" />
      </AppCard>

      <!-- Reports list -->
      <div v-else class="space-y-3">
        <AppCard v-for="r in reports" :key="r.id" :padded="false" class="overflow-hidden">

          <!-- Report header row -->
          <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-start gap-3 cursor-pointer"
            @click="toggle(r.id)">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-xs font-semibold text-slate-400">{{ r.report_no }}</span>
                <AppBadge :color="priorityBadge(r.priority)">{{ r.priority.toUpperCase() }}</AppBadge>
                <AppBadge :color="statusBadge(r.status)">{{ statusLabel(r.status) }}</AppBadge>
                <CameraIcon v-if="r.has_screenshot" class="h-3.5 w-3.5 text-slate-400" title="Has screenshot" />
              </div>
              <p class="text-sm font-semibold text-slate-800 mt-1 truncate">{{ r.title }}</p>
              <p class="text-xs text-slate-500 mt-0.5">
                {{ r.reporter?.name ?? '—' }}
                <span v-if="r.reporter?.position" class="text-slate-400"> · {{ r.reporter.position }}</span>
                · {{ formatDate(r.created_at) }}
              </p>
            </div>
            <ChevronDownIcon class="h-4 w-4 text-slate-400 shrink-0 mt-1 transition-transform"
              :class="expanded === r.id ? 'rotate-180' : ''" />
          </div>

          <!-- Expanded detail + edit panel -->
          <div v-if="expanded === r.id" class="border-t border-slate-100">

            <!-- Description + screenshot -->
            <div class="px-5 py-4 space-y-3">
              <div>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Description</p>
                <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ r.description }}</p>
              </div>
              <div v-if="r.page_url" class="text-xs text-slate-400">
                <span class="font-semibold text-slate-500">Page:</span>
                <a :href="r.page_url" target="_blank" class="text-indigo-500 hover:underline ml-1 break-all">{{ r.page_url }}</a>
              </div>
              <div v-if="r.browser_info" class="text-xs text-slate-400">
                <span class="font-semibold text-slate-500">Browser:</span>
                {{ r.browser_info.browser }} on {{ r.browser_info.os }}
                <span v-if="r.browser_info.viewport" class="ml-1">({{ r.browser_info.viewport }})</span>
              </div>
              <div v-if="r.has_screenshot">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Screenshot</p>
                <img :src="r.screenshot_url" alt="Error screenshot"
                  class="rounded-lg border border-slate-200 max-h-72 object-contain bg-slate-50 w-full" />
              </div>
            </div>

            <!-- MIS action panel -->
            <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 space-y-3">
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">MIS Response</p>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                  <select v-model="edits[r.id].status"
                    class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Priority</label>
                  <select v-model="edits[r.id].priority"
                    class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Assigned To</label>
                  <select v-model="edits[r.id].assigned_to"
                    class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option :value="null">Unassigned</option>
                    <option v-for="m in misList" :key="m.id" :value="m.id">{{ m.name }}</option>
                  </select>
                </div>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Action Taken</label>
                <textarea v-model="edits[r.id].action_taken" rows="3"
                  placeholder="Describe what was done to fix this issue…"
                  class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"></textarea>
              </div>

              <div v-if="r.resolved_at" class="text-xs text-success-600 flex items-center gap-1">
                <CheckCircleIcon class="h-3.5 w-3.5" /> Resolved on {{ formatDate(r.resolved_at) }}
              </div>

              <div class="flex justify-end">
                <AppButton @click="saveEdit(r)">Save Changes</AppButton>
              </div>
            </div>

          </div>
        </AppCard>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  BugAntIcon, CheckCircleIcon, ArrowPathIcon, ChevronDownIcon,
  CameraIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  reports:  { type: Array,  default: () => [] },
  misList:  { type: Array,  default: () => [] },
  filters:  { type: Object, default: () => ({}) },
  counts:   { type: Object, default: () => ({}) },
})

const filters = reactive({
  status:   props.filters.status   ?? '',
  priority: props.filters.priority ?? '',
  search:   props.filters.search   ?? '',
})

function applyFilters() {
  router.get(route('error-reports.index'), filters, { preserveState: true })
}

// Expanded row
const expanded = ref(null)
function toggle(id) {
  expanded.value = expanded.value === id ? null : id
}

// Per-report edit state (initialized from props)
const edits = reactive({})
props.reports.forEach(r => {
  edits[r.id] = {
    status:       r.status,
    priority:     r.priority,
    assigned_to:  r.assigned_to ?? null,
    action_taken: r.action_taken ?? '',
  }
})

function saveEdit(r) {
  useForm(edits[r.id]).put(route('error-reports.update', r.id), {
    preserveScroll: true,
    onSuccess: () => { expanded.value = null },
  })
}

function statusLabel(s) {
  return { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved' }[s] ?? s
}

function statusBadge(s) {
  return {
    open:        'red',
    in_progress: 'amber',
    resolved:    'green',
  }[s] ?? 'slate'
}

function priorityBadge(p) {
  return {
    critical: 'red',
    high:     'red',
    medium:   'amber',
    low:      'slate',
  }[p] ?? 'slate'
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
