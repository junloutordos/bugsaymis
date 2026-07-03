<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowPathIcon, CheckCircleIcon, ClipboardDocumentCheckIcon, ClockIcon, PlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  schoolYears: Array,
  periods: Array,
  period: Object,
  filters: Object,
  stats: Object,
  clearances: Array,
})

const selectedPeriodId = ref(props.filters?.period_id)
watch(selectedPeriodId, (period_id) => {
  router.get(route('student-clearance.index'), { period_id }, { preserveState: true })
})

const periodForm = useForm({
  school_year_id: props.schoolYears?.find(sy => sy.is_current)?.id ?? props.schoolYears?.[0]?.id,
  title: 'Year-End Clearance',
  opens_at: '',
  closes_at: '',
  status: 'draft',
  target_grade_levels: [7, 8, 9, 10, 11, 12],
})

function createPeriod() {
  periodForm.post(route('student-clearance.periods.store'), { preserveScroll: true })
}

function generate() {
  if (!props.period) return
  router.post(route('student-clearance.periods.generate', props.period.id), {}, { preserveScroll: true })
}

function statusClass(status) {
  return {
    cleared: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    ready_for_adviser: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    pending_registrar: 'bg-blue-50 text-blue-700 border-blue-200',
    with_accountability: 'bg-amber-50 text-amber-700 border-amber-200',
    in_progress: 'bg-slate-50 text-slate-600 border-slate-200',
    open: 'bg-slate-50 text-slate-600 border-slate-200',
  }[status] ?? 'bg-slate-50 text-slate-600 border-slate-200'
}

function statusLabel(status) {
  return String(status ?? '').replaceAll('_', ' ')
}
</script>

<template>
  <Head title="Student Clearance" />

  <AdminLayout title="Student Clearance">
    <div class="space-y-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Registrar</p>
          <h1 class="text-2xl font-semibold text-slate-900">Student Year-End Clearance</h1>
        </div>
        <Link :href="route('student-clearance.queue')" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
          <ClipboardDocumentCheckIcon class="h-4 w-4" />
          Signatory Queue
        </Link>
      </div>

      <section class="grid gap-4 lg:grid-cols-[1fr_1.5fr]">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-900">Create clearance period</h2>
          <div class="mt-4 grid gap-3">
            <select v-model="periodForm.school_year_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">{{ sy.name }}</option>
            </select>
            <input v-model="periodForm.title" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <div class="grid gap-3 sm:grid-cols-2">
              <input v-model="periodForm.opens_at" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <input v-model="periodForm.closes_at" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <select v-model="periodForm.status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="draft">Draft</option>
              <option value="open">Open</option>
              <option value="closed">Closed</option>
            </select>
            <button @click="createPeriod" :disabled="periodForm.processing" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
              <PlusIcon class="h-4 w-4" />
              Create Period
            </button>
          </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-sm font-semibold text-slate-900">Active records</h2>
              <p class="mt-1 text-sm text-slate-500">{{ period?.title ?? 'Select a clearance period' }}</p>
            </div>
            <select v-model="selectedPeriodId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option :value="null">Latest period</option>
              <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.title }} / {{ p.school_year?.name }}</option>
            </select>
          </div>

          <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-slate-200 p-4">
              <ClockIcon class="h-5 w-5 text-slate-400" />
              <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats?.total ?? 0 }}</p>
              <p class="text-xs text-slate-500">Total</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-4">
              <CheckCircleIcon class="h-5 w-5 text-emerald-500" />
              <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats?.cleared ?? 0 }}</p>
              <p class="text-xs text-slate-500">Cleared</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-4">
              <ArrowPathIcon class="h-5 w-5 text-indigo-500" />
              <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats?.pending ?? 0 }}</p>
              <p class="text-xs text-slate-500">Pending</p>
            </div>
          </div>

          <button v-if="period" @click="generate" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            <ArrowPathIcon class="h-4 w-4" />
            Generate / Sync Clearances
          </button>
        </div>
      </section>

      <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3">
          <h2 class="text-sm font-semibold text-slate-900">Students</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Section</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Progress</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="clearance in clearances" :key="clearance.id" class="hover:bg-slate-50">
                <td class="px-4 py-3">
                  <Link :href="route('student-clearance.show', clearance.id)" class="font-medium text-indigo-700 hover:text-indigo-900">{{ clearance.student_name }}</Link>
                  <p class="text-xs text-slate-500">{{ clearance.pisays_id }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600">Grade {{ clearance.grade_level }} {{ clearance.section_name }}</td>
                <td class="px-4 py-3 text-slate-600">{{ clearance.progress.done }}/{{ clearance.progress.total }}</td>
                <td class="px-4 py-3">
                  <span class="rounded-full border px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(clearance.status)">
                    {{ statusLabel(clearance.status) }}
                  </span>
                </td>
              </tr>
              <tr v-if="clearances.length === 0">
                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No clearances generated.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
