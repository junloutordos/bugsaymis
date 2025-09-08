// resources/js/Composables/useEquipments.js
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"

export default function useEquipments(initialEquipments = []) {
  // State
  const equipments = ref(initialEquipments)
  const equipment = ref(null)
  const errors = ref({})

  // Modal state
  const showModal = ref(false)
  const modalMode = ref("create") // create | edit | view
  const selectedEquipment = ref(null)

  // Form state
  const form = ref({
    property_no: "",
    description: "",
    location: "",
    status: "",
    category: "",
    amount: "",
  })

  // Search, sort, pagination
  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = ref(10)
  const sortKey = ref("id")
  const sortAsc = ref(true)

  const sortedEquipments = computed(() => {
    return [...equipments.value].sort((a, b) => {
      let valA = a[sortKey.value] ?? ""
      let valB = b[sortKey.value] ?? ""
      if (valA < valB) return sortAsc.value ? -1 : 1
      if (valA > valB) return sortAsc.value ? 1 : -1
      return 0
    })
  })

  const filteredEquipments = computed(() => {
    const q = searchQuery.value.toLowerCase()
    return sortedEquipments.value.filter(eq =>
      Object.values(eq).some(v => String(v).toLowerCase().includes(q))
    ).slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value)
  })

  const totalPages = computed(() => {
    return Math.ceil(
      sortedEquipments.value.filter(eq =>
        Object.values(eq).some(v => String(v).toLowerCase().includes(searchQuery.value.toLowerCase()))
      ).length / perPage.value
    ) || 1
  })

  // CRUD
  const getEquipments = async () => {
    router.get(route("ict-equipments.index"), {}, {
      preserveState: true,
      replace: true,
      onSuccess: (page) => {
        equipments.value = page.props.equipments
      }
    })
  }

  const getEquipment = async (id) => {
    router.get(route("ict-equipments.show", id), {}, {
      preserveState: true,
      replace: true,
      onSuccess: (page) => {
        equipment.value = page.props.equipment
      }
    })
  }

  const storeEquipment = async () => {
    errors.value = {}
    router.post(route("ict-equipments.store"), form.value, {
      onError: (e) => errors.value = e,
      onSuccess: () => {
        closeModal()
        getEquipments()
      }
    })
  }

  const updateEquipment = async (id) => {
    errors.value = {}
    router.put(route("ict-equipments.update", id), form.value, {
      onError: (e) => errors.value = e,
      onSuccess: () => {
        closeModal()
        getEquipments()
      }
    })
  }

  const destroyEquipment = async (id) => {
    if (confirm("Are you sure you want to delete this equipment?")) {
      router.delete(route("ict-equipments.destroy", id), {
        onSuccess: () => getEquipments()
      })
    }
  }

  // Helpers
  const sortBy = (key) => {
    if (sortKey.value === key) {
      sortAsc.value = !sortAsc.value
    } else {
      sortKey.value = key
      sortAsc.value = true
    }
  }

  const openModal = (mode, eq = null) => {
    modalMode.value = mode
    selectedEquipment.value = eq
    if (mode === "edit" && eq) {
      form.value = { ...eq }
    } else if (mode === "create") {
      form.value = {
        property_no: "",
        description: "",
        location: "",
        status: "",
        category: "",
        amount: "",
      }
    }
    showModal.value = true
  }

  const closeModal = () => {
    showModal.value = false
    selectedEquipment.value = null
  }

  const submitEquipment = () => {
    if (modalMode.value === "create") {
      storeEquipment()
    } else if (modalMode.value === "edit" && selectedEquipment.value) {
      updateEquipment(selectedEquipment.value.id)
    }
  }

  const viewEquipment = (eq) => {
    openModal("view", eq)
  }

  const exportCSV = () => {
    const csv = equipments.value.map(eq =>
      `${eq.id},${eq.property_no},${eq.description},${eq.status},${eq.location},${eq.category},${eq.amount}`
    )
    const blob = new Blob([["ID,Property No,Description,Status,Location,Category,Amount\n", ...csv].join("\n")], { type: "text/csv" })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement("a")
    a.href = url
    a.download = "equipments.csv"
    a.click()
  }

  const printTable = () => {
    const w = window.open("", "_blank")
    let html = `<h3>ICT Equipment Inventory</h3><table border="1" cellspacing="0" cellpadding="5"><tr><th>ID</th><th>Property No</th><th>Description</th><th>Status</th><th>Location</th><th>Category</th><th>Amount</th></tr>`
    equipments.value.forEach(eq => {
      html += `<tr><td>${eq.id}</td><td>${eq.property_no}</td><td>${eq.description}</td><td>${eq.status}</td><td>${eq.location}</td><td>${eq.category}</td><td>${eq.amount}</td></tr>`
    })
    html += "</table>"
    w.document.write(html)
    w.print()
    w.close()
  }

  return {
    equipments,
    equipment,
    errors,
    showModal,
    modalMode,
    selectedEquipment,
    form,
    searchQuery,
    currentPage,
    totalPages,
    filteredEquipments,
    getEquipments,
    getEquipment,
    storeEquipment,
    updateEquipment,
    destroyEquipment,
    sortBy,
    openModal,
    closeModal,
    submitEquipment,
    viewEquipment,
    exportCSV,
    printTable,
  }
}
