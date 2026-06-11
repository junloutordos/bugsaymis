<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  PlusIcon, MagnifyingGlassIcon, LockClosedIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const flash = computed(() => page.props.flash ?? {})

const props = defineProps({
  issuances:        Array,
  categories:       Array,
  canManage:        Boolean,
  totalActiveUsers: Number,
})

const search        = ref('')
const filterCategory= ref('')
const filterYear    = ref('')
const currentPage   = ref(1)
const PER_PAGE      = 15

watch([search, filterCategory, filterYear], () => { currentPage.value = 1 })

const categoryColors = {
  MEMO:  'bg-violet-100 text-violet-700',
  MC:    'bg-indigo-100 text-indigo-700',
  OO:    'bg-cyan-100 text-cyan-700',
  SO:    'bg-blue-100 text-blue-700',
  AO:    'bg-amber-100 text-amber-700',
  EO:    'bg-rose-100 text-rose-700',
  BR:    'bg-emerald-100 text-emerald-700',
  ADV:   'bg-sky-100 text-sky-700',
  GUIDE: 'bg-teal-100 text-teal-700',
  OTHER: 'bg-slate-100 text-slate-600',
}

const statusCls = {
  active:     'bg-emerald-100 text-emerald-700',
  superseded: 'bg-amber-100 text-amber-700',
  archived:   'bg-slate-100 text-slate-500',
}

const years = computed(() => {
  const y = new Set((props.issuances ?? []).map(i => i.issued_date?.slice(0, 4)).filter(Boolean))
  return [...y].sort().reverse()
})

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  return (props.issuances ?? []).filter(i => {
    if (filterCategory.value && i.category_code !== filterCategory.value) return false
    if (filterYear.value && i.issued_date?.slice(0, 4) !== filterYear.value) return false
    if (!q) return true
    return i.title.toLowerCase().includes(q)
        || (i.reference_no ?? '').toLowerCase().includes(q)
        || (i.description ?? '').toLowerCase().includes(q)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const s = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(s, s + PER_PAGE)
})

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

function fmtSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function readDenominator(i) {
  return i.recipient_type === 'all' ? props.totalActiveUsers : i.recipients_count
}
</script>

<template>
  <Head title="Knowledge Management" />
  <AdminLayout title="Knowledge Management">

    <FlashMessage :success="flash.success" :error="flash.error" />

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
      <div>
        <h2 class="text-xl font-semibold text-slate-800">Knowledge Management</h2>
        <p class="text-xs text-slate-500 mt-0.5">
          Searchable repository of memoranda, orders, and other issuances from the Office of the Executive Director
        </p>
      </div>
      <a v-if="canManage" :href="route('km.create')"
        class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white rounded-lg bg-indigo-600 hover:bg-indigo-700">
        <PlusIcon class="h-4 w-4" /> Upload Document
      </a>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-2 mb-4">
      <div class="relative flex-1">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
        <input v-model="search" type="text" placeholder="Search title, reference no., description…"
          class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <select v-model="filterCategory"
        class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Categories</option>
        <option v-for="c in categories" :key="c.code" :value="c.code">{{ c.label }}</option>
      </select>
      <select v-model="filterYear"
        class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Years</option>
        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <EmptyState v-if="displayed.length === 0"
        title="No documents found"
        subtitle="Try adjusting your search or filters." />
      <table v-else class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
            <th class="hidden lg:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Reference No.</th>
            <th class="hidden md:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Issued</th>
            <th v-if="canManage" class="hidden md:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th v-if="canManage" class="hidden md:table-cell px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Read</th>
            <th class="px-3 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="i in displayed" :key="i.id"
            class="hover:bg-slate-50 transition-colors cursor-pointer"
            @click="router.visit(route('km.show', i.id))">
            <td class="px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                :class="categoryColors[i.category_code] ?? categoryColors.OTHER">
                {{ i.category?.label ?? i.category_code }}
              </span>
            </td>
            <td class="px-4 py-3 max-w-[260px]">
              <div class="flex items-center gap-2">
                <span v-if="!i.is_acknowledged" class="inline-block w-2 h-2 rounded-full bg-red-500 shrink-0"></span>
                <p class="text-sm font-medium text-slate-800 truncate">{{ i.title }}</p>
                <LockClosedIcon v-if="canManage && i.recipient_type !== 'all'" class="h-3.5 w-3.5 text-slate-400 shrink-0" title="Restricted" />
              </div>
              <p class="text-xs text-slate-400 truncate">{{ fmtSize(i.file_size) }}</p>
            </td>
            <td class="hidden lg:table-cell px-4 py-3 text-xs text-slate-600 font-mono">{{ i.reference_no ?? '—' }}</td>
            <td class="hidden md:table-cell px-4 py-3 text-xs text-slate-500">{{ fmtDate(i.issued_date) }}</td>
            <td v-if="canManage" class="hidden md:table-cell px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold capitalize"
                :class="statusCls[i.status] ?? 'bg-slate-100 text-slate-600'">
                {{ i.status }}
              </span>
            </td>
            <td v-if="canManage" class="hidden md:table-cell px-4 py-3 text-xs text-slate-500">
              {{ i.acknowledgments_count }}/{{ readDenominator(i) }}
            </td>
            <td class="px-3 py-3 text-right">
              <span class="text-indigo-600 text-xs font-medium">View →</span>
            </td>
          </tr>
        </tbody>
      </table>

      <PaginationControl
        :current-page="currentPage" :total-pages="totalPages" :total="filtered.length"
        @prev="currentPage--" @next="currentPage++" />
    </div>

  </AdminLayout>
</template>
