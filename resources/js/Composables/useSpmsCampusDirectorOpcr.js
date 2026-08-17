import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

export function useSpmsCampusDirectorOpcr(opcr) {
  const generateTargets = () => {
    router.post(route('spms.opcr.generate-targets', opcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Done', 'Targets generated from campus-wide performance indicators.', 'success'),
      onError: () => Swal.fire('Error', 'Could not generate targets.', 'error'),
    })
  }

  const updateTargets = (targets) => {
    router.post(route('spms.opcr.update-targets', opcr.value.id), { targets }, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Saved', 'Accomplishments updated.', 'success'),
      onError: () => Swal.fire('Error', 'Could not save accomplishments.', 'error'),
    })
  }

  const submitToExecutiveDirector = () => {
    router.post(route('spms.opcr.submit-to-ed', opcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Submitted', 'OPCR submitted to Executive Director.', 'success'),
      onError: () => Swal.fire('Error', 'Could not submit OPCR.', 'error'),
    })
  }

  const setOverride = (overrideRating, overrideReason) => {
    router.post(route('spms.opcr.set-override', opcr.value.id), {
      override_rating: overrideRating,
      override_reason: overrideReason,
    }, { preserveScroll: true })
  }

  return { generateTargets, updateTargets, submitToExecutiveDirector, setOverride }
}
