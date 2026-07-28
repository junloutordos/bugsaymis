<script setup>
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppButton from '@/Components/AppButton.vue'
import { PlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  sections: { type: Array, required: true },
  sectionId: { type: [Number, String], required: true },
  logs: { type: Array, required: true },
})

const sectionModel = ref(String(props.sectionId))

function goto() {
  router.get(route('homeroom-attendance.activities.index'), { section: sectionModel.value }, { preserveState: false })
}

function formatDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<template>
  <Head title="Activity Attendance" />
  <AdminLayout title="Activity Attendance">
    <AppPageHeader title="Attendance During Activities" subtitle="School-organized activities and off-campus events">
      <template #actions>
        <AppButton :as="'link'" :href="route('homeroom-attendance.activities.create', { section: sectionId })" size="sm">
          <PlusIcon class="h-4 w-4" /> Log New Activity
        </AppButton>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-4 mb-4">
      <AppSelect v-model="sectionModel" label="Section" :show-blank="false" @update:modelValue="goto">
        <option v-for="s in sections" :key="s.id" :value="String(s.id)">Grade {{ s.level }} - {{ s.name }}</option>
      </AppSelect>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Activity</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
            <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Off-Campus</th>
            <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Students Logged</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="!logs.length">
            <td colspan="4" class="px-4 py-8 text-center text-slate-400 text-sm">No activities logged yet for this section.</td>
          </tr>
          <tr v-for="log in logs" :key="log.id">
            <td class="px-4 py-2 text-slate-700 font-medium">{{ log.title }}</td>
            <td class="px-4 py-2 text-slate-500">{{ formatDate(log.activity_date) }}</td>
            <td class="px-4 py-2 text-center">{{ log.is_off_campus ? 'Yes' : 'No' }}</td>
            <td class="px-4 py-2 text-center">{{ log.records_count }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
