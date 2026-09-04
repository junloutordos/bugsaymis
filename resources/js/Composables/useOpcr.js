import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"
import { groupIndicatorsByHierarchy } from "@/Utils/OPCR/opcrGrouping.js"

export function useOpcr(props) {
  const groupedIndicators = computed(() => groupIndicatorsByHierarchy(props.indicators || []))

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

  // ── Inline DOST tag creation (no schema of its own — reuses the
  // existing DostPillar/DostStrategy/DostSubStrategy/AgencyOutcome
  // store routes so the indicator form never has to leave the page) ───
  const newPillarName = ref("")
  const addPillar = () => {
    if (!newPillarName.value.trim()) return
    router.post(route("dost-pillars.store"), { name: newPillarName.value }, {
      preserveScroll: true,
      onSuccess: () => {
        newPillarName.value = ""
        router.reload({ only: ["pillars"] })
      },
    })
  }

  const newStrategy = ref({ dost_pillar_id: "", name: "" })
  const addStrategy = () => {
    if (!newStrategy.value.dost_pillar_id || !newStrategy.value.name.trim()) return
    router.post(route("dost-strategies.store"), newStrategy.value, {
      preserveScroll: true,
      onSuccess: () => {
        newStrategy.value = { dost_pillar_id: "", name: "" }
        router.reload({ only: ["pillars"] })
      },
    })
  }

  const newSubStrategy = ref({ dost_strategy_id: "", description: "" })
  const addSubStrategy = () => {
    if (!newSubStrategy.value.dost_strategy_id || !newSubStrategy.value.description.trim()) return
    router.post(route("dost-sub-strategies.store"), newSubStrategy.value, {
      preserveScroll: true,
      onSuccess: () => {
        newSubStrategy.value = { dost_strategy_id: "", description: "" }
        router.reload({ only: ["pillars"] })
      },
    })
  }

  const newProgram = ref({ outcome: "", function_type: "" })
  const addProgram = () => {
    if (!newProgram.value.outcome.trim() || !newProgram.value.function_type.trim()) return
    router.post(route("outcome.store"), newProgram.value, {
      preserveScroll: true,
      onSuccess: () => {
        newProgram.value = { outcome: "", function_type: "" }
        router.reload({ only: ["agencyOutcomes"] })
      },
    })
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
    newPillarName,
    addPillar,
    newStrategy,
    addStrategy,
    newSubStrategy,
    addSubStrategy,
    newProgram,
    addProgram,
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
