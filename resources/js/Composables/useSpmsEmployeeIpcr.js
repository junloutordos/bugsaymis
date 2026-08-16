import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

export function useSpmsEmployeeIpcr(ipcr) {
  const generateTargets = () => {
    router.post(route('spms.ipcr.generate-targets', ipcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Done', 'Targets generated from your load assignments.', 'success'),
      onError: () => Swal.fire('Error', 'Could not generate targets.', 'error'),
    })
  }

  const submitTarget = () => {
    router.post(route('spms.ipcr.submit-target', ipcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Submitted', 'Target submitted for approval.', 'success'),
      onError: () => Swal.fire('Error', 'Could not submit target.', 'error'),
    })
  }

  const submitForRating = () => {
    router.post(route('spms.ipcr.submit-for-rating', ipcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Submitted', 'IPCR submitted for rating.', 'success'),
      onError: () => Swal.fire('Error', 'Could not submit for rating.', 'error'),
    })
  }

  return { generateTargets, submitTarget, submitForRating }
}
