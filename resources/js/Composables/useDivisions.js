import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useDivisions(props) {
  const divisionsList = ref(props.divisions || [])

  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedDivision = ref(null)

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  // Form
  const form = ref({
    id: null,
    division_name: "",
    division_chief_id: null,
    year: "",
    status: "active",
  })

  // Computed: filtered and paginated divisions
  const filteredDivisions = computed(() => {
    const results = divisionsList.value.filter((d) =>
      d?.division_name?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.ceil(divisionsList.value.length / perPage)
  )

  // Open modal
  const openModal = (mode, division = null) => {
    modalMode.value = mode
    showModal.value = true

    if ((mode === "edit" || mode === "view") && division) {
      form.value = {
        id: division.id,
        division_name: division.division_name,
        division_chief_id: division.division_chief_id ?? null,
        year: division.year ?? "",
        status: division.status ?? "active",
      }
    } else {
      // Reset for create
      form.value = {
        id: null,
        division_name: "",
        division_chief_id: null,
        year: "",
        status: "active",
      }
    }

    selectedDivision.value = division
  }

  const closeModal = () => {
    showModal.value = false
    selectedDivision.value = null
  }

  // Submit (create / edit)
  const submitDivision = async () => {
    const url = "/users-divisions"
    try {
      if (modalMode.value === "create") {
        router.post(url, form.value, {
          onSuccess: async (page) => {
            closeModal()
            divisionsList.value.unshift(
              page.props.division ?? {
                ...form.value,
                id: Date.now(),
                created_at: new Date(),
              }
            )
            await Swal.fire(
              "Success",
              "Division has been added successfully",
              "success"
            )
            window.location.reload()
          },
          onError: async (errors) => {
            await Swal.fire(
              "Error",
              Object.values(errors).flat().join(", "),
              "error"
            )
          },
        })
      } else if (modalMode.value === "edit") {
        router.put(`${url}/${form.value.id}`, form.value, {
          onSuccess: async (page) => {
            closeModal()
            const index = divisionsList.value.findIndex(
              (d) => d.id === form.value.id
            )
            if (index !== -1)
              divisionsList.value[index] =
                page.props.division ?? { ...form.value }
            await Swal.fire(
              "Updated",
              "Division has been updated successfully",
              "success"
            )
            window.location.reload()
          },
          onError: async (errors) => {
            await Swal.fire(
              "Error",
              Object.values(errors).flat().join(", "),
              "error"
            )
          },
        })
      }
    } catch (err) {
      console.error(err)
      await Swal.fire("Error", "Something went wrong", "error")
    }
  }

  // Delete division
  const deleteDivision = async (division) => {
    const result = await Swal.fire({
      title: `Delete division ${division?.division_name ?? ""}?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
      cancelButtonText: "Cancel",
    })

    if (result.isConfirmed) {
      router.delete(`/users-divisions/${division.id}`, {
        onSuccess: async () => {
          divisionsList.value = divisionsList.value.filter(
            (d) => d.id !== division.id
          )
          await Swal.fire("Deleted", "Division has been deleted", "success")
          window.location.reload()
        },
        onError: async (errors) => {
          await Swal.fire(
            "Error",
            Object.values(errors).flat().join(", "),
            "error"
          )
        },
      })
    }
  }

  return {
    divisionsList,
    showModal,
    modalMode,
    selectedDivision,
    searchQuery,
    currentPage,
    totalPages,
    filteredDivisions,
    form,
    openModal,
    closeModal,
    submitDivision,
    deleteDivision,
  }
}
