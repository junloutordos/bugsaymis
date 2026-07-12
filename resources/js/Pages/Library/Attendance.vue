<template>
  <Head title="Library Attendance" />
  <AdminLayout title="Library Attendance">
    <div class="space-y-5">

      <AppPageHeader title="Library Attendance" subtitle="Scan log of library visits." />

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-full sm:w-64">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="q"
            @keydown.enter.prevent="search"
            type="text"
            placeholder="Search by name or Pisay ID"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        <template #actions>
          <AppButton size="sm" @click="search">Search</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!attendances.data?.length" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">PISAY ID</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Student Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Scan Time</th>
          </tr>
        </template>

        <tr v-for="att in attendances.data" :key="att.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ att.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ att.pisay_systemid || '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ (att.student_name ?? '—').toUpperCase() }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ formatDate(att.scanned_at) }}</td>
        </tr>

        <template #mobileCard>
          <div v-for="att in attendances.data" :key="att.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-500">#{{ att.id }}</p>
                <p class="font-semibold text-slate-800">{{ (att.student_name ?? '—').toUpperCase() }}</p>
                <p class="text-sm text-slate-600">PISAY ID: {{ att.pisay_systemid || '—' }}</p>
              </div>
              <div class="text-right text-sm text-slate-600">
                {{ formatDate(att.scanned_at) }}
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No attendance records" />
        </template>

        <template #footer>
          <PaginationControl
            :links="attendances.links"
            :current-page="attendances.current_page"
            :total-pages="attendances.last_page"
            :total="attendances.total"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { usePage, router, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppButton from '@/Components/AppButton.vue';
import AppFilterBar from '@/Components/AppFilterBar.vue';
import AppTable from '@/Components/AppTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PaginationControl from '@/Components/PaginationControl.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const attendances = computed(() => page.props.attendances || { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null });
const q = ref(page.props.q || '');

// responsive: track window width to switch to card layout on small screens
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)
const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

function search() {
  router.get(route('library.attendance.index'), { q: q.value }, { replace: true });
}
function prev() {
  const url = attendances.value.prev_page_url;
  if (url) goTo(url);
}
function next() {
  const url = attendances.value.next_page_url;
  if (url) goTo(url);
}
function goTo(url) {
  if (!url) return;
  window.location.href = url;
}
function formatDate(v) {
  if (!v) return '—';
  return new Date(v).toLocaleString();
}
</script>
