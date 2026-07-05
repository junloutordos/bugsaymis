<script setup>
import { ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

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

function statusBadgeColor (status) {
  const map = { pending: 'amber', active: 'green', completed: 'blue', terminated: 'red' }
  return map[status] ?? 'slate'
}

const formatDate = (iso) => iso
  ? new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'
</script>

<template>
  <Head title="Placements — Recruitment" />
  <AdminLayout title="Placements & Onboarding">
    <div class="space-y-5">

      <AppPageHeader title="Placements & Onboarding" subtitle="Track onboarding progress for placed applicants." />

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ page.props.flash.success }}
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="active">Active</option>
          <option value="completed">Completed</option>
          <option value="terminated">Terminated</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :loading="isLoading" :is-empty="!placements.data?.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Position</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Office</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Start Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="p in placements.data" :key="p.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-slate-700">{{ p.id }}</td>
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800">
              {{ p.application?.applicant?.last_name }}, {{ p.application?.applicant?.first_name }}
            </div>
            <div class="text-xs text-slate-400">{{ p.application?.applicant?.email }}</div>
          </td>
          <td class="px-4 py-3 text-slate-700">
            {{ p.application?.job_vacancy?.job_item?.position_title ?? '—' }}
            <div class="text-xs text-slate-400">{{ p.application?.job_vacancy?.job_item?.recruitment_type?.name ?? '' }}</div>
          </td>
          <td class="px-4 py-3 text-slate-700">{{ p.office?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ formatDate(p.start_date) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusBadgeColor(p.status)" class="capitalize">{{ p.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <AppButton as="link" size="sm" :href="route('recruitment.placements.show', p.id)">
              Onboarding &rarr;
            </AppButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="p in placements.data" :key="p.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">
                  {{ p.application?.applicant?.last_name }}, {{ p.application?.applicant?.first_name }}
                </p>
                <p class="text-xs text-slate-400">{{ p.application?.applicant?.email }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(p.status)" class="capitalize">{{ p.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">
              {{ p.application?.job_vacancy?.job_item?.position_title ?? '—' }} &middot; {{ p.office?.name ?? '—' }}
            </p>
            <p class="text-xs text-slate-400">Start {{ formatDate(p.start_date) }}</p>
            <div class="pt-1">
              <AppButton as="link" size="sm" :href="route('recruitment.placements.show', p.id)">
                Onboarding &rarr;
              </AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No placements found" />
        </template>

        <template #footer>
          <PaginationControl :links="placements.links" :current-page="placements.current_page" :total-pages="placements.last_page" :total="placements.total" />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
