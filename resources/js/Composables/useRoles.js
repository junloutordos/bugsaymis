import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useRoles(props) {
  const rolesList = ref(props.roles || [])

  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedRole = ref(null)

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  // Form
  const form = ref({
    id: null,
    name: "",
  })

  // Computed: filtered and paginated roles
  const filteredRoles = computed(() => {
    const results = rolesList.value.filter(r =>
      r?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.ceil(rolesList.value.length / perPage)
  )

  // Open modal
  const openModal = (mode, role = null) => {
    modalMode.value = mode
    showModal.value = true
    if (mode === "edit" && role) {
      form.value = { id: role.id, name: role.name }
    } else {
      form.value = { id: null, name: "" }
    }
    selectedRole.value = role
  }

  const closeModal = () => {
    showModal.value = false
    selectedRole.value = null
  }

  // Submit (create / edit)
  const submitRole = async () => {
    const url = "/users-roles"
    try {
      if (modalMode.value === "create") {
        router.post(url, form.value, {
          onSuccess: async (page) => {
            closeModal()
            // fallback if page.props.role is undefined
            rolesList.value.unshift(page.props.role ?? { ...form.value, id: Date.now(), created_at: new Date() })
            await Swal.fire("Success", "Role has been added successfully", "success")
            window.location.reload()
          },
          onError: async (errors) => {
            await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
          },
        })
      } else if (modalMode.value === "edit") {
        router.put(`${url}/${form.value.id}`, form.value, {
          onSuccess: async (page) => {
            closeModal()
            const index = rolesList.value.findIndex(r => r.id === form.value.id)
            if (index !== -1) rolesList.value[index] = page.props.role ?? { ...form.value }
            await Swal.fire("Updated", "Role has been updated successfully", "success")
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

  // Delete role
  const deleteRole = async (role) => {
    const result = await Swal.fire({
      title: `Delete role ${role?.name ?? ""}?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
      cancelButtonText: "Cancel",
    })

    if (result.isConfirmed) {
      router.delete(`/users-roles/${role.id}`, {
        onSuccess: async () => {
          rolesList.value = rolesList.value.filter(r => r.id !== role.id)
          await Swal.fire("Deleted", "Role has been deleted", "success")
          window.location.reload()
        },
        onError: async (errors) => {
          await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
        },
      })
    }
  }

  return {
    rolesList,
    showModal,
    modalMode,
    selectedRole,
    searchQuery,
    currentPage,
    totalPages,
    filteredRoles,
    form,
    openModal,
    closeModal,
    submitRole,
    deleteRole,
  }
}
