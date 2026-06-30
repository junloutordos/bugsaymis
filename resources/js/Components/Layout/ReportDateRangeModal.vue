<script setup>
import { ref, watch } from 'vue'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppModal from '@/Components/AppModal.vue'

const props = defineProps({
  show:     { type: Boolean, default: false },
  title:    { type: String, required: true },
  action:   { type: String, default: 'Generate' },
  withType: { type: Boolean, default: false },
  typeValue:{ type: String, default: 'student' },
})

const emit = defineEmits(['close', 'submit'])

const start = ref('')
const end = ref('')
const reportType = ref(props.typeValue)

watch(() => props.show, (show) => {
  if (show) {
    start.value = ''
    end.value = ''
    reportType.value = props.typeValue
  }
})

function submit() {
  if (!start.value || !end.value) {
    alert('Please select both start and end dates.')
    return
  }
  if (start.value > end.value) {
    alert('Start date must be before or equal to end date.')
    return
  }
  emit('submit', { start: start.value, end: end.value, type: reportType.value })
}
</script>

<template>
  <AppModal :show="show" :title="title" size="md" @close="emit('close')">
    <div class="space-y-4">
      <div v-if="withType">
        <label class="mb-2 block text-xs font-medium text-slate-600">Report Type</label>
        <div class="flex flex-wrap items-center gap-4">
          <label v-for="option in ['student', 'employee', 'both']" :key="option" class="flex items-center gap-2 text-sm text-slate-700">
            <input v-model="reportType" type="radio" :value="option" class="border-slate-300 text-indigo-600 focus:ring-indigo-500" />
            <span class="capitalize">{{ option }}</span>
          </label>
        </div>
      </div>

      <AppInput v-model="start" type="date" label="Start Date" />
      <AppInput v-model="end" type="date" label="End Date" />
    </div>

    <template #footer>
      <AppButton variant="secondary" @click="emit('close')">Cancel</AppButton>
      <AppButton @click="submit">{{ action }}</AppButton>
    </template>
  </AppModal>
</template>
