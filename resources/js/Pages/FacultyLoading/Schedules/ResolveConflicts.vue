<template>
  <Head title="Resolve Adjusted Schedule Conflicts" />
  <AdminLayout title="Resolve Adjusted Schedule Conflicts">
    <div class="space-y-5">
      <AppPageHeader
        :title="`Resolve Conflicts — ${formattedEffectiveDate}`"
        subtitle="Manually correct a flagged entry's time before publishing. Regular classroom/faculty assignments are unaffected."
      >
        <template #actions>
          <AppButton variant="secondary" as="link" :href="route('faculty-loading.schedules.day-adjustments.index')">
            <ArrowLeftIcon class="h-4 w-4" /> Back to Adjustments
          </AppButton>
        </template>
      </AppPageHeader>

      <AppCard v-if="warnings.length" padding="normal">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-amber-700">
          <ExclamationTriangleIcon class="h-5 w-5" /> {{ warnings.length }} possible conflict{{ warnings.length > 1 ? 's' : '' }} to review
        </div>
        <ul class="space-y-2">
          <li v-for="(warning, index) in warnings" :key="index" class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            {{ warning }}
          </li>
        </ul>
      </AppCard>

      <AppCard v-else padding="normal">
        <div class="flex items-center gap-2 text-sm font-semibold text-emerald-700">
          <CheckCircleIcon class="h-5 w-5" /> No conflicts detected in the current preview.
        </div>
      </AppCard>

      <AdjustedDayCalendar :preview="currentPreview" :adjustment="adjustment" @update:preview="value => currentPreview = value" />
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AdjustedDayCalendar from './AdjustedDayCalendar.vue'
import {
  ArrowLeftIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  adjustment: { type: Object, required: true },
  term: { type: Object, required: true },
  preview: { type: Object, required: true },
})

const currentPreview = ref(props.preview)

const warnings = computed(() => currentPreview.value.conflict_warnings ?? [])
const formattedEffectiveDate = computed(() =>
  new Date(`${props.adjustment.effective_date}T00:00:00`).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }))
</script>
