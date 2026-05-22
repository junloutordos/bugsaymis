<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  PlusIcon, MagnifyingGlassIcon, DocumentTextIcon,
  CheckCircleIcon, ClockIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  issuances:  Array,
  isAdmin:    Boolean,
  typeLabels: Object,
})

// ── Filters ────────────────────────────────────────────────────────────────
const search      = ref('')
const filterType  = ref('')
const filterYear  = ref('')
const activeTab   = ref('all') // all | pending | released
const currentPage = ref(1)
const PER_PAGE    = 15

watch([search, filterType, filterYear, activeTab], () => { currentPage.value = 1 })

const pendingCount = computed(() =>
  (props.issuances ?? []).filter(i => !i.my_acknowledged_at && i.status === 'released').length
)

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  return (props.issuances ?? []).filter(i => {
    if (activeTab.value === 'pending' && (i.my_acknowledged_at || i.status !== 'released')) return false
    if (activeTab.value === 'released' && i.status !== 'released') return false
    if (activeTab.value === 'draft' && i.status !== 'draft') return false
    if (filterType.value && i.type !== filterType.value) return false
    if (filterYear.value && !i.control_number.includes(filterYear.value)) return false
    if (!q) return true
    return i.control_number.toLowerCase().includes(q)
        || i.title.toLowerCase().includes(q)
        || i.creator?.name?.toLowerCase().includes(q)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const s = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(s, s + PER_PAGE)
})

// ── Helpers ────────────────────────────────────────────────────────────────
const typeCls = {
  SO: 'bg-indigo-100 text-indigo-700', TO: 'bg-blue-100 text-blue-700',
  MEMO: 'bg-violet-100 text-violet-700', OO: 'bg-cyan-100 text-cyan-700',
  AO: 'bg-amber-100 text-amber-700', CIRC: 'bg-emerald-100 text-emerald-700',
  NOTICE: 'bg-rose-100 text-rose-700',
}
const statusCls = { draft: 'bg-slate-100 text-slate-600', released: 'bg-emerald-100 text-emerald-700', cancelled: 'bg-red-100 text-red-600' }

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

const years = computed(() => {
  const y = new Set((props.issuances ?? []).map(i => i.control_number.split('-')[1]).filter(Boolean))
  return [...y].sort().reverse()
})
</script>

<template>
  <Head title="Issuances" />
  <AdminLayout title="Issuances">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
      <div>
        <h2 class="text-lg font-bold text-slate-800">Official Issuances</h2>
        <p class="text-xs text-slate-500 mt-0.5">Special Orders, Travel Orders, Memorandums and other official issuances from OCD</p>
      </div>
      <a v-if="isAdmin" :href="route('issuances.create')"
        class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white rounded-lg"
        style="background:linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%)">
        <PlusIcon class="h-4 w-4" /> New Issuance
      </a>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-slate-200 mb-4 overflow-x-auto">
      <button v-for="tab in [
        { key:'all', label:'All' },
        { key:'pending', label:'For My Acknowledgment' },
        { key:'released', label:'Released' },
        ...(isAdmin ? [{ key:'draft', label:'Drafts' }] : []),
      ]" :key="tab.key"
        @click="activeTab = tab.key"
        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors border-b-2"
        :class="activeTab === tab.key ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-700'">
        {{ tab.label }}
        <span v-if="tab.key === 'pending' && pendingCount > 0"
          class="ml-1.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
          {{ pendingCount > 9 ? '9+' : pendingCount }}
        </span>
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-2 mb-4">
      <div class="relative flex-1">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
        <input v-model="search" type="text" placeholder="Search control number, title, issued by…"
          class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <select v-model="filterType"
        class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Types</option>
        <option v-for="(label, code) in typeLabels" :key="code" :value="code">{{ label }}</option>
      </select>
      <select v-model="filterYear"
        class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Years</option>
        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div v-if="displayed.length === 0" class="py-16 text-center text-slate-400 text-sm">
        No issuances found.
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
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
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="i in displayed" :key="i.id"
            class="hover:bg-slate-50 transition-colors cursor-pointer"
            @click="router.visit(route('issuances.show', i.id))">
            <td class="px-4 py-3">
              <span class="font-mono text-xs font-bold text-indigo-700">{{ i.control_number }}</span>
            </td>
            <td class="px-4 py-3 max-w-[200px]">
              <p class="text-sm font-medium text-slate-800 truncate">{{ i.title }}</p>
            </td>
            <td class="hidden md:table-cell px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                :class="typeCls[i.type] ?? 'bg-slate-100 text-slate-600'">
                {{ i.type }}
              </span>
            </td>
            <td class="hidden lg:table-cell px-4 py-3 text-xs text-slate-600">{{ i.creator?.name ?? '—' }}</td>
            <td class="hidden lg:table-cell px-4 py-3 text-xs text-slate-500">{{ fmtDate(i.released_at ?? i.created_at) }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold capitalize"
                :class="statusCls[i.status] ?? 'bg-slate-100 text-slate-600'">
                {{ i.status }}
              </span>
            </td>
            <td v-if="isAdmin" class="hidden md:table-cell px-4 py-3 text-xs text-slate-500">
              {{ i.acknowledged_count }}/{{ i.recipients_count }}
            </td>
            <td class="px-3 py-3 text-right">
              <!-- Unread indicator for staff -->
              <span v-if="!isAdmin && !i.my_acknowledged_at && i.status === 'released'"
                class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span>
              <span class="text-indigo-600 text-xs font-medium">View →</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
        <span>Page {{ currentPage }} of {{ totalPages }}</span>
        <div class="flex gap-2">
          <button @click="currentPage--" :disabled="currentPage === 1"
            class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40">Prev</button>
          <button @click="currentPage++" :disabled="currentPage === totalPages"
            class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
