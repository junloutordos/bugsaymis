<script setup>
const props = defineProps({
  equipment: Object
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 p-6">
    <div class="bg-white shadow-xl rounded-xl p-6 max-w-lg w-full">
      <h1 class="text-2xl font-bold mb-4 text-center">Equipment Details</h1>

      <div class="space-y-2">
        <p><strong>Category:</strong> {{ props.equipment.category }}</p>
        <p><strong>Property No:</strong> {{ props.equipment.property_no }}</p>
        <p><strong>Serial No:</strong> {{ props.equipment.serial_no }}</p>
        <p><strong>Description:</strong> {{ props.equipment.description }}</p>
        <p><strong>Date Acquired:</strong> {{ props.equipment.date_acquired }}</p>
        <p><strong>Amount:</strong> {{ props.equipment.amount }}</p>
        <p><strong>Status:</strong> {{ props.equipment.status }}</p>
        <p><strong>Location:</strong> {{ props.equipment.location }}</p>
        <p><strong>Remarks:</strong> {{ props.equipment.remarks }}</p>
        <p><strong>Owner:</strong> {{ props.equipment.owner?.name }}</p>
      </div>
    </div>
  </div>
</template>
