<script setup>
import { computed, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import { ArrowRightIcon, CheckBadgeIcon, ExclamationTriangleIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({ employees: { type: Array, default: () => [] } })

const search = ref('')
const quality = ref('all')
const currentPage = ref(1)
const PER_PAGE = 15

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  return props.employees.filter((employee) => {
    const matchesTerm = !term || [employee.name, employee.employee_no, employee.position, employee.division, employee.office]
      .some((value) => String(value ?? '').toLowerCase().includes(term))
    const matchesQuality = quality.value === 'all'
      || (quality.value === 'ready' && employee.quality_score === 100)
      || (quality.value === 'review' && employee.quality_score > 0 && employee.quality_score < 100)
      || (quality.value === 'empty' && employee.entries_count === 0)
    return matchesTerm && matchesQuality
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => filtered.value.slice((currentPage.value - 1) * PER_PAGE, currentPage.value * PER_PAGE))
const readyCount = computed(() => props.employees.filter((employee) => employee.quality_score === 100).length)
const reviewCount = computed(() => props.employees.filter((employee) => employee.warnings.length > 0).length)
const emptyCount = computed(() => props.employees.filter((employee) => employee.entries_count === 0).length)

watch([search, quality], () => { currentPage.value = 1 })

function qualityColor(score) {
  if (score === 100) return 'green'
  if (score >= 70) return 'amber'
  return score > 0 ? 'orange' : 'slate'
}
</script>

<template>
  <Head title="Service Records" />
  <AdminLayout title="Service Records">
    <div class="mx-auto max-w-7xl">
      <AppPageHeader
        title="Service Record Registry"
        subtitle="Authoritative, evidence-backed government service histories aligned with CSC appointment rules."
        hero
      />

      <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <AppCard>
          <div class="flex items-center gap-3">
            <div class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600"><CheckBadgeIcon class="h-6 w-6" /></div>
            <div><p class="text-2xl font-semibold text-slate-900">{{ readyCount }}</p><p class="text-xs text-slate-500">Complete records</p></div>
          </div>
        </AppCard>
        <AppCard>
          <div class="flex items-center gap-3">
            <div class="rounded-xl bg-amber-50 p-2.5 text-amber-600"><ExclamationTriangleIcon class="h-6 w-6" /></div>
            <div><p class="text-2xl font-semibold text-slate-900">{{ reviewCount }}</p><p class="text-xs text-slate-500">Need HR review</p></div>
          </div>
        </AppCard>
        <AppCard>
          <div class="flex items-center gap-3">
            <div class="rounded-xl bg-slate-100 p-2.5 text-slate-500"><MagnifyingGlassIcon class="h-6 w-6" /></div>
            <div><p class="text-2xl font-semibold text-slate-900">{{ emptyCount }}</p><p class="text-xs text-slate-500">Not yet encoded</p></div>
          </div>
        </AppCard>
      </div>

      <AppCard :padded="false">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="relative w-full sm:max-w-md">
            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input v-model="search" class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Search employee, position, office…">
          </div>
          <select v-model="quality" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="all">All record quality</option>
            <option value="ready">Complete</option>
            <option value="review">Needs review</option>
            <option value="empty">Not encoded</option>
          </select>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[920px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr><th class="px-5 py-3">Employee</th><th class="px-4 py-3">Current appointment</th><th class="px-4 py-3">Record status</th><th class="px-4 py-3">Credited days</th><th class="px-4 py-3">Quality</th><th class="px-5 py-3 text-right"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="employee in displayed" :key="employee.id" class="transition-colors hover:bg-slate-50/70">
                <td class="px-5 py-4">
                  <p class="font-medium text-slate-900">{{ employee.name }}</p>
                  <p class="mt-0.5 text-xs text-slate-500">{{ employee.employee_no || 'No employee number' }} · {{ employee.division || employee.office || 'Unit not assigned' }}</p>
                </td>
                <td class="px-4 py-4"><p class="text-slate-700">{{ employee.current_appointment || employee.position || '—' }}</p><p class="text-xs capitalize text-slate-500">{{ employee.current_status?.replaceAll('_', ' ') || 'No active verified entry' }}</p></td>
                <td class="px-4 py-4"><p class="text-slate-700">{{ employee.verified_entries }} verified · {{ employee.draft_entries }} draft</p><p v-if="employee.warnings.length" class="mt-0.5 text-xs text-amber-600">{{ employee.warnings.length }} item{{ employee.warnings.length === 1 ? '' : 's' }} to review</p></td>
                <td class="px-4 py-4 font-medium text-slate-700">{{ Number(employee.credited_calendar_days).toLocaleString('en-PH') }}</td>
                <td class="px-4 py-4"><AppBadge :color="qualityColor(employee.quality_score)">{{ employee.quality_score }}%</AppBadge></td>
                <td class="px-5 py-4 text-right"><AppButton as="link" :href="route('hr.service-records.show', employee.id)" variant="secondary" size="sm">Open <ArrowRightIcon class="h-3.5 w-3.5" /></AppButton></td>
              </tr>
              <tr v-if="displayed.length === 0"><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-500">No employees match the selected filters.</td></tr>
            </tbody>
          </table>
        </div>

        <div v-if="filtered.length > PER_PAGE" class="border-t border-slate-100 px-5 py-4">
          <PaginationControl :current-page="currentPage" :total-pages="totalPages" :total="filtered.length" @prev="currentPage--" @next="currentPage++" @page="currentPage = $event" />
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
