<script setup>
import { useForm } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'

const props = defineProps({
  deductionPoints: { type: Object, required: true },
  defaults: { type: Object, required: true },
})

const form = useForm({
  tardy: props.deductionPoints.tardy,
  cutting: props.deductionPoints.cutting,
  absence: props.deductionPoints.absence,
})

function submit() {
  form.patch(route('homeroom-attendance.settings.update'), { preserveScroll: true })
}

function resetToDefaults() {
  form.tardy = props.defaults.tardy
  form.cutting = props.defaults.cutting
  form.absence = props.defaults.absence
}
</script>

<template>
  <Head title="Homeroom Attendance Settings" />
  <AdminLayout title="Homeroom Attendance Settings">
    <AppPageHeader title="Deduction Points" subtitle="Used to compute Days Present on the monthly Record on Attendance and Punctuality (CIM 7.0 defaults shown)" />

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-6 max-w-md">
      <div class="space-y-4">
        <AppInput v-model.number="form.tardy" type="number" step="0.05" label="Tardy" :error="form.errors.tardy" />
        <AppInput v-model.number="form.cutting" type="number" step="0.05" label="Cutting Class" :error="form.errors.cutting" />
        <AppInput v-model.number="form.absence" type="number" step="0.05" label="Absence (Unexcused)" :error="form.errors.absence" />
      </div>
      <p class="text-xs text-slate-400 mt-3">
        CIM 7.0 defaults: Tardy {{ defaults.tardy }}, Cutting {{ defaults.cutting }}, Absence {{ defaults.absence }}.
        Excused absences never reduce Days Present.
      </p>
      <div class="mt-5 flex gap-2">
        <AppButton variant="secondary" size="sm" @click="resetToDefaults">Reset to Defaults</AppButton>
        <AppButton size="sm" :loading="form.processing" @click="submit">Save</AppButton>
      </div>
    </div>
  </AdminLayout>
</template>
