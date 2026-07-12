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
import Swal from 'sweetalert2'

const props = defineProps({
  applications:     { type: Object, required: true },
  recruitmentTypes: { type: Array,  default: () => [] },
  stages:           { type: Array,  default: () => [] },
  filters:          { type: Object, default: () => ({}) },
})

const page = usePage()

// ── Filters ────────────────────────────────────────────────────────────────────
const search    = ref(props.filters?.search    ?? '')
const stage     = ref(props.filters?.stage     ?? '')
const typeId    = ref(props.filters?.type_id   ?? '')
const vacancyId = ref(props.filters?.vacancy_id?? '')
const isLoading = ref(false)
let debounceTimer = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('recruitment.applications.index'), {
      search:     search.value     || undefined,
      stage:      stage.value      || undefined,
      type_id:    typeId.value     || undefined,
      vacancy_id: vacancyId.value  || undefined,
    }, {
      preserveState: true, replace: true,
      only: ['applications', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))
watch([stage, typeId], () => applyFilters(true))

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('recruitment.applications.index'), {
    search:     search.value     || undefined,
    stage:      stage.value      || undefined,
    type_id:    typeId.value     || undefined,
    vacancy_id: vacancyId.value  || undefined,
    page: p,
  }, {
    preserveState: true, replace: true,
    only: ['applications', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

// ── Stage colors (AppBadge color keywords) ──────────────────────────────────────
const stageColors = {
  submitted:  'slate',
  screening:  'amber',
  exam:       'orange',
  interview:  'purple',
  ranking:    'blue',
  selection:  'indigo',
  placement:  'green',
  rejected:   'red',
  withdrawn:  'slate',
}

// ── Quick reject ───────────────────────────────────────────────────────────────
const quickReject = async (app) => {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: 'Reject Application',
    input: 'textarea',
    inputLabel: 'Reason for rejection',
    inputPlaceholder: 'Enter reason…',
    inputAttributes: { required: true },
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Reject',
    reverseButtons: true,
  })
  if (!isConfirmed || !reason) return

  router.patch(route('recruitment.applications.reject', app.id), { reason }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Rejected', timer: 1200, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

const formatDate = (iso) => iso
  ? new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

const formatDateTime = (iso) => iso
  ? new Date(iso).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
  : '—'

// Active stages that can still be processed
const terminalStages = ['rejected', 'withdrawn', 'placement']
</script>

<template>
  <Head title="Applications — Recruitment" />
  <AdminLayout title="Applications">
    <div class="space-y-5">

      <AppPageHeader title="Applications" :subtitle="`${applications.total ?? 0} total`" />

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ page.props.flash.success }}
      </div>
      <div v-if="page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ page.props.flash.error }}
      </div>

      <!-- Filter bar -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-[180px] sm:max-w-xs">
          <input v-model="search" type="text" placeholder="Search applicant name…"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">…</span>
        </div>
        <select v-model="stage" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Stages</option>
          <option v-for="s in stages" :key="s" :value="s" class="capitalize">{{ s }}</option>
        </select>
        <select v-model="typeId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Types</option>
          <option v-for="t in recruitmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!applications.data?.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Applicant</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Position</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Applied</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Rank</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Stage</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="app in applications.data" :key="app.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ app.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <div class="font-medium text-slate-800">
              {{ app.applicant?.last_name }}, {{ app.applicant?.first_name }}
            </div>
            <div v-if="app.is_internal" class="text-xs text-indigo-500">Internal</div>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">
            {{ app.job_vacancy?.job_item?.position_title ?? '—' }}
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">
            {{ app.job_vacancy?.job_item?.recruitment_type?.name ?? '—' }}
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ formatDateTime(app.created_at) }}</td>
          <td class="px-4 py-3 text-center">
            <span v-if="app.ranking_summary?.rank" class="font-bold text-indigo-600">#{{ app.ranking_summary.rank }}</span>
            <span v-else class="text-slate-300">—</span>
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="stageColors[app.current_stage] ?? 'slate'" class="capitalize">{{ app.current_stage }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-2 justify-center">
              <AppButton as="link" size="sm" :href="route('recruitment.applications.show', app.id)">View</AppButton>
              <AppButton v-if="!terminalStages.includes(app.current_stage)" size="sm" variant="danger" @click="quickReject(app)">Reject</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="app in applications.data" :key="app.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-400">#{{ app.id }}</p>
                <p class="font-medium text-slate-800">{{ app.applicant?.last_name }}, {{ app.applicant?.first_name }}</p>
                <p v-if="app.is_internal" class="text-xs text-indigo-500">Internal</p>
                <p class="text-xs text-slate-500">{{ app.job_vacancy?.job_item?.position_title ?? '—' }}</p>
              </div>
              <AppBadge :color="stageColors[app.current_stage] ?? 'slate'" class="capitalize">{{ app.current_stage }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>{{ app.job_vacancy?.job_item?.recruitment_type?.name ?? '—' }}</span>
              <span v-if="app.ranking_summary?.rank" class="font-bold text-indigo-600">#{{ app.ranking_summary.rank }}</span>
            </div>
            <p class="text-xs text-slate-400">Applied {{ formatDateTime(app.created_at) }}</p>
            <div class="flex items-center gap-2 pt-1">
              <AppButton as="link" size="sm" :href="route('recruitment.applications.show', app.id)">View</AppButton>
              <AppButton v-if="!terminalStages.includes(app.current_stage)" size="sm" variant="danger" @click="quickReject(app)">Reject</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No applications found" />
        </template>

        <template #footer>
          <PaginationControl
            :links="applications.links"
            :current-page="applications.current_page"
            :total-pages="applications.last_page"
            :total="applications.total"
            @page="goToPage"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
