<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  HomeModernIcon, UserGroupIcon, DocumentTextIcon,
  ExclamationTriangleIcon, ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  stats: Array,
  recentApplications: Array,
  currentSy: String,
  myHall: String,
})

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

const statusColor = (s) => ({
  pending:   'amber',
  evaluated: 'blue',
  approved:  'green',
  rejected:  'red',
  waitlisted: 'slate',
}[s] || 'slate')

const hallLabel = (h) => h === 'BRH' ? 'Boys Residence Hall' : 'Girls Residence Hall'
const hallColor = (h) => h === 'BRH' ? 'border-l-indigo-500' : 'border-l-pink-500'
</script>

<template>
  <Head title="Residence Hall Dashboard" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-6">

      <AppPageHeader hero class="dash-section" style="--stagger: 0" title="Residence Hall Dashboard">
        <template #actions>
          <span class="text-sm text-slate-500">
            {{ myHall ? hallLabel(myHall) : 'All Halls' }}
            <span v-if="currentSy"> · SY {{ currentSy }}</span>
          </span>
        </template>
      </AppPageHeader>

      <!-- Hall Stats Cards -->
      <div class="dash-section grid grid-cols-1 md:grid-cols-2 gap-4" style="--stagger: 1">
        <div v-for="hall in stats" :key="hall.hall"
             :class="['bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 border-l-4 p-5', hallColor(hall.hall)]">
          <div class="flex items-center justify-between mb-4">
            <div>
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ hall.hall }}</span>
              <h2 class="text-base font-semibold text-slate-800">{{ hall.label }}</h2>
            </div>
            <HomeModernIcon class="w-8 h-8 text-slate-300" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="text-center p-3 bg-slate-50 rounded-lg">
              <div class="text-2xl font-bold text-indigo-600">{{ hall.active_interns }}</div>
              <div class="text-xs text-slate-500 mt-1">Active Dormers</div>
            </div>
            <div class="text-center p-3 bg-slate-50 rounded-lg">
              <div class="text-2xl font-bold text-slate-700">{{ hall.total_rooms }}</div>
              <div class="text-xs text-slate-500 mt-1">Rooms</div>
            </div>
            <div class="text-center p-3 bg-amber-50 rounded-lg">
              <div class="text-2xl font-bold text-amber-600">{{ hall.pending_apps }}</div>
              <div class="text-xs text-slate-500 mt-1">Pending Apps</div>
            </div>
            <div class="text-center p-3 rounded-lg"
                 :class="hall.overdue_passes > 0 ? 'bg-rose-50' : 'bg-slate-50'">
              <div class="text-2xl font-bold" :class="hall.overdue_passes > 0 ? 'text-rose-600' : 'text-slate-700'">
                {{ hall.overdue_passes }}
              </div>
              <div class="text-xs text-slate-500 mt-1">Overdue Passes</div>
            </div>
          </div>

          <div class="flex gap-2 mt-4">
            <Link :href="route('rh.interns.index')"
                  class="flex-1 text-center text-xs py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-medium">
              View Dormers
            </Link>
            <Link :href="route('rh.applications.index')"
                  class="flex-1 text-center text-xs py-1.5 rounded-lg bg-slate-50 text-slate-700 hover:bg-slate-100 font-medium">
              Applications
            </Link>
          </div>
        </div>
      </div>

      <!-- Quick Nav -->
      <div class="dash-section grid grid-cols-2 sm:grid-cols-4 gap-3" style="--stagger: 2">
        <Link :href="route('rh.applications.index')"
              class="flex flex-col items-center gap-2 p-4 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 hover:border-indigo-300 transition-colors">
          <DocumentTextIcon class="w-6 h-6 text-indigo-500" />
          <span class="text-xs font-medium text-slate-700">Applications</span>
        </Link>
        <Link :href="route('rh.interns.index')"
              class="flex flex-col items-center gap-2 p-4 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 hover:border-indigo-300 transition-colors">
          <UserGroupIcon class="w-6 h-6 text-indigo-500" />
          <span class="text-xs font-medium text-slate-700">Dormers</span>
        </Link>
        <Link :href="route('rh.rooms.index')"
              class="flex flex-col items-center gap-2 p-4 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 hover:border-indigo-300 transition-colors">
          <HomeModernIcon class="w-6 h-6 text-indigo-500" />
          <span class="text-xs font-medium text-slate-700">Rooms</span>
        </Link>
        <Link href="#"
              class="flex flex-col items-center gap-2 p-4 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 opacity-50 cursor-not-allowed">
          <ClipboardDocumentListIcon class="w-6 h-6 text-slate-400" />
          <span class="text-xs font-medium text-slate-500">Housekeeping</span>
        </Link>
      </div>

      <!-- Recent Pending Applications -->
      <div v-if="recentApplications.length" class="dash-section bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70" style="--stagger: 3">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-700">Pending Applications</h3>
          <Link :href="route('rh.applications.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <div class="divide-y divide-slate-50">
          <Link v-for="app in recentApplications" :key="app.id"
                :href="route('rh.applications.show', app.id)"
                class="flex items-center justify-between px-5 py-3 hover:bg-slate-50 transition-colors">
            <div>
              <p class="text-sm font-medium text-slate-800">{{ app.student_name }}</p>
              <p class="text-xs text-slate-500">{{ app.preferred_hall }} · Grade {{ app.grade_level || '—' }}</p>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs text-slate-400">{{ fmtDate(app.created_at) }}</span>
              <AppBadge :color="statusColor(app.status)" class="capitalize">{{ app.status }}</AppBadge>
            </div>
          </Link>
        </div>
      </div>

      <div v-else class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <EmptyState :title="`No pending applications for ${currentSy || 'the current school year'}`" />
      </div>

    </div>
  </AdminLayout>
</template>
