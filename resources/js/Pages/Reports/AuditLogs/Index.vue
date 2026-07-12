<template>
  <Head title="Audit Logs" />
  <AdminLayout title="Audit Logs">
    <div>
      <AppPageHeader title="Audit Logs" subtitle="Track all system activity and changes" />

      <!-- Filter Bar -->
      <AppFilterBar>
        <div class="flex-1 min-w-[200px]">
          <AppInput v-model="search" type="text" placeholder="Search audit logs..." />
        </div>
        <template #actions>
          <AppButton v-if="search" variant="secondary" size="sm" @click="clearSearch">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table Card -->
      <AppTable :is-empty="!auditLogs.data || auditLogs.data.length === 0" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Time</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">User</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Target</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Details</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">IP</th>
          </tr>
        </template>

        <tr v-for="log in auditLogs.data" :key="log.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ log.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ new Date(log.created_at).toLocaleString() }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ log.user?.name ?? 'System' }}</td>
          <td class="px-4 py-3">
            <AppBadge color="slate">{{ log.action }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ log.auditable_type ? log.auditable_type.split('\\').pop() + ' #' + (log.auditable_id ?? '') : '' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 max-w-xs">
            <div v-if="log.old_values" class="text-xs text-slate-500 mb-0.5">Old: <pre class="inline">{{ JSON.stringify(log.old_values) }}</pre></div>
            <div v-if="log.new_values" class="text-xs text-slate-500">New: <pre class="inline">{{ JSON.stringify(log.new_values) }}</pre></div>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ log.ip_address }}</td>
        </tr>

        <template #empty>
          <EmptyState title="No audit logs found." />
        </template>

        <template #footer>
          <div class="flex items-center justify-between px-4 py-3 text-sm text-slate-600">
            <span>Showing {{ auditLogs.data?.length ?? 0 }} entries</span>
            <div class="flex items-center gap-2">
              <AppButton v-if="auditLogs.prev_page_url" variant="secondary" size="sm" @click="goto(auditLogs.prev_page_url)">
                Previous
              </AppButton>
              <AppButton v-if="auditLogs.next_page_url" size="sm" @click="goto(auditLogs.next_page_url)">
                Next
              </AppButton>
            </div>
          </div>
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
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
