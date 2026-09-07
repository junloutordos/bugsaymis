<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ref, computed } from "vue"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({ records: Array })
const { isSubmitting, submit } = useSubmit()
const selected = ref([])

const submittedIds = computed(() => props.records.filter(r => r.status === "Submitted to HR").map(r => r.id))

function batchSubmit() {
  submit((opts) => router.post(route("hr-ipcr-v2.batchSubmitToPMT"), { ids: selected.value }, opts), {
    onSuccess: () => { selected.value = [] },
  })
}
</script>

<template>
  <Head title="HR — IPCR V2" />
  <AdminLayout title="HR — IPCR V2 Monitoring">
    <AppPageHeader title="HR IPCR V2 Monitoring" />
    <AppCard>
      <div class="flex justify-end mb-3">
        <AppButton :disabled="isSubmitting || !selected.length" @click="batchSubmit">Batch Submit to PMT ({{ selected.length }})</AppButton>
      </div>
      <table class="w-full">
        <thead>
          <tr>
            <th class="px-4 py-2"></th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Employee</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Period</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id">
            <td class="px-4 py-3">
              <input v-if="record.status === 'Submitted to HR'" type="checkbox" :value="record.id" v-model="selected" />
            </td>
            <td class="px-4 py-3 text-sm">{{ record.user?.name }}</td>
            <td class="px-4 py-3 text-sm">{{ record.period?.label }}</td>
            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(record.status)">{{ record.status }}</span></td>
            <td class="px-4 py-3 text-right">
              <AppButton variant="secondary" @click="router.get(route('hr-ipcr-v2.show', record.id))">View</AppButton>
            </td>
          </tr>
          <tr v-if="!records.length">
            <td class="px-4 py-3 text-sm text-slate-500" colspan="5">No IPCR V2 records yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </AdminLayout>
</template>
