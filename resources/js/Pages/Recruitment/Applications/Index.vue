<script setup>
import { ref, watch } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

// ── Stage colors ───────────────────────────────────────────────────────────────
const stageColors = {
  submitted:  'bg-gray-100 text-gray-600',
  screening:  'bg-yellow-100 text-yellow-700',
  exam:       'bg-orange-100 text-orange-700',
  interview:  'bg-purple-100 text-purple-700',
  ranking:    'bg-blue-100 text-blue-700',
  selection:  'bg-indigo-100 text-indigo-700',
  placement:  'bg-green-100 text-green-700',
  rejected:   'bg-red-100 text-red-600',
  withdrawn:  'bg-gray-100 text-gray-400',
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
    confirmButtonColor: '#ef4444',
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
    <div>
      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">
        {{ page.props.flash.success }}
      </div>
      <div v-if="page.props.flash?.error" class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-100 text-red-600 text-sm">
        {{ page.props.flash.error }}
      </div>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Applications</h1>
        <span class="text-sm text-slate-500">{{ applications.total ?? 0 }} total</span>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
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
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto rounded-xl">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Applicant</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Position</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Applied</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rank</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Stage</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="app in applications.data" :key="app.id" class="hover:bg-slate-50/60">
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
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize" :class="stageColors[app.current_stage]">
                    {{ app.current_stage }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1 justify-center">
                    <Link :href="route('recruitment.applications.show', app.id)"
                          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                      View
                    </Link>
                    <button v-if="!terminalStages.includes(app.current_stage)"
                            @click="quickReject(app)"
                            class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                      Reject
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="applications.data?.length === 0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">No applications found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="applications.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click="goToPage(applications.current_page - 1)"
                  :disabled="applications.current_page === 1 || isLoading"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Prev</button>
          <span>Page {{ applications.current_page }} of {{ applications.last_page }}</span>
          <button @click="goToPage(applications.current_page + 1)"
                  :disabled="applications.current_page === applications.last_page || isLoading"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
