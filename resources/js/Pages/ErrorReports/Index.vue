<template>
  <Head title="Error Reports" />
  <AdminLayout title="Error Reports">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Error Reports</h1>
          <p class="text-sm text-slate-500 mt-0.5">Review and resolve user-reported system errors</p>
        </div>
      </div>

      <!-- Stat cards -->
      <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 flex items-center gap-3">
          <div class="h-9 w-9 rounded-full bg-red-50 flex items-center justify-center shrink-0">
            <BugAntIcon class="h-5 w-5 text-red-500" />
          </div>
          <div>
            <p class="text-2xl font-bold text-red-600">{{ counts.open }}</p>
            <p class="text-xs text-slate-500">Open</p>
          </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 flex items-center gap-3">
          <div class="h-9 w-9 rounded-full bg-amber-50 flex items-center justify-center shrink-0">
            <ArrowPathIcon class="h-5 w-5 text-amber-500" />
          </div>
          <div>
            <p class="text-2xl font-bold text-amber-600">{{ counts.in_progress }}</p>
            <p class="text-xs text-slate-500">In Progress</p>
          </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 flex items-center gap-3">
          <div class="h-9 w-9 rounded-full bg-emerald-50 flex items-center justify-center shrink-0">
            <CheckCircleIcon class="h-5 w-5 text-emerald-500" />
          </div>
          <div>
            <p class="text-2xl font-bold text-emerald-600">{{ counts.resolved }}</p>
            <p class="text-xs text-slate-500">Resolved</p>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
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
        <button @click="applyFilters"
          class="px-3 py-1.5 text-sm bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg font-medium">
          Filter
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Empty -->
      <div v-if="reports.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <BugAntIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No reports found</p>
      </div>

      <!-- Reports list -->
      <div v-else class="space-y-3">
        <div v-for="r in reports" :key="r.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">

          <!-- Report header row -->
          <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-start gap-3 cursor-pointer"
            @click="toggle(r.id)">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-xs font-semibold text-slate-400">{{ r.report_no }}</span>
                <span :class="priorityBadge(r.priority)"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold">
                  {{ r.priority.toUpperCase() }}
                </span>
                <span :class="statusBadge(r.status)"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize">
                  {{ statusLabel(r.status) }}
                </span>
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
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Description</p>
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
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Screenshot</p>
                <img :src="r.screenshot_url" alt="Error screenshot"
                  class="rounded-lg border border-slate-200 max-h-72 object-contain bg-slate-50 w-full" />
              </div>
            </div>

            <!-- MIS action panel -->
            <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 space-y-3">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">MIS Response</p>

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

              <div v-if="r.resolved_at" class="text-xs text-emerald-600 flex items-center gap-1">
                <CheckCircleIcon class="h-3.5 w-3.5" /> Resolved on {{ formatDate(r.resolved_at) }}
              </div>

              <div class="flex justify-end">
                <button @click="saveEdit(r)"
                  class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium">
                  Save Changes
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
