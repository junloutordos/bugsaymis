<template>
  <Head title="Audit Logs" />
  <AdminLayout title="Audit Logs">
    <div>
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Audit Logs</h1>
          <p class="text-sm text-slate-500 mt-0.5">Track all system activity and changes</p>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input v-model="search" type="text" placeholder="Search audit logs..."
          class="flex-1 min-w-[200px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        <button v-if="search" @click="clearSearch"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          Clear
        </button>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">User</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Target</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Details</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">IP</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="log in auditLogs.data" :key="log.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ log.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ new Date(log.created_at).toLocaleString() }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ log.user?.name ?? 'System' }}</td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">
                    {{ log.action }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ log.auditable_type ? log.auditable_type.split('\\').pop() + ' #' + (log.auditable_id ?? '') : '' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 max-w-xs">
                  <div v-if="log.old_values" class="text-xs text-slate-500 mb-0.5">Old: <pre class="inline">{{ JSON.stringify(log.old_values) }}</pre></div>
                  <div v-if="log.new_values" class="text-xs text-slate-500">New: <pre class="inline">{{ JSON.stringify(log.new_values) }}</pre></div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ log.ip_address }}</td>
              </tr>

              <tr v-if="!auditLogs.data || auditLogs.data.length === 0">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No audit logs found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Showing {{ auditLogs.data?.length ?? 0 }} entries</span>
          <div class="flex items-center gap-2">
            <button v-if="auditLogs.prev_page_url" @click="goto(auditLogs.prev_page_url)"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Previous
            </button>
            <button v-if="auditLogs.next_page_url" @click="goto(auditLogs.next_page_url)"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Next
            </button>
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
pre { white-space: pre-wrap; word-break: break-word; }
</style>
