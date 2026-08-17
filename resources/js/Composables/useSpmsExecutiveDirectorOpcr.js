import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

export function useSpmsExecutiveDirectorOpcr(opcr) {
  const approve = () => {
    router.post(route('spms.opcr.ed.approve', opcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Approved', 'OPCR approved.', 'success'),
    })
  }

  const setOverride = (overrideRating, overrideReason) => {
    router.post(route('spms.opcr.ed.set-override', opcr.value.id), {
      override_rating: overrideRating,
      override_reason: overrideReason,
    }, { preserveScroll: true })
  }

  const returnToSender = (reason) => {
    router.post(route('spms.opcr.ed.return', opcr.value.id), { reason }, { preserveScroll: true })
  }

  return { approve, setOverride, returnToSender }
}
