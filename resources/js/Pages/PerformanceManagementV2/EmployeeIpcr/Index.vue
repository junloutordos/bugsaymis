<script setup>
import { useForm, Link, Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ ipcrs: Array, ratingPeriods: Array })

const form = useForm({ rating_period_id: '', title: '' })

const submit = () => form.post(route('pm2.employee-ipcr.store'))
</script>

<template>
  <Head title="PM V2 — My IPCR" />
  <AdminLayout title="PM V2 — My IPCR">
    <div class="p-6 space-y-4">
      <form class="bg-white rounded-lg border border-slate-200 p-4 flex gap-3 items-end" @submit.prevent="submit">
        <div class="flex-1">
          <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Rating Period</label>
          <select v-model="form.rating_period_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full">
            <option value="" disabled>Select a period</option>
            <option v-for="p in props.ratingPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
          </select>
        </div>
        <div class="flex-1">
          <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</label>
          <input v-model="form.title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        </div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium" :disabled="form.processing">Create IPCR</button>
      </form>

      <table class="w-full text-sm bg-white rounded-lg border border-slate-200 overflow-hidden">
        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
          <tr><th class="px-3 py-2 text-left">Title</th><th class="px-3 py-2 text-left">Period</th><th class="px-3 py-2 text-left">Status</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="ipcr in props.ipcrs" :key="ipcr.id" class="border-t border-slate-100">
            <td class="px-3 py-2">{{ ipcr.title }}</td>
            <td class="px-3 py-2">{{ ipcr.rating_period?.label }}</td>
            <td class="px-3 py-2">{{ ipcr.status }}</td>
            <td class="px-3 py-2 text-right"><Link :href="route('pm2.employee-ipcr.show', ipcr.id)" class="text-indigo-600 hover:underline">View</Link></td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
