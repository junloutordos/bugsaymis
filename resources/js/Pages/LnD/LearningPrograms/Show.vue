<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
  program: { type: Object, required: true },
})

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : '—'

function typeBadgeColor (t) {
  const map = { mandatory: 'purple', technical: 'indigo', leadership: 'orange', functional: 'blue' }
  return map[t] ?? 'slate'
}
function statusBadgeColor (s) {
  const map = { planned: 'blue', ongoing: 'amber', completed: 'green', cancelled: 'red' }
  return map[s] ?? 'slate'
}
function sessionStatusBadgeColor (s) {
  const map = { scheduled: 'blue', ongoing: 'amber', completed: 'green', cancelled: 'red' }
  return map[s] ?? 'slate'
}
const modeLabel = { face_to_face: 'Face-to-Face', online: 'Online', blended: 'Blended' }
</script>

<template>
  <Head :title="program.title" />
  <AdminLayout :title="program.title">
    <div class="space-y-5">

      <AppPageHeader
        :title="program.title"
        :breadcrumb="[{ label: 'Learning Programs', href: route('lnd.programs.index') }, { label: program.title }]"
      />

      <!-- Program Info + Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Main Info Card -->
        <div class="md:col-span-2">
          <AppCard :padded="false">
            <template #header>
              <div class="flex items-start justify-between gap-3 w-full">
                <h1 class="text-xl font-semibold text-slate-800">{{ program.title }}</h1>
                <div class="flex gap-2 shrink-0">
                  <AppBadge :color="typeBadgeColor(program.type)">{{ program.type }}</AppBadge>
                  <AppBadge :color="statusBadgeColor(program.status)">{{ program.status }}</AppBadge>
                </div>
              </div>
            </template>
            <div class="p-5 space-y-3">
              <p v-if="program.description" class="text-sm text-slate-600">{{ program.description }}</p>
              <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <dt class="text-slate-500">Competency Area</dt>
                <dd class="text-slate-800">{{ program.competency_area ?? '—' }}</dd>
                <dt class="text-slate-500">Target Position</dt>
                <dd class="text-slate-800">{{ program.target_position ?? '—' }}</dd>
                <dt class="text-slate-500">Provider</dt>
                <dd class="text-slate-800">{{ program.provider ?? '—' }}</dd>
                <dt class="text-slate-500">Duration</dt>
                <dd class="text-slate-800">
                  <template v-if="program.start_date">{{ fmt(program.start_date) }} – {{ fmt(program.end_date) }}</template>
                  <template v-else>—</template>
                </dd>
                <dt class="text-slate-500">Training Hours</dt>
                <dd class="text-slate-800">{{ program.hours ? `${program.hours} hrs` : '—' }}</dd>
                <dt class="text-slate-500">Budget</dt>
                <dd class="text-slate-800">{{ program.budget ? `₱${Number(program.budget).toLocaleString()}` : '—' }}</dd>
                <dt class="text-slate-500">Created By</dt>
                <dd class="text-slate-800">{{ program.creator?.name ?? '—' }}</dd>
              </dl>
            </div>
          </AppCard>
        </div>

        <!-- Stats Sidebar -->
        <div class="space-y-4">
          <AppCard class="text-center">
            <div class="text-3xl font-bold text-indigo-600">{{ program.sessions?.length ?? 0 }}</div>
            <div class="text-xs text-slate-500 mt-1">Total Sessions</div>
          </AppCard>
          <AppCard class="text-center">
            <div class="text-3xl font-bold text-emerald-600">
              {{ program.sessions?.filter(s => s.status === 'completed').length ?? 0 }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Completed Sessions</div>
          </AppCard>
          <AppCard class="text-center">
            <div class="text-3xl font-bold text-blue-600">
              {{ program.sessions?.reduce((sum, s) => sum + (s.participants_count ?? 0), 0) ?? 0 }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Total Participants</div>
          </AppCard>
        </div>
      </div>

      <!-- Sessions Table Card -->
      <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Training Sessions</h2>
          <Link :href="route('lnd.sessions.index', { program_id: program.id })" class="text-sm text-indigo-600 hover:underline">View all</Link>
        </div>
        <AppTable :card="false" :is-empty="!program.sessions?.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Venue</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Mode</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Participants</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="s in program.sessions" :key="s.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ fmt(s.session_date) }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ s.venue ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ modeLabel[s.mode] ?? s.mode }}</td>
          <td class="px-4 py-3 text-center font-medium text-indigo-600 text-sm">{{ s.participants_count }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="sessionStatusBadgeColor(s.status)">{{ s.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <Link :href="route('lnd.sessions.show', s.id)" class="text-xs font-medium text-indigo-600 hover:underline">View</Link>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No sessions yet." />
        </template>
        </AppTable>
      </div>

    </div>
  </AdminLayout>
</template>
