<script setup>
import { ref, watch } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'

const props = defineProps({
  placements: { type: Object, required: true },
  filters:    { type: Object, default: () => ({}) },
})

const page = usePage()

const status    = ref(props.filters?.status ?? '')
const isLoading = ref(false)

const applyFilters = () => {
  isLoading.value = true
  router.get(route('recruitment.placements.index'), {
    status: status.value || undefined,
  }, {
    preserveState: true, replace: true,
    only: ['placements', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

watch(status, applyFilters)

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('recruitment.placements.index'), {
    status: status.value || undefined,
    page: p,
  }, {
    preserveState: true, replace: true,
    only: ['placements', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const statusColors = {
  pending:    'bg-yellow-100 text-yellow-700',
  active:     'bg-green-100 text-green-700',
  completed:  'bg-blue-100 text-blue-700',
  terminated: 'bg-red-100 text-red-600',
}

const formatDate = (iso) => iso
  ? new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'
</script>

<template>
  <Head title="Placements — Recruitment" />
  <AdminLayout title="Placements & Onboarding">
    <div>
      <div v-if="page.props.flash?.success" class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Placements &amp; Onboarding</h1>
        <span v-if="isLoading" class="text-sm text-slate-400">Loading…</span>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <select v-model="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="active">Active</option>
          <option value="completed">Completed</option>
          <option value="terminated">Terminated</option>
        </select>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto rounded-xl">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Position</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Office</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Start Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="p in placements.data" :key="p.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <div class="font-medium text-slate-800">
                    {{ p.application?.applicant?.last_name }}, {{ p.application?.applicant?.first_name }}
                  </div>
                  <div class="text-xs text-slate-400">{{ p.application?.applicant?.email }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  {{ p.application?.job_vacancy?.job_item?.position_title ?? '—' }}
                  <div class="text-xs text-slate-400">{{ p.application?.job_vacancy?.job_item?.recruitment_type?.name ?? '' }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.office?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ formatDate(p.start_date) }}</td>
                <td class="px-4 py-3">
                  <span :class="[badgeBase, statusBadgeClass(p.status), 'capitalize']">{{ p.status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <Link :href="route('recruitment.placements.show', p.id)"
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                    Onboarding &rarr;
                  </Link>
                </td>
              </tr>
              <tr v-if="placements.data?.length === 0">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No placements found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="placements.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click="goToPage(placements.current_page - 1)"
                  :disabled="placements.current_page === 1 || isLoading"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Prev</button>
          <span>Page {{ placements.current_page }} of {{ placements.last_page }}</span>
          <button @click="goToPage(placements.current_page + 1)"
                  :disabled="placements.current_page === placements.last_page || isLoading"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
