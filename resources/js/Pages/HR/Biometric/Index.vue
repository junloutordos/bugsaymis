<template>
  <Head title="Biometric Logs" />
  <AdminLayout title="Biometric Logs">
    <div class="space-y-5">

      <AppPageHeader title="Biometric Logs" subtitle="Import and resolve biometric punch records." />

      <div class="rounded-lg border border-slate-200 bg-white p-4 mb-6">
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Live Punch Feed</h3>
        <p v-if="livePunches.length === 0" class="text-sm text-slate-400">
          Waiting for punches from the guardhouse device…
        </p>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="p in livePunches" :key="p.key" class="py-2 flex items-center justify-between text-sm">
            <div class="flex items-center gap-2">
              <span
                class="inline-block w-2 h-2 rounded-full"
                :class="p.is_resolved ? 'bg-indigo-600' : 'bg-amber-500'"
              ></span>
              <span class="font-medium text-slate-700">
                {{ p.is_resolved ? p.user_name : `Unresolved badge ${p.device_employee_id}` }}
              </span>
              <span class="text-slate-400">{{ p.log_type === 'time_in' ? 'Time In' : p.log_type === 'time_out' ? 'Time Out' : 'Punch' }}</span>
            </div>
            <div class="text-slate-400">
              {{ p.device_label }} · {{ formatLiveTime(p.log_datetime) }}
            </div>
          </li>
        </ul>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" />
        {{ $page.props.flash.error }}
      </div>

      <!-- Upload + Stats Row -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Upload Panel -->
        <div v-if="canManage" class="lg:col-span-1 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-5">
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
            <AppButton
              type="submit"
              block
              :loading="uploadForm.processing"
              :disabled="uploadForm.processing || !uploadForm.files.length"
            >
              {{ uploadForm.processing ? 'Importing…' : 'Import Now' }}
            </AppButton>
          </form>
        </div>

        <!-- Stats Cards -->
        <div :class="canManage ? 'lg:col-span-2' : 'lg:col-span-3'" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-4">
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
      <AppFilterBar>
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
        <template #actions>
          <AppButton size="sm" @click="applyFilters">Filter</AppButton>
        </template>
      </AppFilterBar>

      <!-- Logs Table -->
      <AppTable :is-empty="!logs.data?.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Device Employee ID</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Matched User</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date &amp; Time</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Device</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-mono text-slate-700">{{ log.device_employee_id }}</td>
          <td class="px-4 py-3 text-slate-700">{{ log.user?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ log.log_datetime }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="logTypeBadge(log.log_type)">{{ log.log_type.replace('_', ' ') }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ log.device_id ?? '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="log.is_resolved ? 'green' : 'amber'">{{ log.is_resolved ? 'Resolved' : 'Unresolved' }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <button
              v-if="canManage && !log.is_resolved"
              @click="openResolve(log)"
              class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
            >
              Resolve
            </button>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="log in logs.data" :key="log.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-xs text-slate-500">{{ log.device_employee_id }}</p>
                <p class="font-medium text-slate-800 text-sm">{{ log.user?.name ?? '—' }}</p>
                <p class="text-xs text-slate-400">{{ log.log_datetime }}</p>
              </div>
              <AppBadge :color="log.is_resolved ? 'green' : 'amber'">{{ log.is_resolved ? 'Resolved' : 'Unresolved' }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <AppBadge :color="logTypeBadge(log.log_type)">{{ log.log_type.replace('_', ' ') }}</AppBadge>
              <span>{{ log.device_id ?? '—' }}</span>
            </div>
            <div v-if="canManage && !log.is_resolved" class="flex justify-end pt-1">
              <button @click="openResolve(log)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Resolve</button>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No logs found" />
        </template>

        <template #footer>
          <PaginationControl
            :links="logs.links"
            :current-page="logs.current_page"
            :total-pages="logs.last_page"
            :total="logs.total" />
        </template>
      </AppTable>

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
            <AppButton variant="secondary" @click="resolveModal.open = false">Cancel</AppButton>
            <AppButton
              :disabled="!resolveForm.user_id || resolveForm.processing"
              :loading="resolveForm.processing"
              @click="submitResolve"
            >
              Resolve
            </AppButton>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import {
  ArrowUpTrayIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  logs:      Object,
  stats:     Object,
  users:     Array,
  filters:   Object,
  canManage: Boolean,
})

// ── Live Punch Feed ────────────────────────────────────────────────────────

const livePunches = ref([])
const MAX_LIVE_PUNCHES = 25

function subscribeToLiveFeed() {
  if (!window.Echo) return
  window.Echo.private('biometric-feed')
    .listen('.biometric.punch.recorded', (payload) => {
      livePunches.value.unshift({
        key: `${payload.device_employee_id}-${payload.log_datetime}-${Date.now()}`,
        ...payload,
      })
      if (livePunches.value.length > MAX_LIVE_PUNCHES) {
        livePunches.value.pop()
      }
    })
}

onMounted(() => {
  subscribeToLiveFeed()
})

onUnmounted(() => {
  window.Echo?.leaveChannel('private-biometric-feed')
})

function formatLiveTime(iso) {
  return new Date(iso.replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' })
}

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
    time_in:  'green',
    time_out: 'blue',
    auto:     'slate',
  }[type] ?? 'slate'
}
</script>
