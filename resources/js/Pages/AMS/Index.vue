<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete as confirmDeleteDialog } from '@/Composables/useConfirm.js'
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

async function confirmDelete(activity) {
  if (await confirmDeleteDialog(`Delete "${activity.title}"? This cannot be undone.`)) {
    router.delete(route('ams.activities.destroy', activity.id), { preserveScroll: true })
  }
}
</script>

<template>
  <Head title="Activities" />
  <AdminLayout title="Activity Management">
    <div class="space-y-5">

      <AppPageHeader title="School Activities" subtitle="Manage seminars, workshops, and campus events.">
        <template #actions>
          <AppButton as="link" :href="route('ams.activities.create')">
            <PlusIcon class="w-4 h-4" />
            New Activity
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Search -->
      <AppFilterBar>
        <div class="relative w-full sm:w-80">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="search"
            @input="onSearch"
            type="text"
            placeholder="Search activities…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="displayed.length === 0" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Activity</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider hidden sm:table-cell">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider hidden md:table-cell">Venue</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider hidden lg:table-cell">Resource Person</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider hidden lg:table-cell">Created By</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </template>

        <tr v-for="activity in displayed" :key="activity.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3">
            <Link
              :href="route('ams.activities.show', activity.id)"
              class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline block"
            >{{ activity.title }}</Link>
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
              <Link
                :href="route('ams.activities.show', activity.id)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                title="View"
              ><EyeIcon class="w-4 h-4" /></Link>
              <Link
                :href="route('ams.activities.edit', activity.id)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-warning-600 hover:bg-warning-50 transition-colors"
                title="Edit"
              ><PencilSquareIcon class="w-4 h-4" /></Link>
              <AppIconButton label="Delete activity" variant="danger" @click="confirmDelete(activity)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="activity in displayed" :key="activity.id" class="p-4 space-y-2">
            <Link :href="route('ams.activities.show', activity.id)" class="font-medium text-indigo-600 hover:underline block">
              {{ activity.title }}
            </Link>
            <p class="text-xs text-slate-400">
              {{ formatDate(activity.start_date) }}
              <span v-if="activity.end_date && activity.end_date !== activity.start_date"> – {{ formatDate(activity.end_date) }}</span>
            </p>
            <div class="text-xs text-slate-500 space-y-1">
              <p v-if="activity.venue" class="inline-flex items-center gap-1">
                <MapPinIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />{{ activity.venue }}
              </p>
              <p v-if="activity.resource_person" class="inline-flex items-center gap-1">
                <UserIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />{{ activity.resource_person }}
              </p>
              <p>Created by {{ activity.creator?.name ?? '—' }}</p>
            </div>
            <div class="flex items-center gap-1 pt-1">
              <Link
                :href="route('ams.activities.show', activity.id)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                title="View"
              ><EyeIcon class="w-4 h-4" /></Link>
              <Link
                :href="route('ams.activities.edit', activity.id)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-warning-600 hover:bg-warning-50 transition-colors"
                title="Edit"
              ><PencilSquareIcon class="w-4 h-4" /></Link>
              <AppIconButton label="Delete activity" variant="danger" @click="confirmDelete(activity)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No activities found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="filtered.length"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
