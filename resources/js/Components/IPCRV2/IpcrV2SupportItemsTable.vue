<script setup>
import AppTextarea from "@/Components/AppTextarea.vue"
import { TD } from "@/Composables/useTableClasses.js"
import { router } from "@inertiajs/vue3"
import { computed } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import { groupConsecutiveByFunction } from "@/Composables/ipcrV2FunctionGrouping.js"

const props = defineProps({
  ipcrId: [Number, String],
  items: { type: Array, default: () => [] },
  isOwner: Boolean,
  isMutable: Boolean,
  canRate: { type: Boolean, default: false },
})

const { submit } = useSubmit()

const rows = computed(() => groupConsecutiveByFunction(props.items))

function saveEmployeeFields(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
    target: item.target,
    actual_accomplishment: item.actual_accomplishment,
    mov_link: item.mov_link,
  }, opts))
}

function rate(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateSupportItem", [props.ipcrId, item.id]), {
    quality_rating: item.quality_rating,
    efficiency_rating: item.efficiency_rating,
    timeliness_rating: item.timeliness_rating,
    remarks: item.remarks,
  }, opts))
}

function rateSelf(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
    self_quality_rating: item.self_quality_rating,
    self_efficiency_rating: item.self_efficiency_rating,
    self_timeliness_rating: item.self_timeliness_rating,
  }, opts))
}
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="15" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Support Function (20%)
      </td>
    </tr>
    <tr v-for="row in rows" :key="row.item.id">
      <td v-if="row.isFirst" :rowspan="row.groupSize" :class="TD" class="border border-slate-200 align-top font-medium">{{ row.item.label }}</td>
      <td v-if="row.isFirst" :rowspan="row.groupSize" class="border border-slate-200 px-4 py-3 text-sm text-slate-300 align-top">—</td>
      <td v-if="row.isFirst" :rowspan="row.groupSize" class="border border-slate-200 px-4 py-3 text-sm text-slate-300 align-top">—</td>
      <td :class="TD" class="border border-slate-200 align-top text-slate-500">{{ row.item.success_indicator ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200 align-top">
        <AppTextarea v-if="isOwner && isMutable" v-model="row.item.target" @blur="saveEmployeeFields(row.item)" />
        <span v-else>{{ row.item.target ?? "—" }}</span>
      </td>
      <td :class="TD" class="border border-slate-200 align-top">
        <AppTextarea v-if="isOwner && isMutable" v-model="row.item.actual_accomplishment" @blur="saveEmployeeFields(row.item)" />
        <span v-else>{{ row.item.actual_accomplishment ?? "—" }}</span>
        <input v-if="isOwner && isMutable" v-model="row.item.mov_link" placeholder="MOV link" class="border rounded px-2 py-1 text-xs w-full mt-1" @blur="saveEmployeeFields(row.item)" />
        <small v-else-if="row.item.mov_link" class="block text-slate-400 mt-1">MOV: {{ row.item.mov_link }}</small>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="isOwner && isMutable" v-model.number="row.item.self_quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.self_quality_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="isOwner && isMutable" v-model.number="row.item.self_efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.self_efficiency_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="isOwner && isMutable" v-model.number="row.item.self_timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.self_timeliness_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
        {{ row.item.self_row_average ?? "—" }}
        <button v-if="isOwner && isMutable" type="button" class="block mt-1 text-xs text-indigo-600" @click="rateSelf(row.item)">Save Self-Rating</button>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="row.item.quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.quality_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="row.item.efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.efficiency_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="row.item.timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.timeliness_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
        {{ row.item.row_average ?? "—" }}
        <button v-if="canRate" type="button" class="block mt-1 text-xs text-indigo-600" @click="rate(row.item)">Save</button>
      </td>
      <td :class="TD" class="border border-slate-200 align-top">
        <input v-if="canRate" v-model="row.item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
        <span v-else>{{ row.item.remarks ?? "—" }}</span>
      </td>
    </tr>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" colspan="15">No Support Function rows yet.</td>
    </tr>
  </tbody>
</template>
