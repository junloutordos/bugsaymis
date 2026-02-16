import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useUsers(props) {
  const usersList = ref(props.users || [])
  const rolesList = ref(props.roles || [])
  const divisionsList = ref(props.divisions || []) // ✅ make it reactive
  const officesList = ref(props.offices || [])

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
    sex:"",
    badge_id: "",
    role_id: [],
    position: "",
    division_id: "",
    office_id: "",
    emp_category: '',
  })

  const isEmployeesPage = !!(props.pageTitle && String(props.pageTitle).toLowerCase().includes('employee'))

  // Filtered + paginated users
  const filteredUsers = computed(() => {
    let results = usersList.value.filter(
      (u) =>
        u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        u.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.ceil(usersList.value.length / perPage)
  )

  // Modal logic
  const openModal = (mode, user = null) => {
    modalMode.value = mode
    showModal.value = true
    if (mode === "edit" && user) {
      form.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        sex: user.sex,
        badge_id: user.badge_id ?? "",
        role_id: Array.isArray(user.role_id)
          ? user.role_id
          : user.role_id
          ? user.role_id.toString().split(',').map((s) => Number(s.trim()))
          : [],
        position: user.position ?? "",
        division_id: user.division_id ?? "",
        office_id: user.office_id ?? user.office?.id ?? "",
        emp_category: user.emp_category ?? user.emp_category ?? '' ,
      }
    } else {
      form.value = {
        id: null,
        name: "",
        email: "",
        badge_id: "",
        role_id: [],
        position: "",
        division_id: "",
        office_id: "",
        emp_category: '',
      }
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

  // Submit
  const submitUser = async () => {
    if (modalMode.value === "create") {
      const payload = { ...form.value }
      if (Array.isArray(payload.role_id)) payload.role_id = payload.role_id.join(',')

      // If this is the HR Employees page, send to the HR endpoint and strip admin-only fields
      if (isEmployeesPage) {
        // remove admin-only fields
        delete payload.email
        delete payload.badge_id
        delete payload.role_id
        router.post("/hr/employees", payload, {
          onSuccess: async () => {
            closeModal()
            await Swal.fire("Success", "The employee has been added successfully", "success")
            window.location.reload()
          },
          onError: async (errors) => {
            await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
          },
        })
        return
      }

      router.post("/users", payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Success", "The user has been added successfully", "success")
          window.location.reload()
        },
        onError: async (errors) => {
          await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
        },
      })
    } else if (modalMode.value === "edit") {
      const payload = { ...form.value }
      if (Array.isArray(payload.role_id)) payload.role_id = payload.role_id.join(',')

      router.put(`/users/${form.value.id}`, payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Updated", "The user has been updated successfully", "success")
          window.location.reload()
        },
        onError: async (errors) => {
          await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
        },
      })
    }
  }

  // Delete
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
          usersList.value = usersList.value.filter((u) => u.id !== user.id)
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
    divisionsList, // ✅ return it
    officesList,
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
    isEmployeesPage,
  }
}
