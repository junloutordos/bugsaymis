import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

export function useSpmsReviewerDpcr(dpcr) {
  const review = () => {
    router.post(route('spms.dpcr.review.review', dpcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Reviewed', 'Rollup rating computed from division IPCRs.', 'success'),
    })
  }

  const submitToApprover = () => {
    router.post(route('spms.dpcr.review.submit-to-approver', dpcr.value.id), {}, { preserveScroll: true })
  }

  const approve = () => {
    router.post(route('spms.dpcr.review.approve', dpcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Approved', 'DPCR approved.', 'success'),
    })
  }

  const setOverride = (overrideRating, overrideReason) => {
    router.post(route('spms.dpcr.review.set-override', dpcr.value.id), {
      override_rating: overrideRating,
      override_reason: overrideReason,
    }, { preserveScroll: true })
  }

  const returnToSender = (reason) => {
    router.post(route('spms.dpcr.review.return', dpcr.value.id), { reason }, { preserveScroll: true })
  }

  return { review, submitToApprover, approve, setOverride, returnToSender }
}
