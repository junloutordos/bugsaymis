<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  CalendarDaysIcon,
  MapPinIcon,
  UserIcon,
  TrashIcon,
  PencilSquareIcon,
  EyeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  activities: Array,
})

const search = ref('')
const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  return props.activities.filter(a =>
    a.title.toLowerCase().includes(q) ||
    (a.venue ?? '').toLowerCase().includes(q) ||
    (a.resource_person ?? '').toLowerCase().includes(q) ||
    (a.creator?.name ?? '').toLowerCase().includes(q)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))

const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function onSearch() {
  currentPage.value = 1
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

function confirmDelete(activity) {
  if (confirm(`Delete "${activity.title}"? This cannot be undone.`)) {
    router.delete(route('ams.activities.destroy', activity.id), { preserveScroll: true })
  }
}
</script>

<template>
  <Head title="Activities" />
  <AdminLayout title="Activity Management">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-800">School Activities</h1>
        <p class="text-sm text-slate-500 mt-0.5">Manage seminars, workshops, and campus events.</p>
      </div>
      <a
        :href="route('ams.activities.create')"
        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
      >
        <PlusIcon class="w-4 h-4" />
        New Activity
      </a>
    </div>

    <!-- Search -->
    <div class="relative mb-4 max-w-sm">
      <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
      <input
        v-model="search"
        @input="onSearch"
        type="text"
        placeholder="Search activities…"
        class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
      />
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50">
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Activity</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Date</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Venue</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Resource Person</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Created By</th>
            <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="displayed.length === 0">
            <td colspan="6" class="text-center py-12 text-slate-400 text-sm">No activities found.</td>
          </tr>
          <tr v-for="activity in displayed" :key="activity.id" class="hover:bg-slate-50">
            <td class="px-4 py-3">
              <a
                :href="route('ams.activities.show', activity.id)"
                class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline block"
              >{{ activity.title }}</a>
              <span class="text-xs text-slate-400 sm:hidden">{{ formatDate(activity.start_date) }}</span>
            </td>
            <td class="px-4 py-3 text-slate-600 hidden sm:table-cell whitespace-nowrap">
              {{ formatDate(activity.start_date) }}
              <span v-if="activity.end_date && activity.end_date !== activity.start_date">
                – {{ formatDate(activity.end_date) }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600 hidden md:table-cell">
              <span v-if="activity.venue" class="inline-flex items-center gap-1">
                <MapPinIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                {{ activity.venue }}
              </span>
              <span v-else class="text-slate-300">—</span>
            </td>
            <td class="px-4 py-3 text-slate-600 hidden lg:table-cell">
              <span v-if="activity.resource_person" class="inline-flex items-center gap-1">
                <UserIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                {{ activity.resource_person }}
              </span>
              <span v-else class="text-slate-300">—</span>
            </td>
            <td class="px-4 py-3 text-slate-600 hidden lg:table-cell text-xs">
              {{ activity.creator?.name ?? '—' }}
            </td>
            <td class="px-4 py-3 text-right">
              <div class="inline-flex items-center gap-1">
                <a
                  :href="route('ams.activities.show', activity.id)"
                  class="p-1.5 rounded hover:bg-indigo-50 text-indigo-600"
                  title="View"
                ><EyeIcon class="w-4 h-4" /></a>
                <a
                  :href="route('ams.activities.edit', activity.id)"
                  class="p-1.5 rounded hover:bg-amber-50 text-amber-600"
                  title="Edit"
                ><PencilSquareIcon class="w-4 h-4" /></a>
                <button
                  @click="confirmDelete(activity)"
                  class="p-1.5 rounded hover:bg-red-50 text-red-500"
                  title="Delete"
                ><TrashIcon class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
        <PaginationControl
      :current-page="currentPage"
      :total-pages="totalPages"
      @prev="currentPage--"
      @next="currentPage++"
      @page="currentPage = $event"
    />

  </AdminLayout>
</template>
