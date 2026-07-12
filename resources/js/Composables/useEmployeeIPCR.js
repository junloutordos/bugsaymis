import { ref, reactive, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"

export default function useEmployeeIPCR(initialIPCRs = [], workPlans = []) {
  const ipcrTargets = ref(initialIPCRs)
  const workPlansList = ref(workPlans)
  const errors = ref({})
  const showModal = ref(false)
  const showAddPlansModal = ref(false)
  const modalMode = ref("create")
  const selectedIPCR = ref(null)
  const selectedPlans = ref([])
  const planSearch = ref("")

  const form = reactive({
    rating_period_id: "",
    title: "",
    remarks: "",
  })

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = ref(10)
  const sortKey = ref("id")
  const sortAsc = ref(true)

  // --- Computed ---
  const sortedIPCRs = computed(() => {
    return [...ipcrTargets.value].sort((a, b) => {
      let valA = a[sortKey.value] ?? ""
      let valB = b[sortKey.value] ?? ""
      if (valA < valB) return sortAsc.value ? -1 : 1
      if (valA > valB) return sortAsc.value ? 1 : -1
      return 0
    })
  })

  const filteredIPCRs = computed(() => {
    const q = searchQuery.value.toLowerCase()
    return sortedIPCRs.value
      .filter(t => Object.values(t).some(v => String(v).toLowerCase().includes(q)))
      .slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value)
  })

  const totalPages = computed(() =>
    Math.ceil(
      sortedIPCRs.value.filter(t => Object.values(t).some(v => String(v).toLowerCase().includes(searchQuery.value.toLowerCase()))).length / perPage.value
    ) || 1
  )

  // --- Filtered Plans with Office Search ---
  const filteredPlans = computed(() => {
    const q = planSearch.value.toLowerCase()
    return workPlansList.value.filter(plan =>
      (plan.success_indicator && plan.success_indicator.toLowerCase().includes(q)) ||
      (plan.performance_indicator?.description && plan.performance_indicator.description.toLowerCase().includes(q)) ||
      (plan.office_involved && plan.office_involved.toLowerCase().includes(q))
    )
  })

  // --- Plan selection helpers ---
  const isPlanSelected = (planId) => selectedPlans.value.includes(planId)
  const togglePlanSelection = (plan) => {
    const idx = selectedPlans.value.indexOf(plan.id)
    if (idx >= 0) selectedPlans.value.splice(idx, 1)
    else selectedPlans.value.push(plan.id)
  }

  // --- CRUD & Modals ---
  const getIPCRs = () => {
    router.get(route("employee-ipcr.index"), {}, {
      preserveState: true,
      replace: true,
      onSuccess: page => ipcrTargets.value = page.props.ipcrs
    })
  }

  // AppSelect emits strings — cast the FK before submitting
  const formPayload = () => ({
    ...form,
    rating_period_id: form.rating_period_id ? Number(form.rating_period_id) : null,
  })

  const storeIPCR = () => {
    router.post(route("employee-ipcr.store"), formPayload(), {
      onError: e => errors.value = e,
      onSuccess: () => {
        closeModal()
        getIPCRs()
        Swal.fire({ icon: "success", title: "IPCR Target Added", timer: 2000, showConfirmButton: false })
      },
    })
  }

  const updateIPCR = (id) => {
    router.put(route("employee-ipcr.update", id), formPayload(), {
      onError: e => errors.value = e,
      onSuccess: () => {
        closeModal()
        getIPCRs()
        Swal.fire({ icon: "success", title: "IPCR Target Updated", timer: 2000, showConfirmButton: false })
      }
    })
  }

  const destroyIPCR = (id) => {
    Swal.fire({
      title: "Are you sure?",
      text: "This IPCR target will be permanently deleted!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#dc2626",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Yes, delete it!",
    }).then((result) => {
      if (result.isConfirmed) {
        router.delete(route("employee-ipcr.destroy", id), {
          onSuccess: () => {
            getIPCRs()
            Swal.fire({ icon: "success", title: "Deleted", timer: 2000, showConfirmButton: false })
          }
        })
      }
    })
  }

  const openModal = (mode, ipcr = null) => {
    modalMode.value = mode
    selectedIPCR.value = ipcr
    if (mode === "edit" && ipcr) {
      form.rating_period_id = ipcr.rating_period_id ?? ""
      form.title = ipcr.title
      form.remarks = ipcr.remarks
    } else if (mode === "create") {
      form.rating_period_id = ""
      form.title = ""
      form.remarks = ""
    }
    showModal.value = true
  }

  const closeModal = () => {
    showModal.value = false
    selectedIPCR.value = null
  }

  const submitIPCR = () => {
    if (modalMode.value === "create") storeIPCR()
    else if (modalMode.value === "edit" && selectedIPCR.value) updateIPCR(selectedIPCR.value.id)
  }

  const openAddPlansModal = (ipcr) => {
    selectedIPCR.value = ipcr
    selectedPlans.value = ipcr.plans?.map(p => p.id) || []
    planSearch.value = ""
    showAddPlansModal.value = true
  }

  const closeAddPlansModal = () => {
    showAddPlansModal.value = false
    selectedPlans.value = []
    selectedIPCR.value = null
    planSearch.value = ""
  }

  const submitPlans = () => {
    if (!selectedIPCR.value) return
    router.post(route("employee-ipcr.addPlans", selectedIPCR.value.id), { plan_ids: selectedPlans.value }, {
      onSuccess: () => {
        closeAddPlansModal()
        getIPCRs()
        Swal.fire({ icon: "success", title: "Plans Added", timer: 2000, showConfirmButton: false })
      },
      onError: (e) => Swal.fire({ icon: "error", title: "Failed to add plans", text: e.message })
    })
  }

  // --- Utilities ---
  const viewIPCR = (t) => {
  router.get(route("employee-ipcr.show", t.id))
  }

  const sortBy = (key) => { if(sortKey.value===key) sortAsc.value=!sortAsc.value; else sortKey.value=key,sortAsc.value=true }
  const statusClasses = ipcrStatusClass

  return {
    ipcrTargets,
    workPlans: workPlansList,
    errors,
    showModal,
    showAddPlansModal,
    modalMode,
    selectedIPCR,
    selectedPlans,
    form,
    searchQuery,
    currentPage,
    totalPages,
    filteredIPCRs,
    planSearch,
    filteredPlans,
    isPlanSelected,
    togglePlanSelection,
    getIPCRs,
    destroyIPCR,
    openModal,
    closeModal,
    submitIPCR,
    openAddPlansModal,
    closeAddPlansModal,
    submitPlans,
    viewIPCR,
    sortBy,
    statusClasses
  }
}
