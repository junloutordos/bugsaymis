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
        <label class="flex items-center gap-2 whitespace-nowrap text-sm text-slate-600">
          <input v-model="guardActivity" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
          Guard activity
        </label>
        <select v-model="guardId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All guards</option>
          <option v-for="guard in guards" :key="guard.id" :value="String(guard.id)">{{ guard.name }}</option>
        </select>
        <select v-model="deviceId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All kiosk devices</option>
          <option v-for="device in kioskDevices" :key="device.id" :value="String(device.id)">{{ device.name }}</option>
        </select>
        <template #actions>
          <AppButton v-if="search || guardActivity || guardId || deviceId" variant="secondary" size="sm" @click="clearSearch">Clear</AppButton>
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

const props = defineProps({
  auditLogs: Object,
  guards: { type: Array, default: () => [] },
  kioskDevices: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});
const auditLogs = props.auditLogs;
const guards = props.guards;
const kioskDevices = props.kioskDevices;
const urlParams = new URLSearchParams(window.location.search);
const searchInit = urlParams.get('q') || '';
const search = ref(searchInit);
const guardActivity = ref(Boolean(Number(props.filters.guard_activity ?? 0)));
const guardId = ref(props.filters.guard_id ? String(props.filters.guard_id) : '');
const deviceId = ref(props.filters.kiosk_device_id ? String(props.filters.kiosk_device_id) : '');
let debounceTimer = null;

watch(search, (val) => {
  clearTimeout(debounceTimer);
  // do not trigger if identical to initial value on load
  debounceTimer = setTimeout(() => {
    doSearch();
  }, 500);
});

watch([guardActivity, guardId, deviceId], () => doSearch());

onBeforeUnmount(() => clearTimeout(debounceTimer));

function goto(url) { window.location.href = url; }

function doSearch() {
  const q = String(search.value || '');
  const base = window.location.pathname;
  const qs = new URLSearchParams(window.location.search);
  if (q) qs.set('q', q); else qs.delete('q');
  if (guardActivity.value) qs.set('guard_activity', '1'); else qs.delete('guard_activity');
  if (guardId.value) qs.set('guard_id', guardId.value); else qs.delete('guard_id');
  if (deviceId.value) qs.set('kiosk_device_id', deviceId.value); else qs.delete('kiosk_device_id');
  const target = base + (qs.toString() ? ('?' + qs.toString()) : '');
  window.location.href = target;
}

function clearSearch() {
  search.value = '';
  guardActivity.value = false;
  guardId.value = '';
  deviceId.value = '';
  doSearch();
}
</script>

<style scoped>
pre { white-space: pre-wrap; word-break: break-word; }
</style>
