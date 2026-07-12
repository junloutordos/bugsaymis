<template>
  <Head title="Discipline Cases" />
  <AdminLayout title="Student Discipline">
    <div class="space-y-5">

      <AppPageHeader title="Discipline Cases" subtitle="Anecdotal Reports and case resolution">
        <template #actions>
          <AppButton v-if="canFile" as="link" :href="route('discipline.cases.create')">
            <PlusIcon class="h-4 w-4" />
            File a Report
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-[200px]">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Case no. or offense…"
                 class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <select v-model="status" @change="applyFilters"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Statuses</option>
          <option v-for="s in statusOptions" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
        </select>

        <select v-model="level" @change="applyFilters"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Levels</option>
          <option value="1">Level 1</option>
          <option value="2">Level 2</option>
          <option value="3">Level 3</option>
        </select>

        <template #actions>
          <AppButton size="sm" @click="applyFilters">Search</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!cases.data.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Case No.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Student</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Offense</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Level</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Filed</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="c in cases.data" :key="c.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-medium text-slate-700">{{ c.case_no }}</td>
          <td class="px-4 py-3 text-slate-600">{{ c.student_name }}</td>
          <td class="px-4 py-3 text-slate-600">{{ c.offense?.title || c.nature_of_offense || '—' }}</td>
          <td class="px-4 py-3 text-slate-600">{{ c.offense_level ? ('L' + c.offense_level) : '—' }}</td>
          <td class="px-4 py-3 text-slate-500">{{ fmtDate(c.date_filed) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(c.status)">{{ c.status.replace('_', ' ') }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-right">
            <Link :href="route('discipline.cases.show', c.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="c in cases.data" :key="c.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-xs text-slate-500">{{ c.case_no }}</p>
                <p class="font-medium text-slate-800">{{ c.student_name }}</p>
                <p class="text-xs text-slate-500">{{ c.offense?.title || c.nature_of_offense || '—' }}</p>
              </div>
              <AppBadge :color="statusColor(c.status)">{{ c.status.replace('_', ' ') }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <span>{{ c.offense_level ? ('L' + c.offense_level) : '—' }} &middot; Filed {{ fmtDate(c.date_filed) }}</span>
              <Link :href="route('discipline.cases.show', c.id)" class="text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No discipline cases found" />
        </template>

        <template #footer>
          <PaginationControl
            :links="cases.links"
            :current-page="cases.current_page"
            :total-pages="cases.last_page"
            :total="cases.total"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  cases: Object,
  filters: Object,
  canManage: Boolean,
  canFile: Boolean,
})

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const level = ref(props.filters.level || '')

const statusOptions = ['filed', 'received', 'under_review', 'resolved', 'dismissed', 'cancelled']

const statusColor = (s) => ({
  filed: 'amber',
  received: 'blue',
  under_review: 'indigo',
  resolved: 'green',
  dismissed: 'slate',
  cancelled: 'red',
}[s] || 'slate')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

function applyFilters() {
  router.get(route('discipline.cases.index'),
    { search: search.value, status: status.value, level: level.value },
    { preserveState: true, replace: true })
}
</script>
