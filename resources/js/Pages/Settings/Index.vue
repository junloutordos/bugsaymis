<script setup>
import AppPageHeader from '@/Components/AppPageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
</script>

<template>
  <div class="p-6">
    <AppPageHeader title="Settings" subtitle="Manage application settings and preferences" />
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
      <EmptyState title="Settings configuration will appear here" />
    </div>
  </div>
</template>
