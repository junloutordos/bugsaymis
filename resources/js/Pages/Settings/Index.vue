<script setup>
import AppPageHeader from '@/Components/AppPageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
</script>

<template>
  <div class="p-6">
    <AppPageHeader title="Settings" subtitle="Manage application settings and preferences" />
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
      <EmptyState title="Settings configuration will appear here" />
    </div>
  </div>
</template>
