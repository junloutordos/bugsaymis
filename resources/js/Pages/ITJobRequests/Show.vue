<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({
  request: Object,
});
</script>

<template>
  <AdminLayout>
    <h1 class="text-xl font-bold mb-4">IT Job Request Details</h1>

    <div class="bg-white shadow rounded p-4">
      <p><strong>ID:</strong> {{ request.id }}</p>
      <p><strong>Submitted By:</strong> {{ request.user?.name }}</p>
      <p><strong>Request Type:</strong> {{ request.type }}</p>
      <p><strong>Status:</strong> {{ request.status }}</p>
      <p><strong>Created At:</strong> {{ request.created_at }}</p>
    </div>
  </AdminLayout>
</template>
