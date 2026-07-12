<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ComputerDesktopIcon, ExclamationTriangleIcon, SignalIcon } from '@heroicons/vue/24/outline'

defineProps({
  labs: Array,
})
</script>

<template>
  <Head title="Computer Laboratories" />
  <AdminLayout title="Computer Laboratories">
    <div class="space-y-4">
      <AppPageHeader title="Computer Laboratories" subtitle="Visual map of each computer laboratory. Click a lab to see its seating layout and click any unit to view its live Atlas Sentinel specs." />

      <div v-if="!labs.length" class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <EmptyState title="No computer laboratories yet" subtitle="Set a room's type in Data Management → Rooms." />
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <Link
          v-for="lab in labs"
          :key="lab.room.id"
          :href="route('computer-labs.show', lab.room.id)"
          class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:border-indigo-300 hover:shadow-md transition-all"
        >
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-2">
              <ComputerDesktopIcon class="w-6 h-6 text-indigo-500" />
              <div>
                <div class="text-sm font-semibold text-slate-800">{{ lab.room.name }}</div>
                <div class="text-xs text-slate-400">{{ lab.total }} unit(s)</div>
              </div>
            </div>
            <AppBadge v-if="lab.critical > 0" color="red">
              <ExclamationTriangleIcon class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5" /> {{ lab.critical }} critical
            </AppBadge>
          </div>

          <div class="mt-4 flex items-center gap-1.5 text-xs text-slate-500">
            <SignalIcon class="w-3.5 h-3.5 text-slate-400" />
            {{ lab.enrolled }} of {{ lab.total }} enrolled with Atlas Sentinel
          </div>
          <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div
              class="h-full rounded-full bg-indigo-500 transition-all"
              :style="{ width: (lab.total ? (lab.enrolled / lab.total) * 100 : 0) + '%' }"
            ></div>
          </div>
        </Link>
      </div>
    </div>
  </AdminLayout>
</template>
