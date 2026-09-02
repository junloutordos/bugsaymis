<script setup>
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ ipcr: Object, weightTargets: Object, weightSums: Object, isMutable: Boolean })

const FUNCTION_LABELS = { strategic: 'Strategic Function', core: 'Core Function', support: 'Support Function' }

const rowsByType = (type) => props.ipcr.rows.filter((r) => r.function_type === type)

const weightDrafts = reactive(
  Object.fromEntries(props.ipcr.rows.map((r) => [r.id, r.weight_percent ?? '']))
)

const selfRateDrafts = reactive(
  Object.fromEntries(props.ipcr.rows.map((r) => [r.id, {
    accomplishment: r.accomplishment ?? '',
    mov_link: r.mov_link ?? '',
    self_quality: r.self_quality,
    self_efficiency: r.self_efficiency,
    self_timeliness: r.self_timeliness,
  }]))
)

const saveWeight = (row) => {
  router.put(route('pm2.employee-ipcr.updateRowWeight', [props.ipcr.id, row.id]), {
    weight_percent: weightDrafts[row.id],
  }, { preserveScroll: true })
}

const saveSelfRate = (row) => {
  router.put(route('pm2.employee-ipcr.selfRate', [props.ipcr.id, row.id]), selfRateDrafts[row.id], { preserveScroll: true })
}

const submitForRating = () => {
  router.post(route('pm2.employee-ipcr.submitRating', props.ipcr.id))
}
</script>

<template>
  <Head :title="`PM V2 — ${props.ipcr.title}`" />
  <AdminLayout :title="props.ipcr.title">
    <div class="p-6 space-y-6">
      <div class="bg-white rounded-lg border border-slate-200 p-4 flex items-center justify-between">
        <div>
          <div class="font-semibold text-slate-800">{{ props.ipcr.title }}</div>
          <div class="text-sm text-slate-500">{{ props.ipcr.rating_period?.label }} — {{ props.ipcr.status }}</div>
        </div>
        <div class="flex gap-2">
          <a :href="route('pm2.employee-ipcr.pdf', props.ipcr.id)" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Download PDF</a>
          <button
            v-if="props.ipcr.status === 'Targets Approved'"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
            @click="submitForRating"
          >Submit for Rating</button>
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
          <div class="flex items-center gap-2">
            <label class="text-xs text-slate-500">Weight %</label>
            <input
              v-model.number="weightDrafts[row.id]"
              type="number" step="0.01"
              :disabled="!props.isMutable || props.ipcr.status !== 'New Target'"
              class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm w-24"
              @blur="saveWeight(row)"
            />
          </div>
          <div v-if="props.ipcr.status === 'Targets Approved'" class="grid grid-cols-3 gap-2">
            <input v-model="selfRateDrafts[row.id].accomplishment" placeholder="Accomplishment" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm col-span-3" />
            <input v-model.number="selfRateDrafts[row.id].self_quality" type="number" min="1" max="5" placeholder="Quality" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm" />
            <input v-model.number="selfRateDrafts[row.id].self_efficiency" type="number" min="1" max="5" placeholder="Efficiency" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm" />
            <input v-model.number="selfRateDrafts[row.id].self_timeliness" type="number" min="1" max="5" placeholder="Timeliness" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm" />
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-sm col-span-3 w-fit" @click="saveSelfRate(row)">Save Self-Rating</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
