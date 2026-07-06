<script setup>
import AppBadge from "@/Components/AppBadge.vue";

const props = defineProps({
  equipment: Object
})

function statusColor(status) {
  return {
    active: 'green',
    in_use: 'blue',
    for_repair: 'amber',
    disposed: 'red',
  }[status] ?? 'slate'
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 p-6">
    <div class="bg-white shadow-sm border border-slate-100 rounded-xl p-6 max-w-lg w-full">
      <h1 class="font-heading text-xl font-semibold mb-4 text-center text-slate-800">Equipment Details</h1>

      <dl class="divide-y divide-slate-100 text-sm">
        <div class="flex justify-between py-2"><dt class="text-slate-500">Category</dt><dd class="font-medium text-slate-800">{{ props.equipment.category }}</dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Property No</dt><dd class="font-medium text-slate-800">{{ props.equipment.property_no }}</dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Serial No</dt><dd class="font-medium text-slate-800">{{ props.equipment.serial_no }}</dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Description</dt><dd class="font-medium text-slate-800">{{ props.equipment.description }}</dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Date Acquired</dt><dd class="font-medium text-slate-800">{{ props.equipment.date_acquired }}</dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Amount</dt><dd class="font-medium text-slate-800">{{ props.equipment.amount }}</dd></div>
        <div class="flex justify-between items-center py-2"><dt class="text-slate-500">Status</dt><dd><AppBadge :color="statusColor(props.equipment.status)" class="capitalize">{{ props.equipment.status }}</AppBadge></dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Room / Location</dt><dd class="font-medium text-slate-800">{{ props.equipment.room?.name ?? 'N/A' }}</dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Remarks</dt><dd class="font-medium text-slate-800">{{ props.equipment.remarks }}</dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Owner</dt><dd class="font-medium text-slate-800">{{ props.equipment.owner?.name }}</dd></div>
      </dl>
    </div>
  </div>
</template>
