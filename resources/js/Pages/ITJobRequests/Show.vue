<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({
  request: Object,
});
</script>

<template>
  <AdminLayout>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <h1 class="text-xl font-semibold text-slate-800">IT Job Request Details</h1>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 space-y-3 text-sm text-slate-700">
      <p><strong>ID:</strong> {{ request.id }}</p>
      <p><strong>Submitted By:</strong> {{ request.user?.name }}</p>
      <p><strong>Request Type:</strong> {{ request.type }}</p>
      <p><strong>Status:</strong> {{ request.status }}</p>
      <p><strong>Created At:</strong> {{ request.created_at }}</p>
    </div>
  </AdminLayout>
</template>
