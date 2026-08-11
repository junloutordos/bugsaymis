<script setup>
import { computed } from 'vue'
import { COURSE_COVER_PRESETS } from '@/Constants/courseCoverPresets'

const props = defineProps({
  photoUrl: { type: String, default: null },
  preset: { type: String, default: null },
  initials: { type: String, default: '' },
})

const activePreset = computed(() => COURSE_COVER_PRESETS.find(p => p.key === props.preset) ?? COURSE_COVER_PRESETS[0])
</script>

<template>
  <div class="relative overflow-hidden">
    <img v-if="photoUrl" :src="photoUrl" class="h-full w-full object-cover" alt="" />
    <div v-else :class="['flex h-full w-full items-center justify-center', activePreset.class]">
      <span class="font-heading text-3xl font-bold tracking-wide text-white/25">{{ initials }}</span>
    </div>
  </div>
</template>
