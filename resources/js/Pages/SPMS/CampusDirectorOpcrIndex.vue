<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ opcrs: Array })

const statusBadgeColor = (status) => ({
  'Draft': 'bg-slate-100 text-slate-700',
  'Submitted to Executive Director': 'bg-amber-100 text-amber-700',
  'ED Approved': 'bg-emerald-100 text-emerald-700',
  'Returned': 'bg-rose-100 text-rose-700',
}[status] ?? 'bg-slate-100 text-slate-700')

const createOpcr = () => {
  router.post(route('spms.opcr.store'))
}
</script>

<template>
  <Head title="My OPCR (SPMS)" />
  <AdminLayout title="My OPCR (SPMS)">
    <div class="mb-4 flex justify-end">
      <button @click="createOpcr" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Create OPCR
      </button>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
      <p v-if="!opcrs.length" class="p-6 text-sm text-slate-500">No records yet — click Create to get started.</p>
      <table v-else class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Fiscal Year</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Final Rating</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="opcr in opcrs" :key="opcr.id">
            <td class="px-4 py-3">{{ opcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusBadgeColor(opcr.status)">
                {{ opcr.status }}
              </span>
            </td>
            <td class="px-4 py-3">{{ opcr.final_rating ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.opcr.show', opcr.id)" class="text-indigo-600 hover:underline">View</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
