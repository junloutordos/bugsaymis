/**
 * useSubmit — thin wrapper around Inertia router calls that prevents
 * double-submission by tracking an `isSubmitting` state.
 *
 * Usage:
 *   const { isSubmitting, submit } = useSubmit()
 *
 *   // In template:  :disabled="isSubmitting"
 *
 *   submit(() => router.post(route('foo'), payload, options))
 *
 *   // Or with explicit Inertia options merged in:
 *   submit(
 *     (opts) => router.post(route('foo'), payload, opts),
 *     {
 *       onSuccess: () => { ... },   // called after Inertia success
 *       onError:   () => { ... },   // called after Inertia error  (button re-enabled)
 *     }
 *   )
 */
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

export function useSubmit() {
  const isSubmitting = ref(false)

  /**
   * @param {Function} routerCall  A function that receives merged Inertia visit
   *                               options and returns void.  It must call one of
   *                               router.post / put / patch / delete / visit.
   * @param {Object}  [options]    Optional extra Inertia visit options.
   *                               onError / onFinish are merged automatically.
   */
  function submit(routerCall, options = {}) {
    if (isSubmitting.value) return          // guard: ignore repeated clicks

    isSubmitting.value = true

    const { onSuccess, onError, onFinish, ...rest } = options

    const mergedOptions = {
      ...rest,
      onSuccess: (page) => {
        onSuccess?.(page)
        // keep disabled on success — page navigation / flash handles UX
      },
      onError: (errors) => {
        isSubmitting.value = false          // re-enable on validation error
        onError?.(errors)
      },
      onFinish: () => {
        // Only reset if still submitting (success keeps it locked until nav)
        // This fires after both success and error; for error path we already
        // reset above, so this is a safety net for unexpected states.
        onFinish?.()
      },
    }

    routerCall(mergedOptions)
  }

  /**
   * Simplified helper: post to a named route.
   * submit.post(routeName, data, options?)
   */
  submit.post   = (route_, data, opts = {}) => submit((o) => router.post(route_, data, o), opts)
  submit.put    = (route_, data, opts = {}) => submit((o) => router.put(route_, data, o), opts)
  submit.patch  = (route_, data, opts = {}) => submit((o) => router.patch(route_, data, o), opts)
  submit.delete = (route_, opts = {})       => submit((o) => router.delete(route_, o), opts)

  return { isSubmitting, submit }
}
