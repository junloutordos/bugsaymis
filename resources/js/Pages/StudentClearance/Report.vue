<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <Link :href="route('student-clearance.index', { period_id: period.id })" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-indigo-700">
            <ArrowLeftIcon class="h-4 w-4" />
            Back to clearance dashboard
          </Link>
          <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-indigo-600">Registrar</p>
          <h1 class="text-2xl font-semibold text-slate-900">{{ period.title }}</h1>
          <p class="mt-1 text-sm text-slate-500">S.Y. {{ period.school_year_name }}</p>
        </div>
        <a :href="route('student-clearance.export', period.id)" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
          <ArrowDownTrayIcon class="h-4 w-4" />
          Export CSV
        </a>
      </div>

      <section class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</p>
          <p class="mt-2 text-3xl font-semibold text-slate-900">{{ stats?.total ?? 0 }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cleared</p>
          <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ stats?.cleared ?? 0 }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</p>
          <p class="mt-2 text-3xl font-semibold text-amber-600">{{ stats?.pending ?? 0 }}</p>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Status breakdown</h2>
          </div>
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Students</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in byStatus" :key="row.status">
                <td class="px-4 py-3 capitalize text-slate-700">{{ statusLabel(row.status) }}</td>
                <td class="px-4 py-3 text-right font-medium text-slate-900">{{ row.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Completion by section</h2>
          </div>
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Section</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Cleared</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in bySection" :key="`${row.grade_level}-${row.section_name}`">
                <td class="px-4 py-3 text-slate-700">Grade {{ row.grade_level }} {{ row.section_name ?? 'Unassigned' }}</td>
                <td class="px-4 py-3 text-right text-slate-700">{{ row.total }}</td>
                <td class="px-4 py-3 text-right font-medium text-emerald-700">{{ row.cleared }}</td>
                <td class="px-4 py-3 text-right font-medium text-amber-700">{{ row.pending }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3">
          <h2 class="text-sm font-semibold text-slate-900">Requirement bottlenecks</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
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
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in byRequirement" :key="`${row.requirement_group}-${row.requirement_label}`">
                <td class="px-4 py-3 font-medium text-slate-800">{{ row.requirement_label }}</td>
                <td class="px-4 py-3 capitalize text-slate-500">{{ row.requirement_group }}</td>
                <td class="px-4 py-3 text-right text-slate-700">{{ row.total }}</td>
                <td class="px-4 py-3 text-right text-emerald-700">{{ row.completed }}</td>
                <td class="px-4 py-3 text-right text-slate-700">{{ row.pending }}</td>
                <td class="px-4 py-3 text-right text-amber-700">{{ row.hold }}</td>
                <td class="px-4 py-3 text-right text-rose-700">{{ row.returned }}</td>
                <td class="px-4 py-3 text-right text-slate-700">{{ row.blockers }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
