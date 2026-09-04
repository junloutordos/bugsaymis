import { ref, computed } from "vue"
import axios from "axios"
import Swal from "sweetalert2"

const blankState = () => ({
  pillar: { mode: "skip", id: "", name: "", outcome_statement: "" },
  strategy: { mode: "skip", id: "", name: "" },
  sub_strategy: { mode: "skip", id: "", description: "" },
  program: { mode: "skip", id: "", outcome: "", function_type: "" },
})

/**
 * Shared "select existing, create new, or skip" chain builder for
 * Pillar -> Strategy -> Sub-Strategy -> PSHS Program, backed by the
 * single atomic POST /dost-chain endpoint. Used standalone on the DOST
 * Strategic Plan page and embedded inside OPCR's indicator modal.
 */
export function useDostChainWizard(props) {
  const state = ref(blankState())
  const submitting = ref(false)

  const reset = () => {
    state.value = blankState()
  }

  const availableStrategies = computed(() => {
    if (state.value.pillar.mode !== "existing" || !state.value.pillar.id) return []
    const pillar = props.pillars.find((p) => p.id === Number(state.value.pillar.id))
    return pillar?.strategies ?? []
  })

  const availableSubStrategies = computed(() => {
    if (state.value.strategy.mode !== "existing" || !state.value.strategy.id) return []
    const strategy = availableStrategies.value.find((s) => s.id === Number(state.value.strategy.id))
    return strategy?.sub_strategies ?? []
  })

  const submit = async () => {
    submitting.value = true
    try {
      const { data } = await axios.post(route("dost-chain.store"), state.value)
      reset()
      return data
    } catch (error) {
      const errors = error.response?.data?.errors
      await Swal.fire("Error", errors ? Object.values(errors).flat().join(", ") : "Something went wrong.", "error")
      return null
    } finally {
      submitting.value = false
    }
  }

  return {
    state,
    submitting,
    availableStrategies,
    availableSubStrategies,
    reset,
    submit,
  }
}
