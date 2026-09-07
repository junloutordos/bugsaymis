<script setup>
import AppTextarea from "@/Components/AppTextarea.vue"
import { TD } from "@/Composables/useTableClasses.js"
import { router } from "@inertiajs/vue3"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcrId: [Number, String],
  items: { type: Array, default: () => [] },
  isOwner: Boolean,
  isMutable: Boolean,
  canRate: { type: Boolean, default: false },
})

const { submit } = useSubmit()

function saveEmployeeFields(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
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
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="11" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Support Function (20%)
      </td>
    </tr>
    <tr v-for="item in items" :key="item.id">
      <td :class="TD" class="border border-slate-200 font-medium">{{ item.label }}</td>
      <td class="border border-slate-200 px-4 py-3 text-sm text-slate-300">—</td>
      <td class="border border-slate-200 px-4 py-3 text-sm text-slate-300">—</td>
      <td class="border border-slate-200 px-4 py-3 text-sm text-slate-400">100% delivered</td>
      <td class="border border-slate-200 px-4 py-3 text-sm text-slate-300">—</td>
      <td :class="TD" class="border border-slate-200">
        <AppTextarea v-if="isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
        <span v-else>{{ item.actual_accomplishment ?? "—" }}</span>
        <input v-if="isOwner && isMutable" v-model="item.mov_link" placeholder="MOV link" class="border rounded px-2 py-1 text-xs w-full mt-1" @blur="saveEmployeeFields(item)" />
        <small v-else-if="item.mov_link" class="block text-slate-400 mt-1">MOV: {{ item.mov_link }}</small>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="item.quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ item.quality_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="item.efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ item.efficiency_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="item.timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ item.timeliness_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
        {{ item.row_average ?? "—" }}
        <button v-if="canRate" type="button" class="block mt-1 text-xs text-indigo-600" @click="rate(item)">Save</button>
      </td>
      <td :class="TD" class="border border-slate-200">
        <input v-if="canRate" v-model="item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
        <span v-else>{{ item.remarks ?? "—" }}</span>
      </td>
    </tr>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" colspan="11">No Support Function rows yet.</td>
    </tr>
  </tbody>
</template>
