import { ref, reactive, computed } from "vue"
import { router } from "@inertiajs/vue3"
import * as XLSX from "xlsx"
import Swal from "sweetalert2"

export function useIPCR(props) {
  /* -------------------------------
   * SEARCH STATE
   * ------------------------------- */
  const searchQuery = ref("")

  /* -------------------------------
   * MODAL CONTROL
   * ------------------------------- */
  const showModal = ref(false)
  const modalMode = ref("") // create, accomplishment, view
  const selectedPlan = ref(null)

  /* -------------------------------
   * FORM FOR ACCOMPLISHMENT
   * ------------------------------- */
  const form = reactive({
    period: "",
    year: new Date().getFullYear(),
    accomplishment: "",
    mov_link: "",
    self_quality: "",
    self_efficiency: "",
    self_timeliness: "",
  })

  /* -------------------------------
   * CREATE-BULK STATE
   * ------------------------------- */
  const createModalState = reactive({
    period: "",
    year: new Date().getFullYear(),
    search: "",
    selectedPlans: []
  })

  const selectedPlansList = computed(() => createModalState.selectedPlans)

  /* -------------------------------
   * FILTERED PLANS FOR MODAL (INCLUDES office_involved)
   * ------------------------------- */
  const filteredPlans = computed(() => {
    const q = createModalState.search.toLowerCase()
    const list = props.plans || []

    if (!q) return list

    return list.filter(plan =>
      (plan.success_indicator || "").toLowerCase().includes(q) ||
      (plan.performance_indicator?.description || "").toLowerCase().includes(q) ||
      (plan.office_involved || "").toLowerCase().includes(q) // NEW: search office_involved
    )
  })

  /* -------------------------------
   * GROUPED PLANS (DISPLAY TABLE)
   * Only show plans with approved IPCR
   * ------------------------------- */
  const groupedPlans = computed(() => {
    const list = (props.plans || []).filter(plan => 
      plan.ipcrs?.[0]?.target_status === "approved"
    )

    const groups = {}
    list.forEach(plan => {
      const outcome = plan.performance_indicator?.agency_outcome?.outcome?.trim() || "Uncategorized Outcome"
      if (!groups[outcome]) groups[outcome] = []
      groups[outcome].push(plan)
    })

    // sort each group alphabetically
    const sorted = {}
    Object.keys(groups)
      .sort((a, b) => a.localeCompare(b))
      .forEach(key => {
        sorted[key] = groups[key].sort((a, b) => {
          const A = a.performance_indicator?.description?.toLowerCase() || ""
          const B = b.performance_indicator?.description?.toLowerCase() || ""
          return A.localeCompare(B)
        })
      })

    return sorted
  })

  /* -------------------------------
   * MODAL OPEN / CLOSE
   * ------------------------------- */
  const openModal = (mode, plan = null) => {
    modalMode.value = mode
    selectedPlan.value = plan

    const ipcr = plan?.ipcrs?.[0] ?? {}

    form.period = ipcr.period ?? ""
    form.year = ipcr.year ?? new Date().getFullYear()
    form.accomplishment = ipcr.accomplishment ?? ""
    form.mov_link = ipcr.mov_link ?? ""
    form.self_quality = ipcr.self_quality ?? ""
    form.self_efficiency = ipcr.self_efficiency ?? ""
    form.self_timeliness = ipcr.self_timeliness ?? ""

    showModal.value = true
  }

  const openCreateModal = () => {
    modalMode.value = "create"
    showModal.value = true

    createModalState.period = ""
    createModalState.year = new Date().getFullYear()
    createModalState.search = ""
    createModalState.selectedPlans = []
  }

  const closeModal = () => showModal.value = false

  /* -------------------------------
   * PLAN SELECTION LOGIC
   * ------------------------------- */
  const togglePlanSelection = plan => {
    const idx = createModalState.selectedPlans.findIndex(p => p.id === plan.id)
    if (idx === -1) createModalState.selectedPlans.push({ ...plan })
    else createModalState.selectedPlans.splice(idx, 1)
  }

  const isPlanSelected = planId =>
    createModalState.selectedPlans.some(p => p.id === planId)

  /* -------------------------------
   * BULK CREATE TARGETS
   * ------------------------------- */
  const submitBulkCreateTargets = () => {
    if (!createModalState.period) {
      Swal.fire("Validation", "Choose a rating period.", "warning")
      return
    }
    if (!createModalState.selectedPlans.length) {
      Swal.fire("Validation", "Please select plans.", "warning")
      return
    }

    const items = createModalState.selectedPlans.map(p => ({
      plan_id: p.id,
      target: "" // blank by rule
    }))

    router.post(`/ipcrs/bulk-create`, {
      period: createModalState.period,
      year: createModalState.year,
      items,
    }, {
      onSuccess: () => {
        closeModal()
        Swal.fire("Success", "Blank targets were created for the selected plans.", "success")
          .then(() => window.location.reload())
      }
    })
  }

  /* -------------------------------
   * SUBMIT ACCOMPLISHMENT
   * ------------------------------- */
  const submitAccomplishment = () => {
    if (!selectedPlan.value) return
    const ipcr = selectedPlan.value.ipcrs?.[0] ?? null

    if (!ipcr?.id) {
      Swal.fire("Error", "No IPCR target exists yet.", "error")
      return
    }

    if (!form.accomplishment.trim()) {
      Swal.fire("Validation", "Enter an accomplishment.", "warning")
      return
    }

    const q = Number(form.self_quality)
    const e = Number(form.self_efficiency)
    const t = Number(form.self_timeliness)

    if (![q, e, t].every(v => v >= 1 && v <= 5 && !Number.isNaN(v))) {
      Swal.fire("Validation", "Ratings must be 1–5.", "warning")
      return
    }

    router.put(`/ipcrs/${ipcr.id}/accomplishment`, {
      accomplishment: form.accomplishment,
      mov_link: form.mov_link,
      self_quality: q,
      self_efficiency: e,
      self_timeliness: t,
    }, {
      onSuccess: () => {
        closeModal()
        Swal.fire("Success", "Accomplishment submitted.", "success")
          .then(() => window.location.reload())
      }
    })
  }

  /* -------------------------------
   * APPROVE / REMOVE IPCR
   * ------------------------------- */
  const approveIPCR = (ipcr) => {
    if (!ipcr?.id) return
    Swal.fire({
      title: "Approve this IPCR?",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Approve",
    }).then(r => {
      if (!r.isConfirmed) return
      router.put(`/ipcrs/${ipcr.id}/approve`, {}, {
        onSuccess: () => {
          Swal.fire("Approved", "IPCR approved.", "success")
            .then(() => window.location.reload())
        }
      })
    })
  }

  const removeIPCR = (ipcr) => {
    if (!ipcr?.id) return
    Swal.fire({
      title: "Remove this IPCR?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Remove",
    }).then(r => {
      if (!r.isConfirmed) return
      router.delete(`/ipcrs/${ipcr.id}`, {}, {
        onSuccess: () => {
          Swal.fire("Removed", "IPCR removed.", "success")
            .then(() => window.location.reload())
        }
      })
    })
  }

  /* -------------------------------
   * EXPORT FUNCTIONS (unchanged)
   * ------------------------------- */
  const exportAllHTML = () => Swal.fire("Info", "Implement exportAllHTML logic.", "info")
  const exportPlanHTML = (plan, meta) => { /* ... */ }
  const exportAllExcel = () => { /* ... */ }
  const exportPlanExcel = (plan) => { /* ... */ }

  return {
    searchQuery,
    filteredPlans,
    groupedPlans,

    showModal,
    modalMode,
    selectedPlan,

    form,
    createModalState,
    selectedPlansList,

    openModal,
    openCreateModal,
    closeModal,

    togglePlanSelection,
    isPlanSelected,

    submitBulkCreateTargets,
    submitAccomplishment,
    approveIPCR,
    removeIPCR,

    exportAllHTML,
    exportAllExcel,
    exportPlanHTML,
    exportPlanExcel,
  }
}
