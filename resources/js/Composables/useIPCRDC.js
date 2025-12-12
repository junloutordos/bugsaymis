import { ref, reactive, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export default function useDivisionChiefIPCR(initialIPCRs = [], workPlans = []) {
  const ipcrTargets = ref(initialIPCRs)
  const workPlansList = ref(workPlans)
  const errors = ref({})
  const showModal = ref(false)
  const showAddPlansModal = ref(false)
  const modalMode = ref("create")
  const selectedIPCR = ref(null)
  const selectedPlans = ref([])
  const searchQuery = ref("")
  const planSearch = ref("")
  const currentPage = ref(1)
  const perPage = ref(10)
  const sortKey = ref("user")
  const sortAsc = ref(true)

  const form = reactive({
    rating_period: "",
    title: "",
    status: "New Target",
    remarks: "",
  })

  // --- Computed: Sorting ---
  const sortedIPCRs = computed(() => {
    return [...ipcrTargets.value].sort((a, b) => {
      let valA = sortKey.value === 'user' ? `${a.user.first_name} ${a.user.last_name}`.toLowerCase() : a[sortKey.value]
      let valB = sortKey.value === 'user' ? `${b.user.first_name} ${b.user.last_name}`.toLowerCase() : b[sortKey.value]
      if (valA < valB) return sortAsc.value ? -1 : 1
      if (valA > valB) return sortAsc.value ? 1 : -1
      return 0
    })
  })

  const filteredIPCRs = computed(() => {
    const q = searchQuery.value.toLowerCase()
    return sortedIPCRs.value
      .filter(t => {
        const name = `${t.user.first_name} ${t.user.last_name}`.toLowerCase()
        return name.includes(q) || t.rating_period.toLowerCase().includes(q) || t.title.toLowerCase().includes(q) || t.status.toLowerCase().includes(q)
      })
      .slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value)
  })

  const totalPages = computed(() => Math.ceil(
    sortedIPCRs.value.filter(t => {
      const name = `${t.user.first_name} ${t.user.last_name}`.toLowerCase()
      const q = searchQuery.value.toLowerCase()
      return name.includes(q) || t.rating_period.toLowerCase().includes(q) || t.title.toLowerCase().includes(q) || t.status.toLowerCase().includes(q)
    }).length / perPage.value
  ) || 1)

  // --- Plans filtering ---
  const filteredPlans = computed(() => {
    const q = planSearch.value.toLowerCase()
    return workPlansList.value.filter(plan =>
      (plan.success_indicator && plan.success_indicator.toLowerCase().includes(q)) ||
      (plan.performance_indicator?.description && plan.performance_indicator.description.toLowerCase().includes(q)) ||
      (plan.office_involved && plan.office_involved.toLowerCase().includes(q))
    )
  })

  // --- Plan selection ---
  const isPlanSelected = id => selectedPlans.value.includes(id)
  const togglePlanSelection = plan => {
    const idx = selectedPlans.value.indexOf(plan.id)
    if (idx >= 0) selectedPlans.value.splice(idx, 1)
    else selectedPlans.value.push(plan.id)
  }

  // --- CRUD Actions ---
  const getIPCRs = () => router.get(route("division-chief-ipcr.index"), {}, {
    preserveState: true,
    onSuccess: page => ipcrTargets.value = page.props.ipcrs
  })

  const storeIPCR = () => {
    router.post(route("employee-ipcr.store"), form, {
      onError: e => errors.value = e,
      onSuccess: () => { closeModal(); getIPCRs(); Swal.fire({ icon: "success", title: "IPCR Added", timer: 2000, showConfirmButton: false }) }
    })
  }

  const updateIPCR = (id) => {
    router.put(route("employee-ipcr.update", id), form, {
      onError: e => errors.value = e,
      onSuccess: () => { closeModal(); getIPCRs(); Swal.fire({ icon: "success", title: "IPCR Updated", timer: 2000, showConfirmButton: false }) }
    })
  }

  const destroyIPCR = id => {
    Swal.fire({
      title: "Are you sure?",
      text: "This IPCR target will be deleted!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Yes, delete it!"
    }).then(result => {
      if (result.isConfirmed) {
        router.delete(route("employee-ipcr.destroy", id), { onSuccess: getIPCRs })
      }
    })
  }

  // --- Modals ---
  const openModal = (mode, ipcr = null) => {
    modalMode.value = mode
    selectedIPCR.value = ipcr
    if(mode === 'edit' && ipcr){
      Object.assign(form, {
        rating_period: ipcr.rating_period,
        title: ipcr.title,
        status: ipcr.status,
        remarks: ipcr.remarks
      })
    } else {
      Object.assign(form, { rating_period: "", title: "", status: "New Target", remarks: "" })
    }
    showModal.value = true
  }
  const closeModal = () => { showModal.value = false; selectedIPCR.value = null }
  const submitIPCR = () => modalMode.value === "create" ? storeIPCR() : updateIPCR(selectedIPCR.value.id)

  const openAddPlansModal = ipcr => { selectedIPCR.value = ipcr; selectedPlans.value = ipcr.plans?.map(p => p.id) || []; planSearch.value = ""; showAddPlansModal.value = true }
  const closeAddPlansModal = () => { showAddPlansModal.value = false; selectedPlans.value = []; selectedIPCR.value = null; planSearch.value = "" }
  const submitPlans = () => {
    if(!selectedIPCR.value) return
    router.post(route("employee-ipcr.addPlans", selectedIPCR.value.id), { plan_ids: selectedPlans.value }, {
      onSuccess: () => { closeAddPlansModal(); getIPCRs(); Swal.fire({ icon: "success", title: "Plans Added", timer: 2000, showConfirmButton: false }) },
      onError: e => Swal.fire({ icon: "error", title: "Failed to add plans", text: e.message })
    })
  }

  const viewIPCR = t => router.get(route("division-employee-ipcr.show", t.id))
  const sortBy = key => { sortKey.value === key ? sortAsc.value = !sortAsc.value : (sortKey.value = key, sortAsc.value = true) }

  const statusClasses = status => {
    switch(status){
      case 'New Target': return 'bg-blue-100 text-blue-700'
      case 'For Review': return 'bg-yellow-100 text-yellow-700'
      case 'Targets Approved': return 'bg-green-100 text-green-700'
      case 'Submitted for Rating': return 'bg-orange-100 text-orange-700'
      case 'Rated & For PMT Review': return 'bg-violet-100 text-violet-700'
      case 'Rejected': return 'bg-red-100 text-red-700'
      default: return 'bg-gray-100 text-gray-700'
    }
  }

  return {
    ipcrTargets,
    workPlans: workPlansList,
    searchQuery,
    planSearch,
    currentPage,
    totalPages,
    filteredIPCRs,
    filteredPlans,
    showModal,
    showAddPlansModal,
    modalMode,
    selectedIPCR,
    selectedPlans,
    form,
    isPlanSelected,
    togglePlanSelection,
    openModal,
    closeModal,
    submitIPCR,
    openAddPlansModal,
    closeAddPlansModal,
    submitPlans,
    viewIPCR,
    destroyIPCR,
    sortBy,
    statusClasses
  }
}
