<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import { ArrowDownTrayIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

defineProps({
  period: Object,
  stats: Object,
  byStatus: Array,
  bySection: Array,
  byRequirement: Array,
})

function statusLabel(status) {
  return String(status ?? '').replaceAll('_', ' ')
}
</script>

<template>
  <Head :title="`Clearance Report - ${period.title}`" />

  <AdminLayout title="Student Clearance Report">
    <div class="space-y-6">
      <Link :href="route('student-clearance.index', { period_id: period.id })" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-indigo-700">
        <ArrowLeftIcon class="h-4 w-4" />
        Back to clearance dashboard
      </Link>

      <AppPageHeader :title="period.title" :subtitle="`S.Y. ${period.school_year_name}`">
        <template #actions>
          <AppButton as="a" :href="route('student-clearance.export', period.id)">
            <ArrowDownTrayIcon class="h-4 w-4" />
            Export CSV
          </AppButton>
        </template>
      </AppPageHeader>

      <section class="grid gap-4 sm:grid-cols-3">
        <AppCard>
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</p>
          <p class="mt-2 text-3xl font-semibold text-slate-900">{{ stats?.total ?? 0 }}</p>
        </AppCard>
        <AppCard>
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cleared</p>
          <p class="mt-2 text-3xl font-semibold text-success-600">{{ stats?.cleared ?? 0 }}</p>
        </AppCard>
        <AppCard>
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</p>
          <p class="mt-2 text-3xl font-semibold text-warning-600">{{ stats?.pending ?? 0 }}</p>
        </AppCard>
      </section>

      <section class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
        <AppCard title="Status breakdown" :padded="false">
          <AppTable :is-empty="!byStatus.length" :skeleton-cols="2" :card="false">
            <template #head>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Students</th>
              </tr>
            </template>
            <tr v-for="row in byStatus" :key="row.status">
              <td class="px-4 py-3 capitalize text-slate-700">{{ statusLabel(row.status) }}</td>
              <td class="px-4 py-3 text-right font-medium text-slate-900">{{ row.total }}</td>
            </tr>
          </AppTable>
        </AppCard>

        <AppCard title="Completion by section" :padded="false">
          <AppTable :is-empty="!bySection.length" :skeleton-cols="4" :card="false">
            <template #head>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Section</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Cleared</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</th>
              </tr>
            </template>
            <tr v-for="row in bySection" :key="`${row.grade_level}-${row.section_name}`">
              <td class="px-4 py-3 text-slate-700">Grade {{ row.grade_level }} {{ row.section_name ?? 'Unassigned' }}</td>
              <td class="px-4 py-3 text-right text-slate-700">{{ row.total }}</td>
              <td class="px-4 py-3 text-right font-medium text-success-700">{{ row.cleared }}</td>
              <td class="px-4 py-3 text-right font-medium text-warning-700">{{ row.pending }}</td>
            </tr>
          </AppTable>
        </AppCard>
      </section>

      <AppCard title="Requirement bottlenecks" :padded="false">
        <AppTable :is-empty="!byRequirement.length" :skeleton-cols="8" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Requirement</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Group</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Completed</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Hold</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Returned</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Blockers</th>
            </tr>
          </template>
          <tr v-for="row in byRequirement" :key="`${row.requirement_group}-${row.requirement_label}`">
            <td class="px-4 py-3 font-medium text-slate-800">{{ row.requirement_label }}</td>
            <td class="px-4 py-3 capitalize text-slate-500">{{ row.requirement_group }}</td>
            <td class="px-4 py-3 text-right text-slate-700">{{ row.total }}</td>
            <td class="px-4 py-3 text-right text-success-700">{{ row.completed }}</td>
            <td class="px-4 py-3 text-right text-slate-700">{{ row.pending }}</td>
            <td class="px-4 py-3 text-right text-warning-700">{{ row.hold }}</td>
            <td class="px-4 py-3 text-right text-danger-600">{{ row.returned }}</td>
            <td class="px-4 py-3 text-right text-slate-700">{{ row.blockers }}</td>
          </tr>
        </AppTable>
      </AppCard>
    </div>
  </AdminLayout>
</template>
