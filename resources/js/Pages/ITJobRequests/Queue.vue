<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PencilSquareIcon } from '@heroicons/vue/24/outline'
import MISAssessmentModal from '@/Components/MISAssessmentModal.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'

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

const applyFilters = () => {
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

const clearFilters = () => {
  search.value = ''
  filterCategory.value = ''
  isLoading.value = true
  router.get(route('jobrequests.queue'), {}, {
    preserveState: true,
    replace: true,
    onFinish: () => { isLoading.value = false },
  })
}


// ── Priority helpers ──────────────────────────────────────────────────────────
const PRIORITY_LABELS = {
  urgent: 'Urgent',
  high:   'High',
  normal: 'Normal',
  low:    'Low',
}

function priorityBadgeColor(priority) {
  const map = { urgent: 'red', high: 'orange', normal: 'blue', low: 'slate' }
  return map[priority] ?? 'blue'
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

function statusBadgeColor(status) {
  return status === 'In Progress' ? 'orange' : 'amber'
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
      <AppPageHeader title="MIS Work Queue">
        <template #actions>
          <AppButton as="a" :href="route('jobrequests.index')" variant="secondary">
            All Requests
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Stats row -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
        <AppCard>
          <div class="text-xs text-slate-500 uppercase tracking-wide">Total</div>
          <div class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.total }}</div>
        </AppCard>
        <AppCard>
          <div class="text-xs text-red-500 uppercase tracking-wide">Urgent</div>
          <div class="text-2xl font-bold text-red-700 mt-0.5">{{ stats.urgent }}</div>
        </AppCard>
        <AppCard>
          <div class="text-xs text-orange-500 uppercase tracking-wide">High</div>
          <div class="text-2xl font-bold text-orange-700 mt-0.5">{{ stats.high }}</div>
        </AppCard>
        <AppCard>
          <div class="text-xs text-indigo-500 uppercase tracking-wide">In Progress</div>
          <div class="text-2xl font-bold text-indigo-700 mt-0.5">{{ stats.inProg }}</div>
        </AppCard>
        <AppCard>
          <div class="text-xs text-amber-500 uppercase tracking-wide">Assessed</div>
          <div class="text-2xl font-bold text-amber-700 mt-0.5">{{ stats.assessed }}</div>
        </AppCard>
      </div>

      <!-- Filter bar -->
      <AppFilterBar class="mb-4">
        <div class="relative flex-1 sm:w-64 sm:flex-none">
          <AppInput
            v-model="search"
            type="text"
            placeholder="Search queue..."
            @keydown.enter.prevent="applyFilters"
          />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">⏳</span>
        </div>
        <select
          v-model="filterCategory"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        >
          <option value="">All Categories</option>
          <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
        </select>

        <template #actions>
          <AppButton @click="applyFilters" :disabled="isLoading">Search</AppButton>
          <AppButton v-if="search || filterCategory" @click="clearFilters" :disabled="isLoading" variant="secondary">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Queue table -->
      <AppTable :is-empty="props.items.length === 0" :skeleton-cols="11">
        <template #head>
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
        </template>

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
            <AppBadge :color="priorityBadgeColor(item.priority)">{{ PRIORITY_LABELS[item.priority] ?? item.priority }}</AppBadge>
          </td>

          <td class="px-4 py-3 text-slate-700 font-mono text-xs">{{ item.itjr_no }}</td>

          <td class="px-4 py-3 text-slate-800 max-w-xs">
            <div class="truncate font-medium">{{ item.title }}</div>
          </td>

          <td class="px-4 py-3 text-slate-600">{{ item.user?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-600">{{ item.category }}</td>

          <!-- Status badge -->
          <td class="px-4 py-3">
            <AppBadge :color="statusBadgeColor(item.status)">{{ item.status === 'MIS Assessed the Request' ? 'Assessed' : item.status }}</AppBadge>
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
            <AppButton size="sm" @click="openAssessment(item)" title="Open MIS Assessment">
              <PencilSquareIcon class="w-3.5 h-3.5" />
              Assess
            </AppButton>
          </td>
        </tr>

        <template #mobileCard>
          <div
            v-for="item in props.items"
            :key="item.id"
            class="p-4"
            :class="item.priority === 'urgent' ? 'bg-red-50/30' : item.priority === 'high' ? 'bg-orange-50/20' : ''"
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
              <AppBadge :color="priorityBadgeColor(item.priority)">{{ PRIORITY_LABELS[item.priority] ?? item.priority }}</AppBadge>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-y-1 text-sm text-slate-600">
              <div><span class="text-slate-400">Requestor:</span> {{ item.user?.name ?? '—' }}</div>
              <div><span class="text-slate-400">Category:</span> {{ item.category }}</div>
              <div>
                <span class="text-slate-400">Status:</span>
                <AppBadge :color="statusBadgeColor(item.status)">{{ item.status === 'MIS Assessed the Request' ? 'Assessed' : item.status }}</AppBadge>
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
              <AppButton size="sm" @click="openAssessment(item)">
                <PencilSquareIcon class="w-3.5 h-3.5" />
                Assess
              </AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No requests currently in the queue" />
        </template>
      </AppTable>
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
