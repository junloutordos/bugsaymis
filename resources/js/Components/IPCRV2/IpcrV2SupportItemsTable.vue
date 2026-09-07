<script setup>
import AppCard from "@/Components/AppCard.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import { TH, TD } from "@/Composables/useTableClasses.js"
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
</script>

<template>
  <AppCard>
    <h3 class="text-sm font-semibold text-slate-700 mb-1">Support Function (20%)</h3>
    <table class="w-full">
      <thead>
        <tr>
          <th :class="TH">Item</th>
          <th :class="TH">Actual Accomplishment</th>
          <th :class="TH">MOV</th>
          <th :class="TH">Row Average</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td :class="TD">{{ item.label }}</td>
          <td :class="TD">
            <AppTextarea v-if="isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
            <span v-else>{{ item.actual_accomplishment ?? "—" }}</span>
          </td>
          <td :class="TD">
            <input v-if="isOwner && isMutable" v-model="item.mov_link" class="border rounded px-2 py-1 text-xs w-full" @blur="saveEmployeeFields(item)" />
            <span v-else>{{ item.mov_link ?? "—" }}</span>
          </td>
          <td :class="TD">{{ item.row_average ?? "—" }}</td>
        </tr>
        <tr v-if="!items.length">
          <td :class="TD" colspan="4">No Support Function rows yet.</td>
        </tr>
      </tbody>
    </table>
  </AppCard>
</template>
