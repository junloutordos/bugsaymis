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

    <div class="p-6 space-y-6">

      <!-- Back -->
      <div class="flex items-center gap-3">
        <a :href="route('lnd.programs.index')"
          class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          Learning Programs
        </a>
        <span class="text-gray-300">/</span>
        <span class="text-sm font-medium text-gray-700 truncate">{{ program.title }}</span>
      </div>

      <!-- Program Info -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="md:col-span-2 rounded-xl border border-gray-200 bg-white p-5 shadow-sm space-y-3">
          <div class="flex items-start justify-between gap-3">
            <h1 class="text-xl font-bold text-gray-800">{{ program.title }}</h1>
            <div class="flex gap-2 shrink-0">
              <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize', typeColors[program.type] ?? 'bg-gray-100 text-gray-600']">
                {{ program.type }}
              </span>
              <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize', statusColors[program.status] ?? 'bg-gray-100 text-gray-600']">
                {{ program.status }}
              </span>
            </div>
          </div>
          <p v-if="program.description" class="text-sm text-gray-600">{{ program.description }}</p>
          <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm mt-3">
            <dt class="text-gray-500">Competency Area</dt>
            <dd class="text-gray-800">{{ program.competency_area ?? '—' }}</dd>
            <dt class="text-gray-500">Target Position</dt>
            <dd class="text-gray-800">{{ program.target_position ?? '—' }}</dd>
            <dt class="text-gray-500">Provider</dt>
            <dd class="text-gray-800">{{ program.provider ?? '—' }}</dd>
            <dt class="text-gray-500">Duration</dt>
            <dd class="text-gray-800">
              <template v-if="program.start_date">{{ fmt(program.start_date) }} – {{ fmt(program.end_date) }}</template>
              <template v-else>—</template>
            </dd>
            <dt class="text-gray-500">Training Hours</dt>
            <dd class="text-gray-800">{{ program.hours ? `${program.hours} hrs` : '—' }}</dd>
            <dt class="text-gray-500">Budget</dt>
            <dd class="text-gray-800">{{ program.budget ? `₱${Number(program.budget).toLocaleString()}` : '—' }}</dd>
            <dt class="text-gray-500">Created By</dt>
            <dd class="text-gray-800">{{ program.creator?.name ?? '—' }}</dd>
          </dl>
        </div>

        <!-- Stats -->
        <div class="space-y-4">
          <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <div class="text-3xl font-bold text-blue-600">{{ program.sessions?.length ?? 0 }}</div>
            <div class="text-xs text-gray-500 mt-1">Total Sessions</div>
          </div>
          <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <div class="text-3xl font-bold text-green-600">
              {{ program.sessions?.filter(s => s.status === 'completed').length ?? 0 }}
            </div>
            <div class="text-xs text-gray-500 mt-1">Completed Sessions</div>
          </div>
          <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <div class="text-3xl font-bold text-indigo-600">
              {{ program.sessions?.reduce((sum, s) => sum + (s.participants_count ?? 0), 0) ?? 0 }}
            </div>
            <div class="text-xs text-gray-500 mt-1">Total Participants</div>
          </div>
        </div>
      </div>

      <!-- Sessions Table -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between border-b px-5 py-4">
          <h2 class="font-semibold text-gray-800">Training Sessions</h2>
          <a :href="route('lnd.sessions.index', { program_id: program.id })"
            class="text-sm text-blue-600 hover:underline">View all</a>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
              <tr>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Venue</th>
                <th class="px-4 py-3 text-left">Mode</th>
                <th class="px-4 py-3 text-center">Participants</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="!program.sessions?.length">
                <td colspan="6" class="py-8 text-center text-gray-400">No sessions yet.</td>
              </tr>
              <tr v-for="s in program.sessions" :key="s.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ fmt(s.session_date) }}</td>
                <td class="px-4 py-3 text-gray-600">{{ s.venue ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ modeLabel[s.mode] ?? s.mode }}</td>
                <td class="px-4 py-3 text-center font-medium text-blue-600">{{ s.participants_count }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', sessionStatusColors[s.status] ?? 'bg-gray-100 text-gray-600']">
                    {{ s.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <a :href="route('lnd.sessions.show', s.id)"
                    class="text-xs font-medium text-blue-600 hover:underline">View</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
