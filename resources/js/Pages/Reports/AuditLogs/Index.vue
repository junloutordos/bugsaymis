<template>
  <Head title="Audit Logs" />
  <AdminLayout title="Audit Logs">
    <div>
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">Audit Logs</h1>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <!-- Search -->
        <div class="flex items-center gap-2">
          <input v-model="search" type="text" placeholder="Search audit logs..." class="w-full sm:w-1/2 md:w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
          <button v-if="search" @click="clearSearch" class="ml-2 px-3 py-1 rounded border">Clear</button>
        </div>

        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Time</th>
                <th class="px-4 py-3 text-left">User</th>
                <th class="px-4 py-3 text-left">Action</th>
                <th class="px-4 py-3 text-left">Target</th>
                <th class="px-4 py-3 text-left">Details</th>
                <th class="px-4 py-3 text-left">IP</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="log in auditLogs.data" :key="log.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ log.id }}</td>
                <td class="px-4 py-3">{{ new Date(log.created_at).toLocaleString() }}</td>
                <td class="px-4 py-3">{{ log.user?.name ?? 'System' }}</td>
                <td class="px-4 py-3">{{ log.action }}</td>
                <td class="px-4 py-3">{{ log.auditable_type ? log.auditable_type.split('\\').pop() + ' #' + (log.auditable_id ?? '') : '' }}</td>
                <td class="px-4 py-3">
                  <div v-if="log.old_values" class="text-xs text-gray-700">Old: <pre class="text-xs">{{ JSON.stringify(log.old_values) }}</pre></div>
                  <div v-if="log.new_values" class="text-xs text-gray-700">New: <pre class="text-xs">{{ JSON.stringify(log.new_values) }}</pre></div>
                </td>
                <td class="px-4 py-3">{{ log.ip_address }}</td>
              </tr>

              <tr v-if="!auditLogs.data || auditLogs.data.length === 0">
                <td colspan="7" class="px-4 py-6 text-center text-gray-500">No audit logs found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
          <div class="flex justify-center items-center gap-2">
            <button v-if="auditLogs.prev_page_url" @click="goto(auditLogs.prev_page_url)" class="bg-blue-600 text-white px-4 py-2 rounded">Previous</button>
            <button v-if="auditLogs.next_page_url" @click="goto(auditLogs.next_page_url)" class="bg-blue-600 text-white px-4 py-2 rounded">Next</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, watch, onBeforeUnmount } from 'vue'

const props = defineProps({ auditLogs: Object });
const auditLogs = props.auditLogs;
const urlParams = new URLSearchParams(window.location.search);
const searchInit = urlParams.get('q') || '';
const search = ref(searchInit);
let debounceTimer = null;

watch(search, (val) => {
  clearTimeout(debounceTimer);
  // do not trigger if identical to initial value on load
  debounceTimer = setTimeout(() => {
    doSearch();
  }, 500);
});

onBeforeUnmount(() => clearTimeout(debounceTimer));

function goto(url) { window.location.href = url; }

function doSearch() {
  const q = String(search.value || '');
  const base = window.location.pathname;
  const qs = new URLSearchParams(window.location.search);
  if (q) qs.set('q', q); else qs.delete('q');
  const target = base + (qs.toString() ? ('?' + qs.toString()) : '');
  window.location.href = target;
}

function clearSearch() {
  search.value = '';
  doSearch();
}
</script>

<style scoped>
.btn { @apply bg-blue-600 text-white px-3 py-1 rounded; }
pre { white-space: pre-wrap; word-break: break-word; }
</style>
