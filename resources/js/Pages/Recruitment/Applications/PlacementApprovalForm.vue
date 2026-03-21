<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  application: { type: Object, required: true },
})

// Approval form
const approveForm   = ref({ assigned_office_id: props.application.job_vacancy?.job_item?.office_id ?? '', start_date: '', end_date: '', remarks: '' })
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
    confirmButtonColor: '#ef4444',
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
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Start Date *</label>
        <input v-model="approveForm.start_date" type="date" required
               class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        <p v-if="approveErrors.start_date" class="text-red-500 text-xs mt-1">{{ approveErrors.start_date }}</p>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">End Date <span class="text-slate-400">(leave blank if permanent)</span></label>
        <input v-model="approveForm.end_date" type="date"
               class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
      </div>
      <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
        <textarea v-model="approveForm.remarks" rows="2"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                  placeholder="Optional remarks…"></textarea>
      </div>
    </div>

    <div class="flex gap-3">
      <button @click="submitApprove" :disabled="approveLoading"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
        {{ approveLoading ? 'Approving…' : 'Approve & Place' }}
      </button>
      <button @click="disapprove"
              class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
        Disapprove
      </button>
    </div>
  </div>
</template>
