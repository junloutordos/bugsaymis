import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useWorkDistributionPlans(props) {
  const plansList = ref(props.plans || [])
  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedPlan = ref(null)

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  const form = ref({
    id: null,
    performance_indicator_id: "",
    success_indicator: "",
    office_involved: "",
    rated_by: "",
  })

  const filteredPlans = computed(() => {
    const results = plansList.value.filter((p) =>
      p?.success_indicator?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.ceil(plansList.value.length / perPage)
  )

  const openModal = (mode, plan = null) => {
    modalMode.value = mode
    showModal.value = true

    if ((mode === "edit" || mode === "view") && plan) {
      selectedPlan.value = plan
      form.value = {
        id: plan.id,
        performance_indicator_id: plan.performance_indicator_id ?? "",
        success_indicator: plan.success_indicator ?? "",
        office_involved: plan.office_involved ?? "",
        rated_by: plan.rated_by ?? "",
      }
    } else {
      form.value = {
        id: null,
        performance_indicator_id: "",
        success_indicator: "",
        office_involved: "",
        rated_by: "",
      }
      selectedPlan.value = null
    }
  }

  const closeModal = () => {
    showModal.value = false
    selectedPlan.value = null
  }

  const submitPlan = async () => {
    const url = "/work-distributions"
    const payload = { ...form.value }

    try {
      if (modalMode.value === "create") {
        router.post(url, payload, {
          onSuccess: async () => {
            closeModal()
            await Swal.fire("Success", "WDP created successfully", "success")
            window.location.reload()
          },
        })
      } else {
        router.put(`${url}/${form.value.id}`, payload, {
          onSuccess: async () => {
            closeModal()
            await Swal.fire("Updated", "WDP updated successfully", "success")
            window.location.reload()
          },
        })
      }
    } catch (err) {
      console.error(err)
      Swal.fire("Error", "Something went wrong", "error")
    }
  }

  const deletePlan = async (plan) => {
    const confirm = await Swal.fire({
      title: `Delete WDP?`,
      text: "This action cannot be undone.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Delete",
    })

    if (confirm.isConfirmed) {
      router.delete(`/work-distribution-plans/${plan.id}`, {
        onSuccess: async () => {
          plansList.value = plansList.value.filter((p) => p.id !== plan.id)
          await Swal.fire("Deleted", "WDP deleted", "success")
          window.location.reload()
        },
      })
    }
  }

  return {
    plansList,
    form,
    showModal,
    modalMode,
    selectedPlan,
    searchQuery,
    currentPage,
    totalPages,
    filteredPlans,
    openModal,
    closeModal,
    submitPlan,
    deletePlan,
  }
}
