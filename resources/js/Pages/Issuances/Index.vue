<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import EmptyState from '@/Components/EmptyState.vue'
import IssuanceSettingsModal from './IssuanceSettingsModal.vue'
import {
  PlusIcon, MagnifyingGlassIcon, DocumentTextIcon,
  CheckCircleIcon, ClockIcon, ExclamationTriangleIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const flash = computed(() => page.props.flash ?? {})

const props = defineProps({
  issuances:  Array,
  isAdmin:    Boolean,
  typeLabels: Object,
  filters:    Object,
})

// ── Filters ────────────────────────────────────────────────────────────────
const search      = ref(props.filters?.search ?? '')
const filterType  = ref('')
const filterYear  = ref('')
const activeTab   = ref('all') // all | pending | released
const currentPage = ref(1)
const PER_PAGE    = 15

watch([filterType, filterYear, activeTab], () => { currentPage.value = 1 })

// Text search is server-side (searches content_text too) — debounced Inertia reload
let searchTimer = null
watch(search, (val) => {
  currentPage.value = 1
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    router.get(
      route('issuances.index'),
      val ? { search: val } : {},
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }, 400)
})

const pendingCount = computed(() =>
  (props.issuances ?? []).filter(i => !i.my_acknowledged_at && i.status === 'released').length
)

