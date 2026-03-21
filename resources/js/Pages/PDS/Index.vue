<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  pdsList: Object,
})
</script>

<template>
  <AdminLayout title="All PDS">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Personal Data Sheets</h1>
          <p class="text-sm text-slate-500">View all submitted personal data sheets</p>
        </div>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Email</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Created</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="pds in pdsList.data" :key="pds.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ pds.user.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ pds.user.email }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ pds.created_at }}</td>
                <td class="px-4 py-3">
                  <Link
                    :href="route('pds.show', pds.id)"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm"
                  >
                    View
                  </Link>
                </td>
              </tr>
              <tr v-if="!pdsList.data || pdsList.data.length === 0">
                <td colspan="4" class="py-16 text-center text-slate-400 text-sm">No personal data sheets found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
