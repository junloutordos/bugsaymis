import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useUsers(props) {
  /* =======================
   * STATE
   * ======================= */
  const usersList = ref(props.users || [])
  const rolesList = ref(props.roles || [])
  const divisionsList = ref(props.divisions || [])

  const showModal = ref(false)
  const modalMode = ref("create") // create | edit | view
  const selectedUser = ref(null)

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  /* =======================
   * FORM
   * ======================= */
  const emptyForm = () => ({
    id: null,
    name: "",
    email: "",
    sex: "", // ✅ ADDED
    role_id: "",
    position: "",
    division_id: "",
    office: "",
  })

  const form = ref(emptyForm())

  /* =======================
   * COMPUTED
   * ======================= */
  const filteredUsers = computed(() => {
    const q = searchQuery.value.toLowerCase()

    const results = usersList.value.filter((u) => {
      return (
        u.name?.toLowerCase().includes(q) ||
        u.email?.toLowerCase().includes(q)
      )
    })

    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.ceil(usersList.value.length / perPage)
  )

  /* =======================
   * MODAL ACTIONS
   * ======================= */
  const openModal = (mode, user = null) => {
    modalMode.value = mode
    showModal.value = true

    if ((mode === "edit" || mode === "view") && user) {
      selectedUser.value = user
      form.value = {
        id: user.id,
        name: user.name ?? "",
        email: user.email ?? "",
        sex: user.sex ?? "", // ✅
        role_id: user.role_id ?? "",
        position: user.position ?? "",
        division_id: user.division_id ?? "",
        office: user.office ?? "",
      }
    } else {
      selectedUser.value = null
      form.value = emptyForm()
    }
  }

  const closeModal = () => {
    showModal.value = false
    selectedUser.value = null
    form.value = emptyForm()
  }

  const viewUser = (user) => {
    modalMode.value = "view"
    selectedUser.value = user
    showModal.value = true
  }

  /* =======================
   * SUBMIT
   * ======================= */
  const submitUser = async () => {
  if (modalMode.value === "create") {
    router.post("/users", form.value, {
      onSuccess: async () => {
        closeModal()
        await Swal.fire(
          "Success",
          "User added successfully",
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

  if (modalMode.value === "edit") {
    router.put(`/users/${form.value.id}`, form.value, {
      onSuccess: async () => {
        closeModal()
        await Swal.fire(
          "Updated",
          "User updated successfully",
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
}


  /* =======================
   * DELETE
   * ======================= */
  const deleteUser = async (user) => {
    const result = await Swal.fire({
      title: `Delete ${user.name}?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    })

    if (!result.isConfirmed) return

    router.delete(`/users/${user.id}`, {
      onSuccess: async () => {
        usersList.value = usersList.value.filter(
          (u) => u.id !== user.id
        )
        await Swal.fire("Deleted", "User deleted", "success")
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

  /* =======================
   * EXPORTS
   * ======================= */
  return {
    usersList,
    rolesList,
    divisionsList,
    showModal,
    modalMode,
    selectedUser,
    searchQuery,
    currentPage,
    totalPages,
    filteredUsers,
    form,
    openModal,
    closeModal,
    submitUser,
    viewUser,
    deleteUser,
  }
}
