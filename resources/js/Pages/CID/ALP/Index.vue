<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowPathIcon, ChevronRightIcon, SparklesIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  cycles: { type: Array, default: () => [] },
  schoolYears: { type: Array, default: () => [] },
  selectedSchoolYearId: [Number, String],
  stats: { type: Object, default: () => ({}) },
  canSync: Boolean,
})

const statusClass = (status) => ({
  accredited: 'bg-emerald-100 text-emerald-700', pending_coordinator: 'bg-amber-100 text-amber-700',
  reviewed: 'bg-blue-100 text-blue-700', recommended: 'bg-indigo-100 text-indigo-700',
  returned: 'bg-rose-100 text-rose-700', closed: 'bg-slate-200 text-slate-700',
}[status] || 'bg-slate-100 text-slate-600')

const label = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
const selectYear = (event) => router.get(route('alp.index'), { school_year_id: event.target.value }, { preserveState: true })
const sync = () => router.post(route('alp.sync'), {}, { preserveScroll: true })
</script>

<template>
  <Head title="Alternative Learning Program" />
  <AdminLayout title="Alternative Learning Program">
    <div class="space-y-6">
      <section class="rounded-2xl bg-gradient-to-r from-indigo-700 to-violet-600 p-6 text-white shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-2"><SparklesIcon class="h-7 w-7" /><h1 class="text-2xl font-bold">Alternative Learning Program</h1></div>
            <p class="mt-2 max-w-3xl text-sm text-indigo-100">QMS-controlled accreditation, membership, activities, attendance, financial records, and year-end reporting.</p>
          </div>
          <div class="flex gap-2">
            <select :value="selectedSchoolYearId" class="rounded-lg border-white/30 bg-white/10 px-3 py-2 text-sm text-white" @change="selectYear">
              <option v-for="year in schoolYears" :key="year.id" :value="year.id" class="text-slate-900">{{ year.name }}</option>
            </select>
            <button v-if="canSync" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50" @click="sync">
              <ArrowPathIcon class="h-4 w-4" /> Sync designations
            </button>
          </div>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="item in [
          ['Programs', stats.programs || 0], ['Accredited', stats.accredited || 0],
          ['Active members', stats.members || 0], ['Unassigned Grades 7–10', stats.unassignedRequired || 0]
        ]" :key="item[0]" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ item[0] }}</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ item[1] }}</p>
        </div>
      </section>

      <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
          <h2 class="font-semibold text-slate-900">Program cycles</h2>
          <p class="text-sm text-slate-500">Programs are generated from active ALA Adviser designations; legacy designation codes remain unchanged.</p>
        </div>
        <div v-if="cycles.length" class="divide-y divide-slate-100">
          <Link v-for="cycle in cycles" :key="cycle.id" :href="route('alp.cycles.show', cycle.id)" class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 font-bold text-indigo-700">{{ cycle.program.code.slice(0, 3) }}</div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <p class="truncate font-semibold text-slate-900">{{ cycle.program.name }}</p>
                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(cycle.status)">{{ label(cycle.status) }}</span>
              </div>
              <p class="mt-1 text-sm text-slate-500">{{ cycle.adviser?.name || 'No adviser assigned' }} · {{ cycle.active_members_count }} members · {{ cycle.activities_count }} activities</p>
            </div>
            <ChevronRightIcon class="h-5 w-5 text-slate-400" />
          </Link>
        </div>
        <div v-else class="px-6 py-14 text-center text-sm text-slate-500">
          No ALP cycles found. An authorized coordinator can synchronize current ALA Adviser designations.
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
