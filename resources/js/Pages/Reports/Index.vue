<script setup>
import AppPageHeader from '@/Components/AppPageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
</script>

<template>
  <div class="p-6">
    <AppPageHeader title="Reports" subtitle="View and generate system reports" />
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
      <EmptyState title="Select a report type from the navigation to get started" />
    </div>
  </div>
</template>
