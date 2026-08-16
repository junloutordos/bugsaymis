<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ weightProfiles: Array, fiscalPeriods: Array })

const weightForm = useForm({
  level: 'ipcr', division_id: null, fiscal_year: 2026,
  strategic_pct: 30, core_pct: 50, support_pct: 20,
})

const submitWeightProfile = () => {
  weightForm.post(route('spms.admin.weight-profiles.store'), { preserveScroll: true })
}
</script>

<template>
  <Head title="SPMS Admin Config" />
  <AdminLayout title="SPMS Admin Config">
    <div class="rounded-lg border border-slate-200 bg-white p-4 mb-6">
      <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">New Weight Profile</h2>
      <form @submit.prevent="submitWeightProfile" class="grid grid-cols-3 gap-3">
        <input v-model.number="weightForm.fiscal_year" type="number" placeholder="Fiscal Year"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <input v-model.number="weightForm.strategic_pct" type="number" placeholder="Strategic %"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <input v-model.number="weightForm.core_pct" type="number" placeholder="Core %"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <input v-model.number="weightForm.support_pct" type="number" placeholder="Support %"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Save
        </button>
      </form>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
      <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Weight Profiles</h2>
      <table class="min-w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <tr v-for="profile in weightProfiles" :key="profile.id">
            <td class="px-2 py-2">{{ profile.level }}</td>
            <td class="px-2 py-2">{{ profile.fiscal_year }}</td>
            <td class="px-2 py-2">{{ profile.strategic_pct }}/{{ profile.core_pct }}/{{ profile.support_pct }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
