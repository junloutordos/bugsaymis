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
    personnel: [],
    start_date: "",
    end_date: "",
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
      form.value = {
        id: plan.id,
        performance_indicator_id: plan.performance_indicator_id ?? "",
        success_indicator: plan.success_indicator ?? "",
        personnel: plan.personnel ? [...plan.personnel] : [],
        start_date: plan.start_date ?? "",
        end_date: plan.end_date ?? "",
      }
    } else {
      form.value = {
        id: null,
        performance_indicator_id: "",
        success_indicator: "",
        personnel: [],
        start_date: "",
        end_date: "",
      }
    }

    selectedPlan.value = plan
  }

  const closeModal = () => {
    showModal.value = false
    selectedPlan.value = null
  }

  const submitPlan = async () => {
    const url = "/work-distributions"
    const payload = {
      ...form.value,
      personnel_ids: form.value.personnel.map(u => u.id),
    }

    try {
      if (modalMode.value === "create") {
        router.post(url, payload, {
          onSuccess: async () => {
            closeModal()
            await Swal.fire("Success", "WDP has been created", "success")
            window.location.reload()
          },
        })
      } else if (modalMode.value === "edit") {
        router.put(`${url}/${form.value.id}`, payload, {
          onSuccess: async () => {
            closeModal()
            await Swal.fire("Updated", "WDP has been updated", "success")
            window.location.reload()
          },
        })
      }
    } catch (err) {
      console.error(err)
      await Swal.fire("Error", "Something went wrong", "error")
    }
  }

  const deletePlan = async (plan) => {
    const result = await Swal.fire({
      title: `Delete WDP for "${plan.success_indicator}"?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    })

    if (result.isConfirmed) {
      router.delete(`/work-distribution-plans/${plan.id}`, {
        onSuccess: async () => {
          plansList.value = plansList.value.filter((p) => p.id !== plan.id)
          await Swal.fire("Deleted", "WDP has been deleted", "success")
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
