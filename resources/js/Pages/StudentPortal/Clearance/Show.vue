<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { CheckCircleIcon, ClockIcon, ExclamationTriangleIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  student: Object,
  period: Object,
  clearance: Object,
})

const groups = computed(() => {
  const labels = {
    subject: 'Subject Teachers',
    laboratory: 'Laboratories',
    administrative: 'Offices',
    final: 'Adviser / Registrar',
  }

  return Object.entries(labels).map(([key, label]) => ({
    key,
    label,
    items: (props.clearance?.items ?? []).filter(item => item.requirement_group === key),
  })).filter(group => group.items.length > 0)
})

const progress = computed(() => {
  const items = props.clearance?.items ?? []
  const done = items.filter(item => ['cleared', 'waived', 'not_applicable'].includes(item.status)).length
  return {
    done,
    total: items.length,
    percent: items.length ? Math.round((done / items.length) * 100) : 0,
  }
})

function statusClass(status) {
  return {
    cleared: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    waived: 'bg-blue-50 text-blue-700 border-blue-200',
    not_applicable: 'bg-slate-50 text-slate-600 border-slate-200',
    hold: 'bg-amber-50 text-amber-700 border-amber-200',
    returned: 'bg-red-50 text-red-700 border-red-200',
    pending: 'bg-slate-50 text-slate-600 border-slate-200',
  }[status] ?? 'bg-slate-50 text-slate-600 border-slate-200'
}

function statusLabel(status) {
  return {
    not_applicable: 'Not applicable',
    hold: 'With accountability',
  }[status] ?? String(status ?? 'pending').replaceAll('_', ' ')
}

function statusIcon(status) {
  if (['cleared', 'waived', 'not_applicable'].includes(status)) return CheckCircleIcon
  if (['hold', 'returned'].includes(status)) return ExclamationTriangleIcon
  return ClockIcon
}
</script>

<template>
  <Head title="Year-End Clearance" />

  <StudentPortalLayout title="Year-End Clearance">
    <div class="space-y-5">
      <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Year-End Clearance</p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ period?.title ?? 'No active clearance period' }}</h1>
            <p class="mt-1 text-sm text-slate-500">
              {{ student.full_name }} <span v-if="clearance">/ Grade {{ clearance.grade_level }} {{ clearance.section_name }}</span>
            </p>
          </div>
          <div v-if="clearance" class="rounded-lg border border-slate-200 px-4 py-3 text-right">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Progress</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ progress.done }}/{{ progress.total }}</p>
          </div>
        </div>

        <div v-if="clearance" class="mt-5">
          <div class="h-2 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-indigo-600" :style="{ width: `${progress.percent}%` }"></div>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="rounded-full border px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(clearance.status)">
              {{ statusLabel(clearance.status) }}
            </span>
            <span v-if="clearance.finalized_at" class="text-xs text-slate-500">Finalized {{ clearance.finalized_at }}</span>
          </div>
        </div>
      </section>

      <section v-if="!period" class="rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm">
        <XCircleIcon class="mx-auto h-10 w-10 text-slate-300" />
        <h2 class="mt-3 text-base font-semibold text-slate-900">No clearance period is available</h2>
      </section>

      <section v-else-if="!clearance" class="rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm">
        <ClockIcon class="mx-auto h-10 w-10 text-slate-300" />
        <h2 class="mt-3 text-base font-semibold text-slate-900">Your clearance has not been generated yet</h2>
      </section>

      <section v-for="group in groups" :key="group.key" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3">
          <h2 class="text-sm font-semibold text-slate-900">{{ group.label }}</h2>
        </div>
        <div class="divide-y divide-slate-100">
          <div v-for="item in group.items" :key="item.id" class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_auto]">
            <div>
              <div class="flex items-center gap-2">
                <component :is="statusIcon(item.status)" class="h-5 w-5 text-slate-400" />
                <p class="text-sm font-medium text-slate-900">{{ item.requirement_label }}</p>
              </div>
              <p v-if="item.assigned_to" class="mt-1 text-xs text-slate-500">{{ item.assigned_to }}</p>
              <p v-if="item.accountability" class="mt-2 text-sm text-amber-700">{{ item.accountability }}</p>
              <p v-if="item.remarks" class="mt-1 text-sm text-slate-500">{{ item.remarks }}</p>
            </div>
            <div class="sm:text-right">
              <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(item.status)">
                {{ statusLabel(item.status) }}
              </span>
              <p v-if="item.signed_by" class="mt-1 text-xs text-slate-500">{{ item.signed_by }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>
  </StudentPortalLayout>
</template>
