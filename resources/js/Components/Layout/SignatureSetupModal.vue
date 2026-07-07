<script setup>
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'
import { PencilSquareIcon, CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

defineProps({
  show: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])

const page = usePage()
const user = computed(() => page.props.auth?.user ?? {})

const items = computed(() => [
  {
    label: 'Signature image',
    hint:  'Drawn or uploaded — stamped on documents you sign',
    done:  !!user.value.electronic_signature,
  },
  {
    label: 'Signing PIN',
    hint:  '6-digit PIN that authorizes each signature',
    done:  !!user.value.has_signature_pin,
  },
])

function goToSetup() {
  emit('close')
  router.visit(route('profile.signature'))
}
</script>

<template>
  <AppModal :show="show" title="Set up your digital signature" size="md"
    :close-on-backdrop="false" @close="$emit('close')">

    <div class="space-y-4">
      <div class="flex items-start gap-3">
        <div class="shrink-0 h-10 w-10 rounded-full bg-indigo-50 flex items-center justify-center">
          <PencilSquareIcon class="h-5 w-5 text-indigo-600" />
        </div>
        <p class="text-sm text-slate-600 leading-relaxed">
          Your digital signature is used to sign IPCRs, issuances, requests, and other
          official documents in the MIS. It only takes a minute to set up.
        </p>
      </div>

      <div class="space-y-2">
        <div v-for="item in items" :key="item.label"
          class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5"
          :class="item.done ? 'border-success-100 bg-success-50' : 'border-warning-100 bg-warning-50'">
          <CheckCircleIcon v-if="item.done" class="h-5 w-5 shrink-0 text-success-600" />
          <ExclamationTriangleIcon v-else class="h-5 w-5 shrink-0 text-warning-500" />
          <div class="min-w-0">
            <p class="text-sm font-medium" :class="item.done ? 'text-success-700' : 'text-warning-700'">
              {{ item.label }} — {{ item.done ? 'done' : 'not set' }}
            </p>
            <p class="text-xs text-slate-500">{{ item.hint }}</p>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <AppButton variant="ghost" @click="$emit('close')">Remind Me Later</AppButton>
      <AppButton @click="goToSetup">
        <PencilSquareIcon class="h-4 w-4" /> Set Up Now
      </AppButton>
    </template>
  </AppModal>
</template>
