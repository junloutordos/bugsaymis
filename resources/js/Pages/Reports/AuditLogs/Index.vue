<template>
  <Head title="Audit Logs" />
  <AdminLayout title="Audit Logs">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Audit Logs</h1>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
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
        <div class="flex justify-center items-center gap-2 mt-4">
          <button v-if="auditLogs.prev_page_url" @click="goto(auditLogs.prev_page_url)" class="bg-blue-600 text-white px-4 py-2 rounded">Previous</button>
          <button v-if="auditLogs.next_page_url" @click="goto(auditLogs.next_page_url)" class="bg-blue-600 text-white px-4 py-2 rounded">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ auditLogs: Object });
const auditLogs = props.auditLogs;
function goto(url) { window.location.href = url; }
</script>

<style scoped>
.btn { @apply bg-blue-600 text-white px-3 py-1 rounded; }
pre { white-space: pre-wrap; word-break: break-word; }
</style>
