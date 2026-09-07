<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import AppSelect from "@/Components/AppSelect.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ref } from "vue"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  records: Array,
  openPeriods: Array,
})

const { isSubmitting, submit } = useSubmit()
const selectedPeriod = ref(props.openPeriods[0]?.id ?? null)

function generateTargets() {
  submit((opts) => router.post(route("employee-ipcr-v2.generateTargets"), { rating_period_id: selectedPeriod.value }, opts))
}
</script>

<template>
  <Head title="My IPCR V2" />
  <AdminLayout title="My IPCR V2">
    <AppPageHeader title="My IPCR V2" subtitle="Strategic / Core / Support Functions" />

    <AppCard class="mb-6" v-if="openPeriods.length">
      <div class="flex items-end gap-3">
        <AppSelect v-model="selectedPeriod" label="Rating Period" :show-blank="false">
          <option v-for="p in openPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
        </AppSelect>
        <AppButton :disabled="isSubmitting || !selectedPeriod" @click="generateTargets">Generate Targets</AppButton>
      </div>
    </AppCard>

    <AppCard>
      <table class="w-full">
        <thead>
          <tr>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Period</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Status</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Final Rating</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id">
            <td class="px-4 py-3 text-sm">{{ record.period?.label }}</td>
            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(record.status)">{{ record.status }}</span></td>
            <td class="px-4 py-3 text-sm">{{ record.final_numeric_rating ?? "—" }}</td>
            <td class="px-4 py-3 text-right">
              <AppButton variant="secondary" @click="router.get(route('employee-ipcr-v2.show', record.id))">View</AppButton>
            </td>
          </tr>
          <tr v-if="!records.length">
            <td class="px-4 py-3 text-sm text-slate-500" colspan="4">No IPCR V2 records yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </AdminLayout>
</template>
