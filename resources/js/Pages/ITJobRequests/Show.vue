<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from "@/Components/AppPageHeader.vue";
import AppCard from "@/Components/AppCard.vue";
import AppBadge from "@/Components/AppBadge.vue";

const props = defineProps({
  request: Object,
});

function statusColor(status) {
  return {
    pending: 'amber',
    approved: 'green',
    in_progress: 'blue',
    completed: 'green',
    rejected: 'red',
  }[status] ?? 'slate'
}
</script>

<template>
  <AdminLayout>
    <AppPageHeader title="IT Job Request Details" />

    <AppCard>
      <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
          <dt class="text-xs text-slate-500">ID</dt>
          <dd class="font-medium text-slate-800 mt-0.5">{{ request.id }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Submitted By</dt>
          <dd class="font-medium text-slate-800 mt-0.5">{{ request.user?.name }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Request Type</dt>
          <dd class="font-medium text-slate-800 mt-0.5">{{ request.type }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Status</dt>
          <dd class="mt-0.5"><AppBadge :color="statusColor(request.status)" class="capitalize">{{ request.status }}</AppBadge></dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Created At</dt>
          <dd class="font-medium text-slate-800 mt-0.5">{{ request.created_at }}</dd>
        </div>
      </dl>
    </AppCard>
  </AdminLayout>
</template>
