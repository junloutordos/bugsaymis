<script setup>
import { computed } from 'vue'
import { CheckCircleIcon, ExclamationTriangleIcon, InformationCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  success: { type: String, default: null },
  error:   { type: String, default: null },
  warning: { type: String, default: null },
  info:    { type: String, default: null },
})

const config = computed(() => {
  if (props.error)   return { msg: props.error,   icon: XCircleIcon,            cls: 'bg-red-50 border-red-200 text-red-700' }
  if (props.warning) return { msg: props.warning,  icon: ExclamationTriangleIcon, cls: 'bg-amber-50 border-amber-200 text-amber-700' }
  if (props.info)    return { msg: props.info,     icon: InformationCircleIcon,   cls: 'bg-blue-50 border-blue-200 text-blue-700' }
  if (props.success) return { msg: props.success,  icon: CheckCircleIcon,         cls: 'bg-emerald-50 border-emerald-200 text-emerald-700' }
  return null
})
</script>

<template>
  <div v-if="config"
    class="mb-4 flex items-center gap-2 px-4 py-3 border rounded-xl text-sm"
    :class="config.cls">
    <component :is="config.icon" class="h-4 w-4 shrink-0" />
    {{ config.msg }}
  </div>
</template>
