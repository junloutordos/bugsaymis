import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"
import { groupIndicatorsByProgram } from "@/Utils/OPCR/opcrGrouping.js"

export function useOpcr(props) {
  const groupedIndicators = computed(() => groupIndicatorsByProgram(props.indicators || []))

  // ── Indicator modal — edit-only. OPCR indicators are only ever created
  // via OpcrIndicatorPropagationService, sourced from a Performance
  // Indicator tagged to a PSHS Program; there is no manual creation or
  // deletion on the OPCR side (remove/reassign at the Performance Indicator
  // instead). Opening this modal always edits an existing indicator's
  // DOST tagging and remarks. ─────────────────────────────────────────────
  const showIndicatorModal = ref(false)
  const selectedIndicator = ref(null)

  const blankIndicatorForm = () => ({
    id: null,
    fiscal_year: "",
    agency_outcome_id: "",
    performance_indicator_id: "",
    description: "",
    target: "",
    budget: "",
    remarks: "",
    divisions: [],
  })

  const indicatorForm = ref(blankIndicatorForm())

  // True when this indicator was auto-propagated from a Performance
  // Indicator (see OpcrIndicatorPropagationService) — its fiscal year,
  // description/target/budget/Program/Divisions are mirrored from that
  // source and read-only here; only DOST tagging, actuals, ratings, and
  // remarks stay editable in OPCR.
  const isPropagatedIndicator = computed(() => Boolean(indicatorForm.value.performance_indicator_id))

  const openIndicatorModal = (indicator) => {
    showIndicatorModal.value = true
    selectedIndicator.value = indicator
    indicatorForm.value = {
      id: indicator.id,
      fiscal_year: indicator.fiscal_year,
      agency_outcome_id: indicator.agency_outcome_id ?? "",
      performance_indicator_id: indicator.performance_indicator_id ?? "",
      description: indicator.description ?? "",
      target: indicator.target ?? "",
      budget: indicator.budget ?? "",
      remarks: indicator.remarks ?? "",
      divisions: indicator.divisions ? [...indicator.divisions] : [],
    }
  }

  const closeIndicatorModal = () => {
    showIndicatorModal.value = false
    selectedIndicator.value = null
  }

  const submitIndicator = () => {
    const payload = {
      ...indicatorForm.value,
      division_ids: indicatorForm.value.divisions.map((d) => d.id),
    }

    router.put(route("opcr-indicators.update", indicatorForm.value.id), payload, {
      onSuccess: async () => {
        closeIndicatorModal()
        await Swal.fire("Success", "Indicator updated.", "success")
        router.reload({ only: ["indicators"] })
      },
      onError: async (errors) => {
        await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
      },
    })
  }

  const updateActual = (indicator, quarter, value) => {
    router.put(route("opcr-indicators.actual", indicator.id), { quarter, value }, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["indicators"] }),
    })
  }

  const updateRating = (indicator, ratings) => {
    router.put(route("opcr-indicators.rating", indicator.id), ratings, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["indicators"] }),
    })
  }

  // ── Signatories settings modal (Campus Director/OIC/ED names + commitment
  // statement) — one settings row, not per-FY, used on every PDF export ────
  const showSettingsModal = ref(false)
  const settingsForm = ref({
    campus_director_name: "",
    executive_director_name: "",
    commitment_statement: "",
  })

  const openSettingsModal = () => {
    showSettingsModal.value = true
    settingsForm.value = {
      campus_director_name: props.settings?.campus_director_name ?? "",
      executive_director_name: props.settings?.executive_director_name ?? "",
      commitment_statement: props.settings?.commitment_statement ?? "",
    }
  }

  const closeSettingsModal = () => {
    showSettingsModal.value = false
  }

  const submitSettings = () => {
    router.put(route("opcr-settings.update"), settingsForm.value, {
      onSuccess: async () => {
        closeSettingsModal()
        await Swal.fire("Success", "Signatories updated.", "success")
        router.reload({ only: ["settings"] })
      },
      onError: async (errors) => {
        await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
      },
    })
  }

  // ── Clone modal (copy one FY's indicators into another, empty, FY) ──────
  const showCloneModal = ref(false)
  const cloneForm = ref({ source_fiscal_year: "" })

  const openCloneModal = () => {
    showCloneModal.value = true
  }

  const closeCloneModal = () => {
    showCloneModal.value = false
  }

  const submitClone = (targetFiscalYear) => {
    router.post(route("opcr.clone"), {
      source_fiscal_year: cloneForm.value.source_fiscal_year,
      target_fiscal_year: targetFiscalYear,
    }, {
      onSuccess: async () => {
        closeCloneModal()
        await Swal.fire("Success", "Cloned successfully.", "success")
        window.location.reload()
      },
      onError: async (errors) => {
        await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
      },
    })
  }

  return {
    groupedIndicators,
    showIndicatorModal,
    selectedIndicator,
    indicatorForm,
    isPropagatedIndicator,
    openIndicatorModal,
    closeIndicatorModal,
    submitIndicator,
    updateActual,
    updateRating,
    showSettingsModal,
    settingsForm,
    openSettingsModal,
    closeSettingsModal,
    submitSettings,
    showCloneModal,
    cloneForm,
    openCloneModal,
    closeCloneModal,
    submitClone,
  }
}