// Text search is handled server-side; only type/year/tab filter client-side here
const filtered = computed(() => {
  return (props.issuances ?? []).filter(i => {
    if (activeTab.value === 'pending' && (i.my_acknowledged_at || i.status !== 'released')) return false
    if (activeTab.value === 'released' && i.status !== 'released') return false
    if (activeTab.value === 'draft' && i.status !== 'draft') return false
    if (filterType.value && i.type !== filterType.value) return false
    if (filterYear.value && !i.control_number.includes(filterYear.value)) return false
    return true
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const s = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(s, s + PER_PAGE)
})

// ── Helpers — local badge colour mapping (mirrors useStatusBadge.js categories) ──

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function statusColor(status) {
  const s = (status ?? '').toString().toLowerCase().trim()
  if (s === 'in progress') return 'orange'
  if (['approved', 'active', 'completed', 'released', 'forwarded', 'published'].includes(s)) return 'green'
  if (['submitted', 'for review', 'under review', 'scheduled'].includes(s)) return 'blue'
  if (['pending', 'draft', 'filed', 'queued'].includes(s)) return 'amber'
  if (['rejected', 'declined', 'cancelled', 'returned'].includes(s)) return 'red'
  return 'slate'
}

function typeColor(type) {
  const map = {
    SO:     'indigo',
    TO:     'blue',
    MEMO:   'purple',
    OO:     'blue',
    AO:     'amber',
    CIRC:   'green',
    NOTICE: 'red',
  }
  return map[type] ?? 'slate'
}

const years = computed(() => {
  const y = new Set((props.issuances ?? []).map(i => i.control_number.split('-')[1]).filter(Boolean))
  return [...y].sort().reverse()
})

const showSettings = ref(false)
</script>

<template>
  <Head title="Issuances" />
  <AdminLayout title="Issuances">
    <div class="space-y-5">

      <FlashMessage :success="flash.success" :error="flash.error" />

      <AppPageHeader
        title="Official Issuances"
        :subtitle="isAdmin ? 'Create and manage official issuances released to staff' : 'Official issuances from OCD addressed to you or your office'"
      >
        <template #actions>
          <template v-if="isAdmin">
            <AppIconButton label="Issuance Settings" variant="secondary" @click="showSettings = true">
              <Cog6ToothIcon class="h-4 w-4" />
            </AppIconButton>
            <AppButton as="link" :href="route('issuances.create')">
              <PlusIcon class="h-4 w-4" /> New Issuance
            </AppButton>
          </template>
        </template>
      </AppPageHeader>

      <!-- Tabs -->
      <div class="flex gap-0 border-b border-slate-200 overflow-x-auto">
        <button v-for="tab in isAdmin ? [
          { key:'all',      label:'All' },
          { key:'released', label:'Released' },
          { key:'draft',    label:'Drafts' },
        ] : [
          { key:'all',     label:'My Issuances' },
          { key:'pending', label:'For My Acknowledgment' },
        ]" :key="tab.key"
          @click="activeTab = tab.key"
          class="px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors border-b-2"
          :class="activeTab === tab.key ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-700'">
          {{ tab.label }}
          <span v-if="tab.key === 'pending' && pendingCount > 0"
            class="ml-1.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-danger-600 text-[10px] font-bold text-white">
            {{ pendingCount > 9 ? '9+' : pendingCount }}
          </span>
        </button>
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-full sm:w-96">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" type="text" placeholder="Search control number, title, issued by…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="filterType"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Types</option>
          <option v-for="(label, code) in typeLabels" :key="code" :value="code">{{ label }}</option>
        </select>
        <select v-model="filterYear"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Years</option>
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="displayed.length === 0" :skeleton-cols="isAdmin ? 8 : 7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Control No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
            <th class="hidden md:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
            <th class="hidden lg:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Issued By</th>
            <th class="hidden lg:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th v-if="isAdmin" class="hidden md:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Ack.</th>
            <th class="px-3 py-3"></th>
          </tr>
        </template>

        <tr v-for="i in displayed" :key="i.id"
          class="hover:bg-slate-50/60 cursor-pointer"
          @click="router.visit(route('issuances.show', i.id))">
          <td class="px-4 py-3">
            <span class="font-mono text-xs font-bold text-indigo-700">{{ i.control_number }}</span>
          </td>
          <td class="px-4 py-3 max-w-[200px]">
            <p class="text-sm font-medium text-slate-800 truncate">{{ i.title }}</p>
          </td>
          <td class="hidden md:table-cell px-4 py-3">
            <AppBadge :color="typeColor(i.type)">{{ i.type }}</AppBadge>
          </td>
          <td class="hidden lg:table-cell px-4 py-3 text-xs text-slate-600">{{ i.creator?.name ?? '—' }}</td>
          <td class="hidden lg:table-cell px-4 py-3 text-xs text-slate-500">{{ fmtDate(i.released_at ?? i.created_at) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(i.status)" class="capitalize">{{ i.status }}</AppBadge>
          </td>
          <td v-if="isAdmin" class="hidden md:table-cell px-4 py-3 text-xs text-slate-500">
            {{ i.acknowledged_count }}/{{ i.recipients_count }}
          </td>
          <td class="px-3 py-3 text-right">
            <!-- Unread indicator for staff -->
            <span v-if="!isAdmin && !i.my_acknowledged_at && i.status === 'released'"
              class="inline-block w-2 h-2 rounded-full bg-danger-600 mr-1"></span>
            <span class="text-indigo-600 text-xs font-medium">View →</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="i in displayed" :key="i.id" class="p-4 space-y-2" @click="router.visit(route('issuances.show', i.id))">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-xs font-bold text-indigo-700">{{ i.control_number }}</p>
                <p class="text-sm font-medium text-slate-800">{{ i.title }}</p>
              </div>
              <AppBadge :color="statusColor(i.status)" class="capitalize">{{ i.status }}</AppBadge>
            </div>
            <div class="flex items-center gap-2">
              <AppBadge :color="typeColor(i.type)">{{ i.type }}</AppBadge>
              <span class="text-xs text-slate-500">{{ fmtDate(i.released_at ?? i.created_at) }}</span>
            </div>
            <div class="flex items-center justify-between pt-1">
              <span class="text-xs text-slate-500">{{ i.creator?.name ?? '—' }}</span>
              <span class="text-indigo-600 text-xs font-medium">View →</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState
            title="No issuances found"
            :subtitle="activeTab === 'pending' ? 'No unacknowledged issuances for you.' : 'Try adjusting your filters.'" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage" :total-pages="totalPages" :total="filtered.length"
            @prev="currentPage--" @next="currentPage++" @page="currentPage = $event" />
        </template>
      </AppTable>

      <IssuanceSettingsModal :show="showSettings" @close="showSettings = false" />
    </div>
  </AdminLayout>
</template>
