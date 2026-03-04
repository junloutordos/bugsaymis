<template>
  <Head title="Library Attendance" />
  <AdminLayout title="Library Attendance">
    <div>
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-2xl font-bold">Library Attendance</h1>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="flex items-center justify-between mb-4">
          <input
            v-model="q"
            @keydown.enter="search"
            type="text"
            placeholder="Search by name or Pisay ID"
            class="w-full sm:w-1/2 md:w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full border">
            <thead class="bg-gray-100 text-sm text-gray-700">
              <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">PISAY ID</th>
                <th class="px-4 py-2">STUDENT NAME</th>
                <th class="px-4 py-2">SCAN TIME</th>
              </tr>
            </thead>
            <tbody class="text-sm divide-y">
              <tr v-for="att in attendances.data" :key="att.id">
                <td class="px-4 py-2">{{ att.id }}</td>
                <td class="px-4 py-2">{{ att.pisay_systemid || '—' }}</td>
                <td class="px-4 py-2">{{ (att.student_name ?? '—').toUpperCase() }}</td>
                <td class="px-4 py-2">{{ formatDate(att.scanned_at) }}</td>
              </tr>
              <tr v-if="(attendances.data || []).length === 0"><td :colspan="4" class="px-4 py-6 text-center text-gray-500">No attendance records</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div v-else class="space-y-3 sm:hidden">
          <div v-for="att in attendances.data" :key="att.id" class="border rounded-lg p-3 bg-white shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-sm text-gray-500">#{{ att.id }}</div>
                <div class="font-semibold text-gray-800">{{ (att.student_name ?? '—').toUpperCase() }}</div>
                <div class="text-sm text-gray-600">PISAY ID: {{ att.pisay_systemid || '—' }}</div>
              </div>
              <div class="text-right text-sm">
                <div class="text-gray-600">{{ formatDate(att.scanned_at) }}</div>
              </div>
            </div>
          </div>

          <div v-if="(attendances.data || []).length === 0" class="text-center text-gray-500 py-6">No attendance records</div>
        </div>

        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click.prevent="prev" :disabled="!attendances.prev_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ attendances.current_page }} of {{ attendances.last_page }}</span>
          <button @click.prevent="next" :disabled="!attendances.next_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
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
