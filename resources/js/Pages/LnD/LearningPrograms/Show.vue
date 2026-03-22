<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  program: { type: Object, required: true },
})

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : '—'

const typeColors = {
  mandatory:  'bg-purple-100 text-purple-700',
  technical:  'bg-indigo-100 text-indigo-700',
  leadership: 'bg-orange-100 text-orange-700',
  functional: 'bg-teal-100 text-teal-700',
}
const statusColors = {
  planned:   'bg-blue-100 text-blue-700',
  ongoing:   'bg-yellow-100 text-yellow-700',
  completed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-600',
}
const sessionStatusColors = {
  scheduled: 'bg-blue-100 text-blue-700',
  ongoing:   'bg-yellow-100 text-yellow-700',
  completed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-600',
}
const modeLabel = { face_to_face: 'Face-to-Face', online: 'Online', blended: 'Blended' }
</script>

<template>
  <AdminLayout :title="program.title">
    <Head :title="program.title" />

    <div class="p-6 space-y-5">

      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-sm">
        <a :href="route('lnd.programs.index')"
          class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-700 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          Learning Programs
        </a>
        <span class="text-slate-300">/</span>
        <span class="font-medium text-slate-700 truncate">{{ program.title }}</span>
      </div>

      <!-- Program Info + Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Main Info Card -->
        <div class="md:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-800">{{ program.title }}</h1>
            <div class="flex gap-2 shrink-0">
              <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', typeColors[program.type] ?? 'bg-slate-100 text-slate-600']">
                {{ program.type }}
              </span>
              <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', statusColors[program.status] ?? 'bg-slate-100 text-slate-600']">
                {{ program.status }}
              </span>
            </div>
          </div>
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
        </div>

        <!-- Stats Sidebar -->
        <div class="space-y-4">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="text-3xl font-bold text-indigo-600">{{ program.sessions?.length ?? 0 }}</div>
            <div class="text-xs text-slate-500 mt-1">Total Sessions</div>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="text-3xl font-bold text-emerald-600">
              {{ program.sessions?.filter(s => s.status === 'completed').length ?? 0 }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Completed Sessions</div>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="text-3xl font-bold text-blue-600">
              {{ program.sessions?.reduce((sum, s) => sum + (s.participants_count ?? 0), 0) ?? 0 }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Total Participants</div>
          </div>
        </div>
      </div>

      <!-- Sessions Table Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Training Sessions</h2>
          <a :href="route('lnd.sessions.index', { program_id: program.id })"
            class="text-sm text-indigo-600 hover:underline">View all</a>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Venue</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Mode</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Participants</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!program.sessions?.length">
                <td colspan="6" class="py-16 text-center text-slate-400 text-sm">No sessions yet.</td>
              </tr>
              <tr v-for="s in program.sessions" :key="s.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ fmt(s.session_date) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ s.venue ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ modeLabel[s.mode] ?? s.mode }}</td>
                <td class="px-4 py-3 text-center font-medium text-indigo-600 text-sm">{{ s.participants_count }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', sessionStatusColors[s.status] ?? 'bg-slate-100 text-slate-600']">
                    {{ s.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <a :href="route('lnd.sessions.show', s.id)"
                    class="text-xs font-medium text-indigo-600 hover:underline">View</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
