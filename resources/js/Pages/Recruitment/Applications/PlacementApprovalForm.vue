<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'

const props = defineProps({
  application: { type: Object, required: true },
})

const PLANTILLA_NAMES = ['Plantilla Teaching', 'Plantilla Non-Teaching']
const jobItem = computed(() => props.application.job_vacancy?.job_item ?? {})
const isPlantilla = computed(() => PLANTILLA_NAMES.includes(jobItem.value.recruitment_type?.name))
const vacantPlantillaNumbers = computed(() => (jobItem.value.plantilla_numbers ?? []).filter(p => p.status === 'vacant'))

// Approval form
const approveForm   = ref({ assigned_office_id: jobItem.value.office_id ?? '', plantilla_number_id: '', start_date: '', end_date: '', remarks: '' })
const approveErrors = ref({})
const approveLoading = ref(false)

const submitApprove = () => {
  approveLoading.value = true
  approveErrors.value  = {}
  router.post(route('recruitment.placements.approve', props.application.id), approveForm.value, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Selection Approved! Placement created.', timer: 2000, showConfirmButton: false }),
    onError:   (e) => { approveErrors.value = e },
    onFinish:  () => { approveLoading.value = false },
  })
}

const disapprove = async () => {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: 'Disapprove Selection',
    input: 'textarea', inputLabel: 'Reason *',
    inputPlaceholder: 'Enter reason for disapproval…',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Disapprove',
    reverseButtons: true,
  })
  if (!isConfirmed || !reason?.trim()) return

  router.post(route('recruitment.placements.disapprove', props.application.id), { reason }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Selection disapproved.', timer: 1500, showConfirmButton: false }),
    onError:   (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}
</script>

<template>
  <div class="space-y-4 border border-slate-100 rounded-xl p-4 bg-slate-50">
    <h4 class="text-sm font-semibold text-slate-700">Approve Selection &amp; Create Placement</h4>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div v-if="isPlantilla" class="sm:col-span-2">
        <AppSelect v-model="approveForm.plantilla_number_id" label="Plantilla Item No." required
                   :error="approveErrors.plantilla_number_id" placeholder="Select the item number to fill">
          <option v-for="p in vacantPlantillaNumbers" :key="p.id" :value="p.id">{{ p.plantilla_item_no }}</option>
        </AppSelect>
        <p v-if="!vacantPlantillaNumbers.length" class="text-warning-600 text-xs mt-1">No vacant plantilla item numbers left on this job item.</p>
      </div>
      <AppInput v-model="approveForm.start_date" type="date" label="Start Date" required :error="approveErrors.start_date" />
      <AppInput v-model="approveForm.end_date" type="date" label="End Date (leave blank if permanent)" />
      <div class="sm:col-span-2">
        <AppTextarea v-model="approveForm.remarks" :rows="2" label="Remarks" placeholder="Optional remarks…" />
      </div>
    </div>

    <div class="flex gap-3">
      <AppButton :loading="approveLoading" :disabled="approveLoading" @click="submitApprove">
        {{ approveLoading ? 'Approving…' : 'Approve & Place' }}
      </AppButton>
      <AppButton variant="danger" @click="disapprove">Disapprove</AppButton>
    </div>
  </div>
</template>
