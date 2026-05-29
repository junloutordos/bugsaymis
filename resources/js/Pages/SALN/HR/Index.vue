<template>
  <Head title="All SALN Records" />
  <AdminLayout title="All SALN Records">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">All SALN Records</h1>
          <p class="text-sm text-slate-500 mt-0.5">HR view — all employees' SALN filings</p>
        </div>
        <Link :href="route('saln.hr.reports.annual')"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 transition-colors shrink-0">
          <ChartBarIcon class="h-4 w-4" />Annual Report
        </Link>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <input v-model="filters.search" type="text" placeholder="Search employee…"
            @input="applyFilters"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
          <select v-model="filters.year" @change="applyFilters"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Years</option>
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
          <select v-model="filters.status" @change="applyFilters"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="under_review">Under Review</option>
            <option value="approved">Approved</option>
            <option value="returned">Returned</option>
            <option value="filed">Filed</option>
          </select>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="records.data.length === 0"
        class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <DocumentTextIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No SALN records found</p>
        <p class="text-xs text-slate-400 mt-1">Try adjusting your filters.</p>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Year</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Assets</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Liabilities</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Net Worth</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="rec in records.data" :key="rec.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-5 py-3">
                <Link :href="route('saln.hr.employee', rec.user?.id)"
                  class="font-medium text-indigo-600 hover:text-indigo-800">
                  {{ rec.user?.name }}
                </Link>
                <p class="text-xs text-slate-400">{{ rec.user?.employee_id ?? rec.user?.email }}</p>
              </td>
              <td class="px-5 py-3 font-semibold text-slate-700">{{ rec.year }}</td>
              <td class="px-5 py-3 text-right text-slate-600">{{ fmtMoney(rec.total_assets) }}</td>
              <td class="px-5 py-3 text-right text-red-600">{{ fmtMoney(rec.total_liabilities) }}</td>
              <td class="px-5 py-3 text-right font-semibold"
                :class="rec.net_worth >= 0 ? 'text-emerald-700' : 'text-red-600'">
                {{ fmtMoney(rec.net_worth) }}
              </td>
              <td class="px-5 py-3">
                <span :class="statusBadge(rec.status)"
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold">
                  {{ rec.status_label }}
                </span>
              </td>
              <td class="px-5 py-3 text-right">
                <div class="inline-flex gap-1">
                  <Link :href="route('saln.show', rec.id)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                    title="View">
                    <EyeIcon class="h-4 w-4" />
                  </Link>
                  <button v-if="rec.status === 'approved'" type="button"
                    @click="doFile(rec)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                    title="Mark as Filed">
                    <ArchiveBoxArrowDownIcon class="h-4 w-4" />
                  </button>
                  <a v-if="rec.status === 'approved' || rec.status === 'filed'"
                    :href="route('saln.pdf', rec.id)" target="_blank"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                    title="Export PDF">
                    <DocumentArrowDownIcon class="h-4 w-4" />
                  </a>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <PaginationControl :links="records.links" :total="records.total" />
      </div>

    </div>

    <!-- File confirm modal -->
    <ConfirmModal
      :show="showFile !== null"
      title="Mark SALN as Filed"
      :message="`Mark the ${showFile?.year} SALN of ${showFile?.user?.name} as officially filed?`"
      confirmLabel="Mark as Filed"
      :processing="fileForm.processing"
      :showRemarks="true"
      remarksPlaceholder="Optional filing remarks…"
      @cancel="showFile = null"
      @confirm="(remarks) => submitFile(remarks)" />

  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ConfirmModal from '../Partials/ConfirmModal.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import {
  CheckCircleIcon, DocumentTextIcon, EyeIcon,
  DocumentArrowDownIcon, ArchiveBoxArrowDownIcon, ChartBarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  records: Object, // paginated
  filters: Object,
})

const filters = reactive({
  search: props.filters?.search ?? '',
  year: props.filters?.year ?? '',
  status: props.filters?.status ?? '',
})

const yearOptions = Array.from({ length: 10 }, (_, i) => new Date().getFullYear() - i)

let debounceTimer = null
function applyFilters() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(route('saln.hr.index'), filters, { preserveState: true, replace: true })
  }, 400)
}

const showFile = ref(null)
const fileForm = useForm({ remarks: '' })

function doFile(rec) {
  showFile.value = rec
}

function submitFile(remarks) {
  fileForm.remarks = remarks
  fileForm.post(route('saln.hr.file', showFile.value.id), {
    onSuccess: () => { showFile.value = null },
  })
}

const statusBadge = (s) => ({
  draft:        'bg-slate-100 text-slate-600',
  submitted:    'bg-blue-100 text-blue-700',
  under_review: 'bg-amber-100 text-amber-700',
  approved:     'bg-emerald-100 text-emerald-700',
  returned:     'bg-red-100 text-red-600',
  filed:        'bg-indigo-100 text-indigo-700',
}[s] ?? 'bg-slate-100 text-slate-600')

const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
