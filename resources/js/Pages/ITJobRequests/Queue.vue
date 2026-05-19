<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { QueueListIcon, ArrowPathIcon, PencilSquareIcon } from '@heroicons/vue/24/outline'
import MISAssessmentModal from '@/Components/MISAssessmentModal.vue'

const props = defineProps({
  items:        { type: Array,   default: () => [] },
  filters:      { type: Object,  default: () => ({}) },
  categories:   { type: Array,   default: () => [] },
  ictEquipment: { type: Array,   default: () => [] },
  hasPin:       { type: Boolean, default: false },
  signatureUri: { type: String,  default: null },
})

// ── Filters ───────────────────────────────────────────────────────────────────
const search         = ref(props.filters?.search   ?? '')
const filterCategory = ref(props.filters?.category ?? '')
const isLoading      = ref(false)
let debounceTimer    = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('jobrequests.queue'), {
      search:   search.value   || undefined,
      category: filterCategory.value || undefined,
    }, {
      preserveState: true,
      replace: true,
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))
watch(filterCategory, () => applyFilters(true))

// ── Priority helpers ──────────────────────────────────────────────────────────
const PRIORITY_LABELS = {
  urgent: 'Urgent',
  high:   'High',
  normal: 'Normal',
  low:    'Low',
}

const PRIORITY_COLORS = {
  urgent: 'bg-red-100 text-red-700 ring-1 ring-red-300',
  high:   'bg-orange-100 text-orange-700 ring-1 ring-orange-300',
  normal: 'bg-blue-100 text-blue-700 ring-1 ring-blue-200',
  low:    'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
}

const POSITION_COLORS = [
  'bg-yellow-400 text-yellow-900',
  'bg-slate-300 text-slate-800',
  'bg-amber-600 text-white',
]

function positionBadgeClass(pos) {
  if (pos <= 3) return POSITION_COLORS[pos - 1]
  return 'bg-slate-100 text-slate-600'
}

function statusChipClass(status) {
  if (status === 'In Progress') return 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200'
  return 'bg-amber-100 text-amber-700 ring-1 ring-amber-200'
}

function timeInQueue(queuedAt) {
  if (!queuedAt) return '—'
  const diff = Date.now() - new Date(queuedAt).getTime()
  const mins  = Math.floor(diff / 60000)
  const hours = Math.floor(mins / 60)
  const days  = Math.floor(hours / 24)
  if (days > 0)  return `${days}d ${hours % 24}h`
  if (hours > 0) return `${hours}h ${mins % 60}m`
  return `${mins}m`
}

// ── Inline priority update ────────────────────────────────────────────────────
const updatingId = ref(null)

function changePriority(item, newPriority) {
  if (item.priority === newPriority) return
  updatingId.value = item.id

  const form = useForm({ priority: newPriority })
  form.put(route('jobrequests.update-priority', item.id), {
    preserveScroll: true,
    onSuccess: () => { updatingId.value = null },
    onError:   () => { updatingId.value = null },
  })
}

// ── MIS Assessment modal ──────────────────────────────────────────────────────
const showAssessmentModal = ref(false)
const assessmentRequest   = ref(null)

function openAssessment(item) {
  assessmentRequest.value   = item
  showAssessmentModal.value = true
}

function onAssessmentSaved() {
  showAssessmentModal.value = false
  router.reload({ only: ['items'] })
}

// ── Stats ─────────────────────────────────────────────────────────────────────
const stats = computed(() => {
  const all = props.items
  return {
    total:  all.length,
    urgent: all.filter(i => i.priority === 'urgent').length,
    high:   all.filter(i => i.priority === 'high').length,
    inProg: all.filter(i => i.status === 'In Progress').length,
    assessed: all.filter(i => i.status === 'MIS Assessed the Request').length,
  }
})
</script>

