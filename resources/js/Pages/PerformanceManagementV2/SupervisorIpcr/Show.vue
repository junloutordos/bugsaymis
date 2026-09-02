<script setup>
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ ipcr: Object, weightTargets: Object, weightSums: Object })

const FUNCTION_LABELS = { strategic: 'Strategic Function', core: 'Core Function', support: 'Support Function' }

const rowsByType = (type) => props.ipcr.rows.filter((r) => r.function_type === type)

const rateDrafts = reactive(
  Object.fromEntries(props.ipcr.rows.map((r) => [r.id, {
    sup_quality: r.sup_quality,
    sup_efficiency: r.sup_efficiency,
    sup_timeliness: r.sup_timeliness,
  }]))
)

const approveTargets = () => router.post(route('pm2.supervisor-ipcr.approveTargets', props.ipcr.id))

const saveRating = (row) => {
  router.put(route('pm2.supervisor-ipcr.rateRow', [props.ipcr.id, row.id]), rateDrafts[row.id], { preserveScroll: true })
}

const markRated = () => router.post(route('pm2.supervisor-ipcr.markRated', props.ipcr.id))
</script>

<template>
  <Head :title="`PM V2 — ${props.ipcr.user?.name}`" />
  <AdminLayout :title="props.ipcr.user?.name">
    <div class="p-6 space-y-6">
      <div class="bg-white rounded-lg border border-slate-200 p-4 flex items-center justify-between">
        <div>
          <div class="font-semibold text-slate-800">{{ props.ipcr.title }}</div>
          <div class="text-sm text-slate-500">{{ props.ipcr.rating_period?.label }} — {{ props.ipcr.status }}</div>
        </div>
        <div class="flex gap-2">
          <button v-if="props.ipcr.status === 'New Target'" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium" @click="approveTargets">Approve Targets</button>
          <button v-if="props.ipcr.status === 'Submitted for Rating'" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium" @click="markRated">Mark Rated</button>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-slate-200 p-4 grid grid-cols-3 gap-4 text-sm">
        <div v-for="(target, type) in props.weightTargets" :key="type">
          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ FUNCTION_LABELS[type] }}</div>
          <div :class="props.weightSums[type] === target ? 'text-emerald-600' : 'text-red-600'">
            {{ props.weightSums[type] }}% / {{ target }}%
          </div>
        </div>
      </div>

      <div v-for="type in ['strategic', 'core', 'support']" :key="type" class="bg-white rounded-lg border border-slate-200 p-4 space-y-3">
        <h2 class="text-sm font-semibold text-slate-700">{{ FUNCTION_LABELS[type] }}</h2>
        <div v-for="row in rowsByType(type)" :key="row.id" class="border-t border-slate-100 pt-3 space-y-2">
          <div class="text-sm text-slate-700">{{ row.template_item?.output_outcome ?? row.individual_target }}</div>
          <div class="text-sm text-slate-600">Accomplishment: {{ row.accomplishment || '—' }}</div>
          <div v-if="props.ipcr.status === 'Submitted for Rating'" class="grid grid-cols-4 gap-2">
            <input v-model.number="rateDrafts[row.id].sup_quality" type="number" min="1" max="5" placeholder="Quality" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm" />
            <input v-model.number="rateDrafts[row.id].sup_efficiency" type="number" min="1" max="5" placeholder="Efficiency" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm" />
            <input v-model.number="rateDrafts[row.id].sup_timeliness" type="number" min="1" max="5" placeholder="Timeliness" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm" />
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-sm" @click="saveRating(row)">Save</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
