<template>
  <Head title="All SALN Records" />
  <AdminLayout title="All SALN Records">
    <div class="space-y-5">

      <AppPageHeader title="All SALN Records" subtitle="HR view — all employees' SALN filings">
        <template #actions>
          <AppButton as="link" variant="secondary" :href="route('saln.hr.reports.annual')">
            <ChartBarIcon class="h-4 w-4" />Annual Report
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <input v-model="filters.search" type="text" placeholder="Search employee…"
          @input="applyFilters"
          class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <select v-model="filters.year" @change="applyFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Years</option>
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
        <select v-model="filters.status" @change="applyFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="submitted">Submitted</option>
          <option value="under_review">Under Review</option>
          <option value="approved">Approved</option>
          <option value="returned">Returned</option>
          <option value="filed">Filed</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="records.data.length === 0" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Year</th>
            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Assets</th>
            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Liabilities</th>
            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Net Worth</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </template>

        <tr v-for="rec in records.data" :key="rec.id" class="hover:bg-slate-50/60">
          <td class="px-5 py-3">
            <Link :href="route('saln.hr.employee', rec.user?.id)"
              class="font-medium text-indigo-600 hover:text-indigo-800">
              {{ rec.user?.name }}
            </Link>
            <p class="text-xs text-slate-400">{{ rec.user?.employee_id ?? rec.user?.email }}</p>
          </td>
          <td class="px-5 py-3 font-semibold text-slate-700">{{ rec.year }}</td>
          <td class="px-5 py-3 text-right text-slate-600">{{ fmtMoney(rec.total_assets) }}</td>
          <td class="px-5 py-3 text-right text-danger-600">{{ fmtMoney(rec.total_liabilities) }}</td>
          <td class="px-5 py-3 text-right font-semibold"
            :class="rec.net_worth >= 0 ? 'text-success-700' : 'text-danger-600'">
            {{ fmtMoney(rec.net_worth) }}
          </td>
          <td class="px-5 py-3">
            <AppBadge :color="statusBadge(rec.status)">{{ rec.status_label }}</AppBadge>
          </td>
          <td class="px-5 py-3 text-right">
            <div class="inline-flex gap-1">
              <Link :href="route('saln.show', rec.id)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                title="View">
                <EyeIcon class="h-4 w-4" />
              </Link>
              <AppIconButton v-if="rec.status === 'approved'" label="Mark as Filed" variant="success" @click="doFile(rec)">
                <ArchiveBoxArrowDownIcon class="h-4 w-4" />
              </AppIconButton>
              <a v-if="rec.status === 'approved' || rec.status === 'filed'"
                :href="route('saln.pdf', rec.id)" target="_blank"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                title="Export PDF">
                <DocumentArrowDownIcon class="h-4 w-4" />
              </a>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="rec in records.data" :key="rec.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <Link :href="route('saln.hr.employee', rec.user?.id)" class="font-medium text-indigo-600 hover:text-indigo-800">
                  {{ rec.user?.name }}
                </Link>
                <p class="text-xs text-slate-400">{{ rec.user?.employee_id ?? rec.user?.email }} &middot; {{ rec.year }}</p>
              </div>
              <AppBadge :color="statusBadge(rec.status)">{{ rec.status_label }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>Assets {{ fmtMoney(rec.total_assets) }} &middot; Liabilities {{ fmtMoney(rec.total_liabilities) }}</span>
            </div>
            <div class="flex items-center justify-between pt-1">
              <span class="font-semibold" :class="rec.net_worth >= 0 ? 'text-success-700' : 'text-danger-600'">
                Net {{ fmtMoney(rec.net_worth) }}
              </span>
              <div class="inline-flex gap-1">
                <Link :href="route('saln.show', rec.id)"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                  title="View">
                  <EyeIcon class="h-4 w-4" />
                </Link>
                <AppIconButton v-if="rec.status === 'approved'" label="Mark as Filed" variant="success" @click="doFile(rec)">
                  <ArchiveBoxArrowDownIcon class="h-4 w-4" />
                </AppIconButton>
                <a v-if="rec.status === 'approved' || rec.status === 'filed'"
                  :href="route('saln.pdf', rec.id)" target="_blank"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                  title="Export PDF">
                  <DocumentArrowDownIcon class="h-4 w-4" />
                </a>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No SALN records found" subtitle="Try adjusting your filters." />
        </template>

        <template #footer>
          <PaginationControl
            :links="records.links"
            :current-page="records.current_page"
            :total-pages="records.last_page"
            :total="records.total" />
        </template>
      </AppTable>

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
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import {
  CheckCircleIcon, EyeIcon,
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
  draft:        'slate',
  submitted:    'blue',
  under_review: 'amber',
  approved:     'green',
  returned:     'red',
  filed:        'indigo',
}[s] ?? 'slate')

const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