<template>
  <Head title="IT Job Request Queue" />
  <AdminLayout title="IT Job Request Queue">
    <div>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-2">
          <QueueListIcon class="w-6 h-6 text-indigo-600" />
          <h1 class="text-xl font-semibold text-slate-800">MIS Work Queue</h1>
        </div>
        <a
          :href="route('jobrequests.index')"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
        >
          All Requests
        </a>
      </div>

      <!-- Stats row -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3">
          <div class="text-xs text-slate-500 uppercase tracking-wide">Total</div>
          <div class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.total }}</div>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-100 shadow-sm px-4 py-3">
          <div class="text-xs text-red-500 uppercase tracking-wide">Urgent</div>
          <div class="text-2xl font-bold text-red-700 mt-0.5">{{ stats.urgent }}</div>
        </div>
        <div class="bg-orange-50 rounded-xl border border-orange-100 shadow-sm px-4 py-3">
          <div class="text-xs text-orange-500 uppercase tracking-wide">High</div>
          <div class="text-2xl font-bold text-orange-700 mt-0.5">{{ stats.high }}</div>
        </div>
        <div class="bg-indigo-50 rounded-xl border border-indigo-100 shadow-sm px-4 py-3">
          <div class="text-xs text-indigo-500 uppercase tracking-wide">In Progress</div>
          <div class="text-2xl font-bold text-indigo-700 mt-0.5">{{ stats.inProg }}</div>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-100 shadow-sm px-4 py-3">
          <div class="text-xs text-amber-500 uppercase tracking-wide">Assessed</div>
          <div class="text-2xl font-bold text-amber-700 mt-0.5">{{ stats.assessed }}</div>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4">
        <div class="flex flex-wrap items-center gap-3">
          <div class="relative flex-1 sm:w-64 sm:flex-none">
            <input
              v-model="search"
              type="text"
              placeholder="Search queue..."
              @keydown.enter.prevent="applyFilters(true)"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
            />
            <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
              <svg class="animate-spin h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
            </span>
          </div>
          <select
            v-model="filterCategory"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          >
            <option value="">All Categories</option>
            <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
          </select>
          <button
            @click="applyFilters(true)"
            :disabled="isLoading"
            class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50"
          >
            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
            Refresh
          </button>
        </div>
      </div>

      <!-- Queue table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Desktop -->
        <div class="hidden sm:block overflow-x-auto" :class="{ 'opacity-50 pointer-events-none': isLoading }">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-14">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Priority</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">ITJR #</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Time in Queue</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Assigned To</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Change Priority</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="item in props.items"
                :key="item.id"
                class="hover:bg-slate-50/60 transition-colors"
                :class="item.priority === 'urgent' ? 'bg-red-50/40' : item.priority === 'high' ? 'bg-orange-50/30' : ''"
              >
                <!-- Position -->
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                    :class="positionBadgeClass(item.queue_position)"
                  >
                    {{ item.queue_position }}
                  </span>
                </td>

                <!-- Priority badge -->
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="PRIORITY_COLORS[item.priority] ?? PRIORITY_COLORS.normal"
                  >
                    {{ PRIORITY_LABELS[item.priority] ?? item.priority }}
                  </span>
                </td>

                <td class="px-4 py-3 text-slate-700 font-mono text-xs">{{ item.itjr_no }}</td>

                <td class="px-4 py-3 text-slate-800 max-w-xs">
                  <div class="truncate font-medium">{{ item.title }}</div>
                </td>

                <td class="px-4 py-3 text-slate-600">{{ item.user?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600">{{ item.category }}</td>

                <!-- Status chip -->
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="statusChipClass(item.status)"
                  >
                    {{ item.status === 'MIS Assessed the Request' ? 'Assessed' : item.status }}
                  </span>
                </td>

                <td class="px-4 py-3 text-slate-500 text-xs">{{ timeInQueue(item.queued_at) }}</td>

                <td class="px-4 py-3 text-slate-600 text-sm">{{ item.assigned_to?.name ?? '—' }}</td>

                <!-- Priority dropdown -->
                <td class="px-4 py-3">
                  <div class="relative">
                    <select
                      :value="item.priority ?? 'normal'"
                      :disabled="updatingId === item.id"
                      @change="changePriority(item, $event.target.value)"
                      class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 w-28"
                    >
                      <option value="urgent">Urgent</option>
                      <option value="high">High</option>
                      <option value="normal">Normal</option>
                      <option value="low">Low</option>
                    </select>
                    <span v-if="updatingId === item.id" class="absolute right-6 top-1/2 -translate-y-1/2">
                      <svg class="animate-spin h-3 w-3 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                      </svg>
                    </span>
                  </div>
                </td>

                <!-- Assess button -->
                <td class="px-4 py-3 text-center">
                  <button
                    @click="openAssessment(item)"
                    class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm"
                    title="Open MIS Assessment"
                  >
                    <PencilSquareIcon class="w-3.5 h-3.5" />
                    Assess
                  </button>
                </td>
              </tr>

              <tr v-if="props.items.length === 0">
                <td colspan="11" class="py-16 text-center text-slate-400 text-sm">
                  No requests currently in the queue.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden p-4 space-y-3" :class="{ 'opacity-50 pointer-events-none': isLoading }">
          <div
            v-for="item in props.items"
            :key="item.id"
            class="border rounded-xl p-4"
            :class="item.priority === 'urgent' ? 'border-red-200 bg-red-50/30' : item.priority === 'high' ? 'border-orange-200 bg-orange-50/20' : 'border-slate-100'"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2">
                <span
                  class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold flex-shrink-0"
                  :class="positionBadgeClass(item.queue_position)"
                >
                  {{ item.queue_position }}
                </span>
                <div>
                  <div class="font-semibold text-slate-800 leading-snug">{{ item.title }}</div>
                  <div class="text-xs text-slate-400 font-mono mt-0.5">{{ item.itjr_no }}</div>
                </div>
              </div>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold flex-shrink-0"
                :class="PRIORITY_COLORS[item.priority] ?? PRIORITY_COLORS.normal"
              >
                {{ PRIORITY_LABELS[item.priority] ?? item.priority }}
              </span>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-y-1 text-sm text-slate-600">
              <div><span class="text-slate-400">Requestor:</span> {{ item.user?.name ?? '—' }}</div>
              <div><span class="text-slate-400">Category:</span> {{ item.category }}</div>
              <div>
                <span class="text-slate-400">Status:</span>
                <span
                  class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold"
                  :class="statusChipClass(item.status)"
                >
                  {{ item.status === 'MIS Assessed the Request' ? 'Assessed' : item.status }}
                </span>
              </div>
              <div><span class="text-slate-400">In queue:</span> {{ timeInQueue(item.queued_at) }}</div>
            </div>

            <div class="mt-3 flex items-center gap-2 flex-wrap">
              <label class="text-xs text-slate-500">Priority:</label>
              <select
                :value="item.priority ?? 'normal'"
                :disabled="updatingId === item.id"
                @change="changePriority(item, $event.target.value)"
                class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
              >
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="normal">Normal</option>
                <option value="low">Low</option>
              </select>
              <button
                @click="openAssessment(item)"
                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm"
              >
                <PencilSquareIcon class="w-3.5 h-3.5" />
                Assess
              </button>
            </div>
          </div>

          <div v-if="props.items.length === 0" class="py-16 text-center text-slate-400 text-sm">
            No requests currently in the queue.
          </div>
        </div>
      </div>
    </div>

    <MISAssessmentModal
      :show="showAssessmentModal"
      :request="assessmentRequest"
      :ict-equipment="props.ictEquipment"
      :has-pin="props.hasPin"
      :signature-uri="props.signatureUri"
      @close="showAssessmentModal = false"
      @saved="onAssessmentSaved"
    />
  </AdminLayout>
</template>
