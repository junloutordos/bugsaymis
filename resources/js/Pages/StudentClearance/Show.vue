<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { ArrowLeftIcon, CheckCircleIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
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
    items: props.clearance.items.filter(item => item.requirement_group === key),
  })).filter(group => group.items.length > 0)
})

const progress = computed(() => {
  const done = props.clearance.items.filter(item => ['cleared', 'waived', 'not_applicable'].includes(item.status)).length
  return { done, total: props.clearance.items.length }
})

function statusClass(status) {
  return {
    cleared: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    waived: 'bg-blue-50 text-blue-700 border-blue-200',
    not_applicable: 'bg-slate-50 text-slate-600 border-slate-200',
    hold: 'bg-amber-50 text-amber-700 border-amber-200',
    returned: 'bg-red-50 text-red-700 border-red-200',
    pending_registrar: 'bg-blue-50 text-blue-700 border-blue-200',
    ready_for_adviser: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    with_accountability: 'bg-amber-50 text-amber-700 border-amber-200',
    pending: 'bg-slate-50 text-slate-600 border-slate-200',
    in_progress: 'bg-slate-50 text-slate-600 border-slate-200',
    open: 'bg-slate-50 text-slate-600 border-slate-200',
  }[status] ?? 'bg-slate-50 text-slate-600 border-slate-200'
}

function statusLabel(status) {
  return {
    not_applicable: 'Not applicable',
    hold: 'With accountability',
  }[status] ?? String(status ?? '').replaceAll('_', ' ')
}

function statusBadgeColor(status) {
  return {
    cleared: 'green',
    waived: 'blue',
    not_applicable: 'slate',
    hold: 'amber',
    returned: 'red',
    pending_registrar: 'blue',
    ready_for_adviser: 'indigo',
    with_accountability: 'amber',
    pending: 'slate',
    in_progress: 'slate',
    open: 'slate',
  }[status] ?? 'slate'
}

function adviserReview() {
  router.post(route('student-clearance.adviser-review', props.clearance.id), {}, { preserveScroll: true })
}

function finalizeClearance() {
  router.post(route('student-clearance.finalize', props.clearance.id), {}, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Clearance - ${clearance.student_name}`" />

  <AdminLayout title="Student Clearance">
    <div class="space-y-6">
      <Link :href="route('student-clearance.index')" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-indigo-700">
        <ArrowLeftIcon class="h-4 w-4" />
        Back to clearance dashboard
      </Link>

      <AppCard>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ clearance.period.title }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ clearance.student_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
              {{ clearance.pisays_id }} / Grade {{ clearance.grade_level }} {{ clearance.section_name }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <AppBadge :color="statusBadgeColor(clearance.status)">{{ statusLabel(clearance.status) }}</AppBadge>
            <AppButton variant="secondary" @click="adviserReview">
              <ClipboardDocumentCheckIcon class="h-4 w-4" />
              Adviser Review
            </AppButton>
            <AppButton as="a" variant="secondary" :href="route('student-clearance.pdf', clearance.id)" target="_blank">
              PDF
            </AppButton>
            <AppButton @click="finalizeClearance">
              <CheckCircleIcon class="h-4 w-4" />
              Finalize
            </AppButton>
          </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
          <div class="rounded-lg border border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Progress</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ progress.done }}/{{ progress.total }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Adviser</p>
            <p class="mt-1 text-sm font-medium text-slate-900">{{ clearance.adviser_name ?? 'Unassigned' }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">School Year</p>
            <p class="mt-1 text-sm font-medium text-slate-900">{{ clearance.period.school_year_name }}</p>
          </div>
        </div>
      </AppCard>

      <AppCard v-for="group in groups" :key="group.key" :title="group.label" :padded="false">
        <div class="divide-y divide-slate-100">
          <div v-for="item in group.items" :key="item.id" class="grid gap-3 px-5 py-4 lg:grid-cols-[1fr_auto]">
            <div>
              <p class="text-sm font-medium text-slate-900">{{ item.requirement_label }}</p>
              <p class="mt-1 text-xs text-slate-500">
                {{ item.assigned_to ?? item.assigned_permission ?? 'Unassigned' }}
              </p>
              <p v-if="item.blocker_summary" class="mt-2 text-sm text-warning-700">{{ item.blocker_summary }}</p>
              <p v-else-if="item.accountability" class="mt-2 text-sm text-warning-700">{{ item.accountability }}</p>
              <p v-if="item.remarks" class="mt-1 text-sm text-slate-500">{{ item.remarks }}</p>
            </div>
            <div class="lg:text-right">
              <AppBadge :color="statusBadgeColor(item.status)">{{ statusLabel(item.status) }}</AppBadge>
              <p v-if="item.signed_by" class="mt-1 text-xs text-slate-500">{{ item.signed_by }}</p>
            </div>
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
