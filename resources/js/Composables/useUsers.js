import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useUsers(props) {
  const usersList = ref(props.users || [])
  const rolesList = ref(props.roles || [])

  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedUser = ref(null)

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  // Form
  const form = ref({
    id: null,
    name: "",
    email: "",
    role_id: "",
  })

  // Computed: filtered and paginated users
  const filteredUsers = computed(() => {
    let results = usersList.value.filter(u =>
      u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      u.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.ceil(usersList.value.length / perPage)
  )

  // Open modal
  const openModal = (mode, user = null) => {
    modalMode.value = mode
    showModal.value = true
    if (mode === "edit" && user) {
      form.value = { id: user.id, name: user.name, email: user.email, role_id: user.role_id }
    } else {
      form.value = { id: null, name: "", email: "", role_id: "" }
    }
    selectedUser.value = user
  }

  const closeModal = () => {
    showModal.value = false
    selectedUser.value = null
  }

  const viewUser = (user) => {
    modalMode.value = "view"
    selectedUser.value = user
    showModal.value = true
  }

  // Submit (create / edit)
  const submitUser = async () => {
  if (modalMode.value === "create") {
    router.post("/users", form.value, {
      onSuccess: async (page) => {
        // Wait for the user to click OK
        closeModal()
        await Swal.fire("Success", "The user has been added successfully", "success")
        window.location.reload()
        usersList.value.unshift(page.props.user)
      },
      onError: async (errors) => {
        await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
      },
    })
  } else if (modalMode.value === "edit") {
    router.put(`/users/${form.value.id}`, form.value, {
      onSuccess: async (page) => {
        closeModal()
        await Swal.fire("Updated", "The user has been updated successfully", "success")
        window.location.reload()
        const index = usersList.value.findIndex(u => u.id === form.value.id)
        if (index !== -1) usersList.value[index] = page.props.user
      },
      onError: async (errors) => {
        await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
      },
    })
  }
}


  // Delete user
  const deleteUser = async (user) => {
    const result = await Swal.fire({
      title: `Delete ${user.name}?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
      cancelButtonText: "Cancel",
    })

    if (result.isConfirmed) {
      router.delete(`/users/${user.id}`, {
        onSuccess: async () => {
          usersList.value = usersList.value.filter(u => u.id !== user.id)
          await Swal.fire("Deleted", "User has been deleted", "success")
          closeModal()
        },
        onError: async (errors) => {
          await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
        },
      })
    }
  }

  return {
    usersList,
    rolesList,
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
