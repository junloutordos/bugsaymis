<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  AcademicCapIcon,
  ArrowPathIcon,
  CheckBadgeIcon,
  ChevronRightIcon,
  UserGroupIcon,
  UserMinusIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  cycles: { type: Array, default: () => [] },
  schoolYears: { type: Array, default: () => [] },
  selectedSchoolYearId: [Number, String],
  stats: { type: Object, default: () => ({}) },
  canSync: Boolean,
})

const statusColor = (status) => ({
  accredited: 'green',
  pending_coordinator: 'amber',
  reviewed: 'blue',
  recommended: 'indigo',
  returned: 'red',
  closed: 'slate',
}[status] || 'slate')

const label = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
const selectYear = (schoolYearId) => router.get(route('alp.index'), { school_year_id: schoolYearId }, { preserveState: true })
const sync = () => router.post(route('alp.sync'), {}, { preserveScroll: true })

const statCards = computed(() => [
  { label: 'Programs', value: props.stats.programs || 0, icon: AcademicCapIcon, tone: 'bg-indigo-50 text-indigo-600' },
  { label: 'Accredited', value: props.stats.accredited || 0, icon: CheckBadgeIcon, tone: 'bg-success-50 text-success-600' },
  { label: 'Active members', value: props.stats.members || 0, icon: UserGroupIcon, tone: 'bg-blue-50 text-blue-600' },
  { label: 'Unassigned Grades 7–10', value: props.stats.unassignedRequired || 0, icon: UserMinusIcon, tone: 'bg-warning-50 text-warning-600' },
])

const TH = 'px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap'
const TD = 'px-4 py-3 text-sm text-slate-600'
</script>

<template>
  <Head title="Alternative Learning Program" />
  <AdminLayout title="Alternative Learning Program">
    <div class="space-y-5">
      <AppPageHeader
        hero
        title="Alternative Learning Program"
        subtitle="Manage QMS-controlled accreditation, membership, activities, attendance, financial records, and year-end reporting."
      >
        <template #actions>
          <AppSelect
            :model-value="selectedSchoolYearId"
            :show-blank="false"
            aria-label="School year"
            class="min-w-40"
            @update:model-value="selectYear"
          >
            <option v-for="year in schoolYears" :key="year.id" :value="year.id">{{ year.name }}</option>
          </AppSelect>
          <AppButton v-if="canSync" variant="secondary" @click="sync">
            <ArrowPathIcon class="h-4 w-4" />
            Sync designations
          </AppButton>
        </template>
      </AppPageHeader>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="ALP summary">
        <AppCard v-for="item in statCards" :key="item.label">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-medium text-slate-500">{{ item.label }}</p>
              <p class="mt-2 font-heading text-2xl font-semibold text-slate-900">{{ item.value }}</p>
            </div>
            <span :class="['flex h-10 w-10 items-center justify-center rounded-xl', item.tone]">
              <component :is="item.icon" class="h-5 w-5" />
            </span>
          </div>
        </AppCard>
      </section>

      <AppCard
        :padded="false"
        title="Program cycles"
        subtitle="Generated from active ALA Adviser designations; legacy designation codes remain unchanged."
      >
        <AppTable :is-empty="cycles.length === 0" :skeleton-cols="6" :card="false">
          <template #head>
            <tr>
              <th :class="TH">Program</th>
              <th :class="TH">Adviser</th>
              <th :class="TH">Members</th>
              <th :class="TH">Activities</th>
              <th :class="TH">Status</th>
              <th class="w-12 px-4 py-3"><span class="sr-only">Open</span></th>
            </tr>
          </template>

          <Link
            v-for="cycle in cycles"
            :key="cycle.id"
            :href="route('alp.cycles.show', cycle.id)"
            custom
            v-slot="{ navigate }"
          >
            <tr class="cursor-pointer transition-colors hover:bg-indigo-50/40 focus-within:bg-indigo-50/40" tabindex="0" @click="navigate" @keydown.enter="navigate">
              <td :class="TD">
                <div class="flex items-center gap-3">
                  <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-xs font-semibold text-indigo-700">
                    {{ cycle.program.code.slice(0, 3) }}
                  </span>
                  <div class="min-w-0">
                    <p class="font-medium text-slate-800">{{ cycle.program.name }}</p>
                    <p class="text-xs text-slate-400">{{ cycle.program.code }}</p>
                  </div>
                </div>
              </td>
              <td :class="TD">{{ cycle.adviser?.name || 'Unassigned' }}</td>
              <td :class="TD">{{ cycle.active_members_count }}</td>
              <td :class="TD">{{ cycle.activities_count }}</td>
              <td :class="TD"><AppBadge :color="statusColor(cycle.status)">{{ label(cycle.status) }}</AppBadge></td>
              <td class="px-4 py-3 text-right"><ChevronRightIcon class="ml-auto h-4 w-4 text-slate-400" /></td>
            </tr>
          </Link>

          <template #mobileCard>
            <Link
              v-for="cycle in cycles"
              :key="cycle.id"
              :href="route('alp.cycles.show', cycle.id)"
              class="block p-4 transition-colors hover:bg-slate-50"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-heading text-sm font-semibold text-slate-800">{{ cycle.program.name }}</p>
                  <p class="mt-1 text-xs text-slate-500">{{ cycle.adviser?.name || 'No adviser assigned' }}</p>
                </div>
                <AppBadge :color="statusColor(cycle.status)">{{ label(cycle.status) }}</AppBadge>
              </div>
              <p class="mt-3 text-xs text-slate-500">{{ cycle.active_members_count }} members · {{ cycle.activities_count }} activities</p>
            </Link>
          </template>

          <template #empty>
            <EmptyState
              title="No ALP cycles found"
              subtitle="An authorized coordinator can synchronize current ALA Adviser designations."
            />
          </template>
        </AppTable>
      </AppCard>
    </div>
  </AdminLayout>
</template>
