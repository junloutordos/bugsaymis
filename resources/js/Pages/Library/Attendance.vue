<template>
  <Head title="Library Attendance" />
  <AdminLayout title="Library Attendance">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Library Attendance</h1>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="flex items-center justify-between mb-4">
          <input
            v-model="q"
            @keydown.enter="search"
            type="text"
            placeholder="Search by name or Pisay ID"
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div class="overflow-x-auto">
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

        <div class="mt-4 flex items-center justify-between">
          <div class="text-sm text-gray-600">Page {{ attendances.current_page }} of {{ attendances.last_page }}</div>
          <div class="space-x-2">
            <button @click.prevent="goTo(attendances.prev_page_url)" :disabled="!attendances.prev_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
            <button @click.prevent="goTo(attendances.next_page_url)" :disabled="!attendances.next_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, router, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const page = usePage();
const attendances = computed(() => page.props.attendances || { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null });
const q = ref(page.props.q || '');

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
