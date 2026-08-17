import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

export function useSpmsDivisionChiefDpcr(dpcr) {
  const generateTargets = () => {
    router.post(route('spms.dpcr.generate-targets', dpcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Done', 'Targets generated from division performance indicators.', 'success'),
      onError: () => Swal.fire('Error', 'Could not generate targets.', 'error'),
    })
  }

  const updateTargets = (targets) => {
    router.post(route('spms.dpcr.update-targets', dpcr.value.id), { targets }, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Saved', 'Accomplishments updated.', 'success'),
      onError: () => Swal.fire('Error', 'Could not save accomplishments.', 'error'),
    })
  }

  const submitToReviewer = () => {
    router.post(route('spms.dpcr.submit-to-reviewer', dpcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Submitted', 'DPCR submitted to reviewer.', 'success'),
      onError: () => Swal.fire('Error', 'Could not submit DPCR.', 'error'),
    })
  }

  const setOverride = (overrideRating, overrideReason) => {
    router.post(route('spms.dpcr.set-override', dpcr.value.id), {
      override_rating: overrideRating,
      override_reason: overrideReason,
    }, { preserveScroll: true })
  }

  return { generateTargets, updateTargets, submitToReviewer, setOverride }
}
