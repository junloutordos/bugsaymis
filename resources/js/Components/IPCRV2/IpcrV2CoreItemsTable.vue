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
  submit((opts) => router.put(route("employee-ipcr-v2.updateCoreItem", [props.ipcrId, item.id]), {
    target: item.target,
    actual_accomplishment: item.actual_accomplishment,
  }, opts))
}

function rowAverage(item) {
  const parts = [item.student_feedback_rating, item.supervisor_feedback_rating, item.im_development_rating, item.timeliness_rating]
  if (parts.some(v => v === null || v === undefined)) return "—"
  const weighted = parts[0] * 0.3 + parts[1] * 0.2 + parts[2] * 0.2 + parts[3] * 0.3
  return weighted.toFixed(2)
}
</script>

<template>
  <AppCard class="mb-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-1">Core Function (50%)</h3>
    <p class="text-xs text-slate-500 mb-4">
      Student feedback 30% / Supervisor feedback 20% / IM development 20% / Timeliness 30%.
    </p>
    <table class="w-full">
      <thead>
        <tr>
          <th :class="TH">Subject / Designation</th>
          <th :class="TH">Weight %</th>
          <th :class="TH">Target</th>
          <th :class="TH">Actual Accomplishment</th>
          <th :class="TH">Row Average</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td :class="TD">{{ item.label }}</td>
          <td :class="TD">{{ item.weight_percent ?? "—" }}</td>
          <td :class="TD">
            <AppTextarea v-if="isOwner && isMutable" v-model="item.target" @blur="saveEmployeeFields(item)" />
            <span v-else>{{ item.target ?? "—" }}</span>
          </td>
          <td :class="TD">
            <AppTextarea v-if="isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
            <span v-else>{{ item.actual_accomplishment ?? "—" }}</span>
          </td>
          <td :class="TD">{{ item.row_average ?? rowAverage(item) }}</td>
        </tr>
        <tr v-if="!items.length">
          <td :class="TD" colspan="5">No Core Function rows yet — generate targets from Employee Functions.</td>
        </tr>
      </tbody>
    </table>
  </AppCard>
</template>
