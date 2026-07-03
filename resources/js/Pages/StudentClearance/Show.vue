<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

      <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ clearance.period.title }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ clearance.student_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
              {{ clearance.pisays_id }} / Grade {{ clearance.grade_level }} {{ clearance.section_name }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <span class="rounded-full border px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(clearance.status)">
              {{ statusLabel(clearance.status) }}
            </span>
            <button @click="adviserReview" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
              <ClipboardDocumentCheckIcon class="h-4 w-4" />
              Adviser Review
            </button>
            <button @click="finalizeClearance" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
              <CheckCircleIcon class="h-4 w-4" />
              Finalize
            </button>
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
      </section>

      <section v-for="group in groups" :key="group.key" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3">
          <h2 class="text-sm font-semibold text-slate-900">{{ group.label }}</h2>
        </div>
        <div class="divide-y divide-slate-100">
          <div v-for="item in group.items" :key="item.id" class="grid gap-3 px-5 py-4 lg:grid-cols-[1fr_auto]">
            <div>
              <p class="text-sm font-medium text-slate-900">{{ item.requirement_label }}</p>
              <p class="mt-1 text-xs text-slate-500">
                {{ item.assigned_to ?? item.assigned_permission ?? 'Unassigned' }}
              </p>
              <p v-if="item.accountability" class="mt-2 text-sm text-amber-700">{{ item.accountability }}</p>
              <p v-if="item.remarks" class="mt-1 text-sm text-slate-500">{{ item.remarks }}</p>
            </div>
            <div class="lg:text-right">
              <span class="rounded-full border px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(item.status)">
                {{ statusLabel(item.status) }}
              </span>
              <p v-if="item.signed_by" class="mt-1 text-xs text-slate-500">{{ item.signed_by }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
