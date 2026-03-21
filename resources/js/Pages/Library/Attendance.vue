<template>
  <Head title="Library Attendance" />
  <AdminLayout title="Library Attendance">
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Library Attendance</h1>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <input
            v-model="q"
            @keydown.enter="search"
            type="text"
            placeholder="Search by name or Pisay ID"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64"
          />
        </div>

        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PISAY ID</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">STUDENT NAME</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">SCAN TIME</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="att in attendances.data" :key="att.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ att.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ att.pisay_systemid || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ (att.student_name ?? '—').toUpperCase() }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ formatDate(att.scanned_at) }}</td>
              </tr>
              <tr v-if="(attendances.data || []).length === 0"><td :colspan="4" class="py-16 text-center text-slate-400 text-sm">No attendance records</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div v-else class="space-y-3 p-4 sm:hidden">
          <div v-for="att in attendances.data" :key="att.id" class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-slate-500">#{{ att.id }}</div>
                <div class="font-semibold text-slate-800">{{ (att.student_name ?? '—').toUpperCase() }}</div>
                <div class="text-sm text-slate-600">PISAY ID: {{ att.pisay_systemid || '—' }}</div>
              </div>
              <div class="text-right text-sm">
                <div class="text-slate-600">{{ formatDate(att.scanned_at) }}</div>
              </div>
            </div>
          </div>

          <div v-if="(attendances.data || []).length === 0" class="py-16 text-center text-slate-400 text-sm">No attendance records</div>
        </div>

        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click.prevent="prev" :disabled="!attendances.prev_page_url" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Prev</button>
          <span>Page {{ attendances.current_page }} of {{ attendances.last_page }}</span>
          <button @click.prevent="next" :disabled="!attendances.next_page_url" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { usePage, router, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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

<style scoped>
.table-auto th, .table-auto td { padding: 0.5rem; }
</style>
