import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"
import { groupIndicatorsByProgram } from "@/Utils/OPCR/opcrGrouping.js"

export function useOpcr(props) {
  const groupedIndicators = computed(() => groupIndicatorsByProgram(props.indicators || []))

  // ── Indicator modal ──────────────────────────────────────────────────
  const showIndicatorModal = ref(false)
  const indicatorModalMode = ref("create")
  const selectedIndicator = ref(null)

  const blankIndicatorForm = () => ({
    id: null,
    fiscal_year: props.selectedFiscalYear !== "all" ? props.selectedFiscalYear : props.currentFiscalYear,
    dost_sub_strategy_id: "",
    agency_outcome_id: "",
    performance_indicator_id: "",
    description: "",
    target: "",
    budget: "",
    remarks: "",
    divisions: [],
  })

  const indicatorForm = ref(blankIndicatorForm())

  const openIndicatorModal = (mode, indicator = null) => {
    indicatorModalMode.value = mode
    showIndicatorModal.value = true
    selectedIndicator.value = indicator

    if ((mode === "edit" || mode === "view") && indicator) {
      indicatorForm.value = {
        id: indicator.id,
        fiscal_year: indicator.fiscal_year,
        dost_sub_strategy_id: indicator.dost_sub_strategy_id ?? "",
        agency_outcome_id: indicator.agency_outcome_id ?? "",
        performance_indicator_id: indicator.performance_indicator_id ?? "",
        description: indicator.description ?? "",
        target: indicator.target ?? "",
        budget: indicator.budget ?? "",
        remarks: indicator.remarks ?? "",
        divisions: indicator.divisions ? [...indicator.divisions] : [],
      }
    } else {
      indicatorForm.value = blankIndicatorForm()
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

    const onDone = (label) => ({
      onSuccess: async () => {
        closeIndicatorModal()
        await Swal.fire("Success", label, "success")
        router.reload({ only: ["indicators"] })
      },
      onError: async (errors) => {
        await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
      },
    })

    if (indicatorModalMode.value === "create") {
      router.post(route("opcr-indicators.store"), payload, onDone("Indicator created."))
    } else {
      router.put(route("opcr-indicators.update", indicatorForm.value.id), payload, onDone("Indicator updated."))
    }
  }

  const deleteIndicator = async (indicator) => {
    const result = await Swal.fire({
      title: `Delete indicator "${indicator?.description ?? ""}"?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    })

    if (result.isConfirmed) {
      router.delete(route("opcr-indicators.destroy", indicator.id), {
        onSuccess: async () => {
          await Swal.fire("Deleted", "Indicator deleted.", "success")
          router.reload({ only: ["indicators"] })
        },
      })
    }
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

  // ── Embedded DOST chain wizard (shared component) — creating a brand
  // new Pillar/Strategy/Sub-Strategy/Program chain from inside the
  // indicator modal, without navigating away. On success, auto-fills
  // this indicator's own tagging fields with the result.
  const showChainWizardPanel = ref(false)

  const toggleChainWizardPanel = () => {
    showChainWizardPanel.value = !showChainWizardPanel.value
  }

  const onChainWizardCreated = (result) => {
    showChainWizardPanel.value = false
    if (result.sub_strategy_id) indicatorForm.value.dost_sub_strategy_id = result.sub_strategy_id
    if (result.agency_outcome_id) indicatorForm.value.agency_outcome_id = result.agency_outcome_id
    router.reload({ only: ["pillars", "agencyOutcomes"] })
  }

  // ── Signatories settings modal (Campus Director/OIC/ED names + commitment
  // statement) — one settings row, not per-FY, used on every PDF export ────
  const showSettingsModal = ref(false)
  const settingsForm = ref({
    campus_director_name: "",
    oic_campus_director_name: "",
    executive_director_name: "",
    commitment_statement: "",
  })

  const openSettingsModal = () => {
    showSettingsModal.value = true
    settingsForm.value = {
      campus_director_name: props.settings?.campus_director_name ?? "",
      oic_campus_director_name: props.settings?.oic_campus_director_name ?? "",
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
    indicatorModalMode,
    selectedIndicator,
    indicatorForm,
    openIndicatorModal,
    closeIndicatorModal,
    submitIndicator,
    deleteIndicator,
    updateActual,
    updateRating,
    showChainWizardPanel,
    toggleChainWizardPanel,
    onChainWizardCreated,
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
