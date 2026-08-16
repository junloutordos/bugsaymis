import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Swal from 'sweetalert2'

export function useSpmsReviewerIpcr(ipcr) {
  const ratings = ref({})

  const approveTarget = () => {
    router.post(route('spms.ipcr.review.approve-target', ipcr.value.id), {}, { preserveScroll: true })
  }

  const submitRatings = () => {
    router.post(route('spms.ipcr.review.rate', ipcr.value.id), { ratings: ratings.value }, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Rated', 'IPCR rating saved.', 'success'),
    })
  }

  const finalizeIpcr = () => {
    router.post(route('spms.ipcr.review.finalize', ipcr.value.id), {}, { preserveScroll: true })
  }

  const returnToSender = (reason) => {
    router.post(route('spms.ipcr.review.return', ipcr.value.id), { reason }, { preserveScroll: true })
  }

  return { ratings, approveTarget, submitRatings, finalizeIpcr, returnToSender }
}
