import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export default function useEquipments(initialEquipments = [], users = []) {

  // ---------------------------------------------------------------------------
  // STATE
  // ---------------------------------------------------------------------------
  const equipments = ref(initialEquipments)
  const errors = ref({})

  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedEquipment = ref(null)

  // PMS HISTORY MODAL
  const showPmsModal = ref(false)
  const selectedPmsHistory = ref([])

  const emptyForm = () => ({
    category: "",
    owner_id: "",
    property_no: "",
    serial_no: "",
    description: "",
    date_acquired: "",
    amount: "",
    status: "",
    location: "",
    remarks: "",
  })

  const form = ref(emptyForm())

  // ---------------------------------------------------------------------------
  // ENRICH EQUIPMENTS (ADD owner_name)
  // ---------------------------------------------------------------------------
  const enrichedEquipments = computed(() => {
    return equipments.value.map(eq => {
      const owner = users.find(u => u.id === eq.owner_id)
      return {
        ...eq,
        owner_name: owner ? owner.name : ""
      }
    })
  })

  // ---------------------------------------------------------------------------
  // SEARCH, SORT, PAGINATION
  // ---------------------------------------------------------------------------
  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = ref(10)

  const sortKey = ref("id")
  const sortAsc = ref(true)

  const sortedEquipments = computed(() => {
    return [...enrichedEquipments.value].sort((a, b) => {
      let x = String(a[sortKey.value] ?? "").toLowerCase()
      let y = String(b[sortKey.value] ?? "").toLowerCase()
      if (x < y) return sortAsc.value ? -1 : 1
      if (x > y) return sortAsc.value ? 1 : -1
      return 0
    })
  })

  const filteredList = computed(() => {
    const q = searchQuery.value.toLowerCase()
    if (!q) return sortedEquipments.value

    return sortedEquipments.value.filter(item =>
      Object.values(item).some(v => String(v).toLowerCase().includes(q))
    )
  })

  const filteredEquipments = computed(() => {
    const start = (currentPage.value - 1) * perPage.value
    return filteredList.value.slice(start, start + perPage.value)
  })

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredList.value.length / perPage.value))
  )

  // ---------------------------------------------------------------------------
  // CRUD
  // ---------------------------------------------------------------------------
  const getEquipments = async () => {
    router.get(route("ict-equipments.index"), {}, {
      preserveState: true,
      replace: true,
      onSuccess: page => {
        equipments.value = page.props.equipments
      },
    })
  }

  const getEquipment = async (id) => {
    router.get(route("ict-equipments.show", id), {}, {
      preserveState: true,
      replace: true,
      onSuccess: page => {
        selectedEquipment.value = page.props.equipment
      },
    })
  }

  const storeEquipment = async () => {
    errors.value = {}
    router.post(route("ict-equipments.store"), form.value, {
      onError: e => errors.value = e,
      onSuccess: () => {
        closeModal()
        getEquipments()
        Swal.fire({
          icon: "success",
          title: "Success",
          text: "New equipment has been added!",
          timer: 2000,
          showConfirmButton: false,
        })
      }
    })
  }

  const updateEquipment = async (id) => {
    errors.value = {}
    router.put(route("ict-equipments.update", id), form.value, {
      onError: e => errors.value = e,
      onSuccess: () => {
        closeModal()
        getEquipments()
        Swal.fire({
          icon: "success",
          title: "Updated",
          text: "Equipment details have been updated!",
          timer: 2000,
          showConfirmButton: false,
        })
      }
    })
  }

  const destroyEquipment = async (id) => {
    Swal.fire({
      title: "Are you sure?",
      text: "This equipment will be permanently deleted!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Yes, delete it!",
    }).then(result => {
      if (!result.isConfirmed) return

      router.delete(route("ict-equipments.destroy", id), {
        onSuccess: () => {
          getEquipments()
          Swal.fire({
            icon: "success",
            title: "Deleted",
            text: "Equipment has been deleted.",
            timer: 2000,
            showConfirmButton: false,
          })
        }
      })
    })
  }

  // ---------------------------------------------------------------------------
  // UI FUNCTIONS
  // ---------------------------------------------------------------------------
  const sortBy = key => {
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
    form.value = mode === "edit" && eq ? { ...eq } : emptyForm()
    showModal.value = true
  }

  const closeModal = () => {
    showModal.value = false
    selectedEquipment.value = null
  }

  const submitEquipment = () => {
    if (modalMode.value === "create") return storeEquipment()
    if (modalMode.value === "edit") return updateEquipment(selectedEquipment.value.id)
  }

  const viewEquipment = eq => openModal("view", eq)

  // ---------------------------------------------------------------------------
  // PMS HISTORY (NEW)
  // ---------------------------------------------------------------------------
  const openPmsHistory = (eq) => {
    selectedEquipment.value = eq
    selectedPmsHistory.value = eq.pms_history || []
    showPmsModal.value = true
  }
  const formatDate = (dateStr) => {
    if (!dateStr) return "";
    const date = new Date(dateStr);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0"); // Months are 0-indexed
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
  };
  // ---------------------------------------------------------------------------
  // EXPORT
  // ---------------------------------------------------------------------------
  const exportCSV = () => {
    const headers = [
      "ID", "Serial No", "Description", "Status", "Location",
      "Category", "Amount", "Owner Name"
    ]

    const rows = enrichedEquipments.value.map(eq => [
      eq.id, eq.serial_no, eq.description, eq.status,
      eq.location, eq.category, eq.amount, eq.owner_name
    ])

    const csv = [headers, ...rows].map(r => r.join(",")).join("\n")

    const blob = new Blob([csv], { type: "text/csv" })
    const url = URL.createObjectURL(blob)
    const a = document.createElement("a")
    a.href = url
    a.download = "equipments.csv"
    a.click()
  }

  const printTable = () => {
    const w = window.open("", "_blank")

    const rows = enrichedEquipments.value.map(eq => `
      <tr>
        <td>${eq.id}</td>
        <td>${eq.serial_no}</td>
        <td>${eq.description}</td>
        <td>${eq.status}</td>
        <td>${eq.location}</td>
        <td>${eq.category}</td>
        <td>${eq.amount}</td>
        <td>${eq.owner_name}</td>
      </tr>
    `).join("")

    w.document.write(`
      <h3>ICT Equipment Inventory</h3>
      <table border="1" cellspacing="0" cellpadding="5">
        <tr>
          <th>ID</th><th>Serial No</th><th>Description</th>
          <th>Status</th><th>Location</th><th>Category</th><th>Amount</th><th>Owner</th>
        </tr>
        ${rows}
      </table>
    `)

    w.print()
    w.close()
  }
  
  const printPmsHistory = () => {
  if (!selectedEquipment.value) return;

  const history = selectedPmsHistory.value;

  const rows = history.map(pms => `
    <tr>
      <td class="center">${formatDate(pms.pms_date)}</td>
      <td>${pms.description}</td>
      <td class="center">${pms.type === 'PMS' ? '✔' : ''}</td>
      <td class="center">${pms.type === 'Repair' ? '✔' : ''}</td>
      <td class="center">${pms.cost_of_repair ? '₱' + pms.cost_of_repair : ''}</td>
      <td>${pms.remarks || ''}</td>
    </tr>
  `).join("");

  const html = `
    <html>
      <head>
        <title>Equipment History Card</title>
        <style>
          body { font-family: "Times New Roman", Times, serif; margin: 20px; font-size: 12px; }
          h2, h3 { text-align: center; margin-bottom: 10px; }
          p { margin: 2px 0; }
          table { width: 100%; border-collapse: collapse; margin-top: 10px; }
          th, td { border: 1px solid #000; padding: 1px; }
          th { background-color: #f3f3f3; text-align: center; } /* Center headers */
          td.center { text-align: center; } /* Center check marks */
          td { text-align: left; } /* Default left align for other cells */
        </style>
      </head>
      <body>
        <h2>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</h2>
        <h3>CAMPUS: CARAGA REGION CAMPUS</h3>
        <h3>EQUIPMENT HISTORY CARD</h3>

        <p><strong>Description:</strong> ${selectedEquipment.value.description}</p>
        <p><strong>Date Acquired:</strong> ${formatDate(selectedEquipment.value.date_acquired)}</p>
        <p><strong>Supplier:</strong> ${selectedEquipment.value.supplier || ''}</p>
        <p><strong>Property No. / S/N:</strong> ${selectedEquipment.value.serial_no}</p>

        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Details/Description</th>
              <th>Preventive Maintenance</th>
              <th>Repair</th>
              <th>Cost of Repair</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>
        <br>
        <p><small>PSHS-00-F-GSM-07-Ver02-Rev0-02/01/20</small></p>
      </body>
    </html>
  `;

  const w = window.open("", "_blank");
  w.document.write(html);
  w.document.close();
  w.focus();
  w.print();
  w.close();
};



  // ---------------------------------------------------------------------------
  // RETURN
  // ---------------------------------------------------------------------------
  return {
    // Data
    equipments,
    filteredEquipments,
    totalPages,
    searchQuery,
    currentPage,

    // Modal & Form
    showModal,
    modalMode,
    selectedEquipment,
    form,
    openModal,
    closeModal,
    submitEquipment,
    viewEquipment,

    // PMS
    showPmsModal,
    selectedPmsHistory,
    openPmsHistory,
    formatDate,

    // CRUD
    getEquipments,
    getEquipment,
    storeEquipment,
    updateEquipment,
    destroyEquipment,

    // Sorting
    sortBy,

    // Export
    exportCSV,
    printTable,
    printPmsHistory,
  }
}
