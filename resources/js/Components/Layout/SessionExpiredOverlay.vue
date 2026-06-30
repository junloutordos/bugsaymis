<script setup>
import { router } from '@inertiajs/vue3'
import AppButton from '@/Components/AppButton.vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

defineProps({
  show: { type: Boolean, default: false },
})
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
      <div class="w-full max-w-sm rounded-2xl bg-white px-8 py-8 text-center shadow-2xl">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
          <ExclamationTriangleIcon class="h-7 w-7 text-amber-600" />
        </div>
        <h2 class="mb-1 text-lg font-semibold text-slate-800">Session Expired</h2>
        <p class="mb-6 text-sm text-slate-500">Your session has timed out. Please reload the page to continue.</p>
        <AppButton block @click="router.visit(window.location.pathname)">Reload Page</AppButton>
      </div>
    </div>
  </Teleport>
</template>
