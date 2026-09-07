import { ref } from "vue"

/**
 * Manages a single pending PIN-confirm action for the DigitalSignaturePin
 * modal. Each caller of requestPin() replaces any previous pending action —
 * only one confirm flow is ever in-flight per page.
 */
export function usePinConfirm() {
  const showPinModal = ref(false)
  let pendingAction = null

  function requestPin(action) {
    pendingAction = action
    showPinModal.value = true
  }

  function confirmPin(pin) {
    showPinModal.value = false
    const action = pendingAction
    pendingAction = null
    if (action) action(pin)
  }

  function cancelPin() {
    showPinModal.value = false
    pendingAction = null
  }

  return { showPinModal, requestPin, confirmPin, cancelPin }
}
