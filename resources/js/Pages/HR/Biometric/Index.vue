<template>
  <Head title="Biometric Logs" />
  <AdminLayout title="Biometric Logs">
    <div class="space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Biometric Logs</h1>
          <p class="text-sm text-slate-500 mt-0.5">Import and resolve biometric punch records.</p>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" />
        {{ $page.props.flash.error }}
      </div>

      <!-- Upload + Stats Row -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Upload Panel -->
        <div class="lg:col-span-1 bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
            <ArrowUpTrayIcon class="h-4 w-4 text-indigo-500" />
            Upload Biometric File
          </h2>
          <form @submit.prevent="submitUpload" enctype="multipart/form-data" class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Files (.dat or .txt)</label>
              <input
                type="file"
                multiple
                accept=".dat,.txt,.log,.DAT"
                @change="onFilePick"
                class="block w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-slate-200 file:text-xs file:font-medium file:bg-slate-50 file:text-slate-700 hover:file:bg-slate-100 cursor-pointer"
              />
              <p v-if="uploadForm.errors['files.0']" class="text-red-500 text-xs mt-1">{{ uploadForm.errors['files.0'] }}</p>
              <p v-if="uploadForm.errors.files" class="text-red-500 text-xs mt-1">{{ uploadForm.errors.files }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Device ID <span class="font-normal text-slate-400">(optional)</span></label>
              <input
                v-model="uploadForm.device_id"
                type="text"
                placeholder="e.g. TERMINAL-01"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400"
              />
            </div>
            <button
              type="submit"
              :disabled="uploadForm.processing || !uploadForm.files.length"
              class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              <svg v-if="uploadForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              <span>{{ uploadForm.processing ? 'Importing…' : 'Import Now' }}</span>
            </button>
          </form>
        </div>

        <!-- Stats Cards -->
        <div class="lg:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Total</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total.toLocaleString() }}</p>
          </div>
          <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-4">
            <p class="text-xs text-emerald-600 font-medium uppercase tracking-wide">Resolved</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ stats.resolved.toLocaleString() }}</p>
          </div>
          <div class="bg-amber-50 rounded-xl border border-amber-100 p-4">
            <p class="text-xs text-amber-600 font-medium uppercase tracking-wide">Unresolved</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">{{ stats.unresolved.toLocaleString() }}</p>
          </div>
          <div class="bg-rose-50 rounded-xl border border-rose-100 p-4">
            <p class="text-xs text-rose-500 font-medium uppercase tracking-wide">Duplicates</p>
            <p class="text-2xl font-bold text-rose-600 mt-1">{{ stats.duplicates.toLocaleString() }}</p>
          </div>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[160px]">
          <label class="block text-xs font-medium text-slate-500 mb-1">Search Device ID</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search…"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            @change="applyFilters"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
          <select
            v-model="filters.resolved"
            @change="applyFilters"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          >
            <option value="">All</option>
            <option value="false">Unresolved</option>
            <option value="true">Resolved</option>
          </select>
        </div>
        <button @click="applyFilters" class="bg-slate-700 hover:bg-slate-800 text-white text-sm px-4 py-2 rounded-lg transition-colors">
          Filter
        </button>
      </div>

      <!-- Logs Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Device Employee ID</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Matched User</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date &amp; Time</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Device</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!logs.data?.length">
                <td colspan="7" class="px-4 py-12 text-center text-slate-400 text-sm">No logs found.</td>
              </tr>
              <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-mono text-slate-700">{{ log.device_employee_id }}</td>
                <td class="px-4 py-3 text-slate-700">{{ log.user?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ log.log_datetime }}</td>
                <td class="px-4 py-3">
                  <span :class="logTypeBadge(log.log_type)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">
                    {{ log.log_type.replace('_', ' ') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs">{{ log.device_id ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span v-if="log.is_resolved" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700">Resolved</span>
                  <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700">Unresolved</span>
                </td>
                <td class="px-4 py-3">
                  <button
                    v-if="!log.is_resolved"
                    @click="openResolve(log)"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                  >
                    Resolve
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ logs.current_page }} of {{ logs.last_page }}</span>
          <div class="flex gap-2">
            <button @click="goToPage(logs.prev_page_url)" :disabled="!logs.prev_page_url" class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm disabled:opacity-40 hover:bg-slate-50">Prev</button>
            <button @click="goToPage(logs.next_page_url)" :disabled="!logs.next_page_url" class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm disabled:opacity-40 hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>

    </div>

    <!-- Resolve Modal -->
    <Teleport to="body">
      <div v-if="resolveModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-1">Resolve Log</h3>
          <p class="text-sm text-slate-500 mb-4">
            Map device ID <strong>{{ resolveModal.log?.device_employee_id }}</strong> to an employee.
            This will also resolve all other unresolved logs for this device ID.
          </p>
          <div class="mb-4">
            <label class="block text-xs font-medium text-slate-600 mb-1">Employee</label>
            <select
              v-model="resolveForm.user_id"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            >
              <option value="">— Select employee —</option>
              <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.name }}<template v-if="u.badge_id"> (Badge: {{ u.badge_id }})</template>
              </option>
            </select>
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="resolveModal.open = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button
              @click="submitResolve"
              :disabled="!resolveForm.user_id || resolveForm.processing"
              class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors"
            >
              Resolve
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ArrowUpTrayIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  logs:    Object,
  stats:   Object,
  users:   Array,
  filters: Object,
})

// ── Upload ─────────────────────────────────────────────────────────────────

const uploadForm = useForm({ files: [], device_id: '' })

function onFilePick (e) {
  uploadForm.files = Array.from(e.target.files)
}

function submitUpload () {
  uploadForm.post(route('hr.biometric.upload'), {
    forceFormData: true,
    onSuccess: () => { uploadForm.reset() },
  })
}

// ── Filters ────────────────────────────────────────────────────────────────

const filters = reactive({ ...props.filters })

function applyFilters () {
  router.get(route('hr.biometric.index'), filters, { preserveState: true, replace: true })
}

function goToPage (url) {
  if (url) router.visit(url, { preserveState: true })
}

// ── Resolve Modal ──────────────────────────────────────────────────────────

const resolveModal = reactive({ open: false, log: null })
const resolveForm  = useForm({ user_id: '' })

function openResolve (log) {
  resolveModal.log  = log
  resolveModal.open = true
  resolveForm.user_id = ''
}

function submitResolve () {
  resolveForm.post(route('hr.biometric.resolve', resolveModal.log.id), {
    onSuccess: () => {
      resolveModal.open = false
    },
  })
}

// ── Badge helpers ──────────────────────────────────────────────────────────

function logTypeBadge (type) {
  return {
    time_in:  'bg-emerald-50 text-emerald-700',
    time_out: 'bg-blue-50 text-blue-700',
    auto:     'bg-slate-100 text-slate-600',
  }[type] ?? 'bg-slate-100 text-slate-600'
}
</script>
