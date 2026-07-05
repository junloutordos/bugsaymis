import Swal from 'sweetalert2'

/**
 * Shared confirm-dialog helpers so pages stop hand-configuring SweetAlert2 per call site.
 * Both resolve to a boolean — true only if the user pressed the confirm button.
 */

const ATLAS_BLUE = '#0867DB'
const DANGER = '#DC2626'

export async function confirmAction({ title, text, confirmText = 'Confirm', icon = 'question' } = {}) {
  const result = await Swal.fire({
    title,
    text,
    icon,
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: 'Cancel',
    confirmButtonColor: ATLAS_BLUE,
    reverseButtons: true,
  })
  return result.isConfirmed
}

export async function confirmDelete(text = 'This action cannot be undone.') {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel',
    confirmButtonColor: DANGER,
    reverseButtons: true,
  })
  return result.isConfirmed
}

export function useConfirm() {
  return { confirmAction, confirmDelete }
}
