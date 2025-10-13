import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function usePerformanceIndicators(props) {
  const indicatorsList = ref(props.indicators || [])
  const agencyOutcomes = ref(props.agencyOutcomes || []) // ✅ outcomes list
  const divisions = ref(props.divisions || []) // ✅ divisions list

  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedIndicator = ref(null)

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  // ✅ Form holds full division objects
  const form = ref({
    id: null,
    description: "",
    target: "",
    agency_outcome_id: "",
    divisions: [], // full objects for UI
    budget: "",
  })

  // ✅ Filter + paginate
  const filteredIndicators = computed(() => {
    const results = indicatorsList.value.filter((i) =>
      i?.description?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.ceil(indicatorsList.value.length / perPage)
  )

  // ✅ Open modal & populate form
  const openModal = (mode, indicator = null) => {
    modalMode.value = mode
    showModal.value = true

    if ((mode === "edit" || mode === "view") && indicator) {
      form.value = {
        id: indicator.id,
        description: indicator.description ?? "",
        target: indicator.target ?? "",
        agency_outcome_id: indicator.agency_outcome_id ?? "",
        divisions: indicator.divisions ? [...indicator.divisions] : [], // keep objects
        budget: indicator.budget ?? "",
      }
    } else {
      form.value = {
        id: null,
        description: "",
        target: "",
        agency_outcome_id: "",
        divisions: [],
        budget: "",
      }
    }

    selectedIndicator.value = indicator
  }

  const closeModal = () => {
    showModal.value = false
    selectedIndicator.value = null
  }

  // ✅ Submit (send IDs only)
  const submitIndicator = async () => {
    const url = "/performance-indicators"
    const payload = {
      ...form.value,
      division_ids: form.value.divisions.map(d => d.id), // only IDs go to backend
    }

    try {
      if (modalMode.value === "create") {
        router.post(url, payload, {
          onSuccess: async () => {
            closeModal()
            await Swal.fire("Success", "Indicator has been added successfully", "success")
            window.location.reload()
          },
          onError: async (errors) => {
            await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
          },
        })
      } else if (modalMode.value === "edit") {
        router.put(`${url}/${form.value.id}`, payload, {
          onSuccess: async () => {
            closeModal()
            await Swal.fire("Updated", "Indicator has been updated successfully", "success")
            window.location.reload()
          },
          onError: async (errors) => {
            await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
          },
        })
      }
    } catch (err) {
      console.error(err)
      await Swal.fire("Error", "Something went wrong", "error")
    }
  }

  // ✅ Delete
  const deleteIndicator = async (indicator) => {
    const result = await Swal.fire({
      title: `Delete indicator "${indicator?.description ?? ""}"?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
      cancelButtonText: "Cancel",
    })

    if (result.isConfirmed) {
      router.delete(`/performance-indicators/${indicator.id}`, {
        onSuccess: async () => {
          indicatorsList.value = indicatorsList.value.filter((i) => i.id !== indicator.id)
          await Swal.fire("Deleted", "Indicator has been deleted", "success")
          window.location.reload()
        },
        onError: async (errors) => {
          await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
        },
      })
    }
  }

  return {
    indicatorsList,
    agencyOutcomes,
    divisions,
    form,
    showModal,
    modalMode,
    selectedIndicator,
    searchQuery,
    currentPage,
    totalPages,
    filteredIndicators,
    openModal,
    closeModal,
    submitIndicator,
    deleteIndicator,
  }
}
