<template>
  <Head title="Attendance Logs" />
  <AdminLayout title="Attendance Logs">
    <div class="p-6 bg-white rounded shadow">
      <h1 class="text-2xl font-bold mb-4">Attendance Logs</h1>
      <div v-if="(attendances.data || []).length === 0" class="text-gray-500 py-6">No attendance records.</div>
      <table v-else class="w-full table-auto border-collapse">
        <thead>
          <tr class="text-left">
            <th class="px-2 py-1">Badge</th>
            <th class="px-2 py-1">Date</th>
            <th class="px-2 py-1">StartTime1</th>
            <th class="px-2 py-1">StartTime2</th>
            <th class="px-2 py-1">StartTime3</th>
            <th class="px-2 py-1">StartTime4</th>
            <th class="px-2 py-1">OT In</th>
            <th class="px-2 py-1">OT Out</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in attendances.data" :key="a.id" class="hover:bg-gray-50">
            <td class="px-2 py-1">{{ a.BadgeNumber }}</td>
            <td class="px-2 py-1">{{ a.AttDate }}</td>
            <td class="px-2 py-1">{{ a.StartTime1 || '—' }}</td>
            <td class="px-2 py-1">{{ a.StartTime2 || '—' }}</td>
            <td class="px-2 py-1">{{ a.StartTime3 || '—' }}</td>
            <td class="px-2 py-1">{{ a.StartTime4 || '—' }}</td>
            <td class="px-2 py-1">{{ a.OTin || '—' }}</td>
            <td class="px-2 py-1">{{ a.OTout || '—' }}</td>
          </tr>
        </tbody>
      </table>

      <div class="mt-4 flex items-center gap-3">
        <button @click.prevent="prev" :disabled="!attendances.prev_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
        <span>Page {{ attendances.current_page }} of {{ attendances.last_page }}</span>
        <button @click.prevent="next" :disabled="!attendances.next_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed } from 'vue'

const page = usePage()
const attendances = computed(() => page.props.attendances || { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null })

function prev() {
  const url = attendances.value.prev_page_url
  if (url) window.location.href = url
}
function next() {
  const url = attendances.value.next_page_url
  if (url) window.location.href = url
}
</script>
