<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ template: Object })

const form = useForm({
  strategy_label: '',
  output_outcome: '',
  success_indicator: '',
  target: '',
  weight_percent: null,
})

const showForm = ref(false)

const submit = () => {
  form.post(route('pm2.opcr-templates.storeItem'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      showForm.value = false
    },
  })
}

const destroy = (item) => {
  router.delete(route('pm2.opcr-templates.destroyItem', item.id), { preserveScroll: true })
}
</script>

<template>
  <Head title="PM V2 — OPCR Templates" />
  <AdminLayout title="PM V2 — OPCR Templates">
    <div class="p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="text-lg font-semibold text-slate-800">Strategic Function Template</h1>
        <button
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1"
          @click="showForm = !showForm"
        >
          <PlusIcon class="h-4 w-4" /> Add Item
        </button>
      </div>

      <form v-if="showForm" class="bg-white rounded-lg border border-slate-200 p-4 space-y-3" @submit.prevent="submit">
        <input v-model="form.strategy_label" placeholder="Strategy label (e.g. Strategy 1)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
        <textarea v-model="form.output_outcome" placeholder="Output/Outcome" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
        <textarea v-model="form.success_indicator" placeholder="Success Indicator" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
        <input v-model="form.target" placeholder="Target" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
        <input v-model.number="form.weight_percent" type="number" step="0.01" placeholder="Weight %" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium" :disabled="form.processing">Save</button>
      </form>

      <table class="w-full text-sm bg-white rounded-lg border border-slate-200 overflow-hidden">
        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
          <tr>
            <th class="px-3 py-2 text-left">Strategy</th>
            <th class="px-3 py-2 text-left">Output/Outcome</th>
            <th class="px-3 py-2 text-left">Target</th>
            <th class="px-3 py-2 text-right">Weight %</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in props.template.items" :key="item.id" class="border-t border-slate-100">
            <td class="px-3 py-2">{{ item.strategy_label }}</td>
            <td class="px-3 py-2">{{ item.output_outcome }}</td>
            <td class="px-3 py-2">{{ item.target }}</td>
            <td class="px-3 py-2 text-right">{{ item.weight_percent }}</td>
            <td class="px-3 py-2 text-right">
              <button class="text-red-600 hover:text-red-700" @click="destroy(item)"><TrashIcon class="h-4 w-4" /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
