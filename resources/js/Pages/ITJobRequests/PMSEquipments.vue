<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { computed, ref, reactive } from "vue"
import html2pdf from "html2pdf.js"
import { ArrowDownTrayIcon } from "@heroicons/vue/24/outline"
import { router, useForm } from "@inertiajs/vue3"
import Swal from "sweetalert2"

const props = defineProps({
  pms: Object,
  equipments: Array // equipments should now include `history_dates` array from backend
})

// Months
const months = [
  "Aug", "Sept", "Oct", "Nov", "Dec",
  "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul"
]

// Modal state
const showModal = ref(false)
const selectedEquipment = ref(null)
const selectedMonth = ref(null)
const checklistItems = ref([])
const checklistStatus = reactive([])

// Additional modal inputs
const modalData = reactive({
  actualDate: "",
  pmsType: "PMS",
  cost: 0,
  remarks: ""
})

// Equipment checklist mapping
const equipmentChecklist = {
  "CPU/System Unit": [
    "Clean external casing and vents",
    "Check power cables and plugs",
    "Verify fans and temperature",
    "Update OS & antivirus",
    "Backup important files",
    "Inspect for unusual noises or errors"
  ],
  "Monitor": [
    "Clean screen and casing",
    "Inspect power cable and connectors",
    "Check display brightness & resolution",
    "Look for dead pixels or flickering"
  ],
  "Mouse": [
    "Clean exterior",
    "Check cable or wireless connection",
    "Test responsiveness and buttons"
  ],
  "Keyboard": [
    "Clean keys and casing",
    "Test all keys",
    "Check connection/cable or wireless functionality"
  ],
  "UPS": [
    "Check battery health and charge",
    "Inspect cables and connections",
    "Test output voltage and backup duration"
  ],
  "AVR (Automatic Voltage Regulator)": [
    "Inspect input/output connections",
    "Check voltage regulation performance",
    "Test overload and protection functionality"
  ],
  "Printer": [
    "Clean exterior and interior (rollers, trays)",
    "Check cartridges/toner levels",
    "Test print quality",
    "Inspect cables and connections"
  ],
  "Laptop": [
    "Clean screen, keyboard, and touchpad",
    "Check battery health and charging",
    "Update OS & antivirus",
    "Backup critical files"
  ],
  "Scanner": [
    "Clean scanning surface and exterior",
    "Check cables and connection",
    "Test scanning quality and calibration"
  ],
  "Projector": [
    "Clean lens and vents",
    "Check lamp hours",
    "Test display quality",
    "Inspect cables and connectivity"
  ],
  "Network Devices": [
    "Inspect cables, ports, and connectors",
    "Check firmware updates",
    "Test network speed and connectivity",
    "Clean dust from vents"
  ],
  "CCTV Camera": [
    "Clean lens and exterior",
    "Verify positioning and focus",
    "Check cabling and connections",
    "Test video feed"
  ],
  "CCTV NVR/DVR": [
    "Check hard drive health",
    "Verify recording settings",
    "Update firmware",
    "Inspect power and network connections"
  ],
  "Access Point": [
    "Check signal strength and coverage",
    "Inspect cabling and power supply",
    "Update firmware",
    "Test connectivity"
  ],
  "Other": [
    "Follow manufacturer’s recommended preventive maintenance",
    "Clean, inspect, and test functionality as applicable"
  ]
}

// Open modal
const openModal = (equipment, monthIdx) => {
  selectedEquipment.value = equipment
  selectedMonth.value = monthIdx
  checklistItems.value = equipmentChecklist[equipment.category] || ["No checklist available."]

  // Init checklist state
  if (!selectedEquipment.value.checklistStatus) selectedEquipment.value.checklistStatus = {}
  if (!selectedEquipment.value.checklistStatus[monthIdx]) {
    selectedEquipment.value.checklistStatus[monthIdx] = checklistItems.value.map(() => false)
  }

  checklistStatus.length = 0
  checklistStatus.push(...selectedEquipment.value.checklistStatus[monthIdx])

  // Reset modal inputs
  modalData.actualDate = ""
  modalData.pmsType = "PMS"
  modalData.cost = 0
  modalData.remarks = ""

  showModal.value = true
}

// Inertia form
const form = useForm({
  ict_pms_id: props.pms.id,
  equipment_id: null,
  pms_date: "",
  description: "",
  type: "PMS",
  cost_of_repair: 0,
  remarks: ""
})

// Save activity
const saveActivity = () => {
  const description = checklistItems.value
    .filter((_, idx) => checklistStatus[idx])
    .join(", ")

  // ✅ Always PMS regardless of checked items
  const type = "PMS"

  form.ict_pms_id = props.pms.id
  form.equipment_id = selectedEquipment.value.id
  form.pms_date = modalData.actualDate || new Date().toISOString().split("T")[0]
  form.description = description
  form.type = type
  form.cost_of_repair = modalData.cost
  form.remarks = modalData.remarks

  router.post(route("ict-pms-history.store"), form, {
    preserveScroll: true,
    onSuccess: () => {
      // immediately reflect done state
      if (!selectedEquipment.value.history_dates) {
        selectedEquipment.value.history_dates = []
      }
      selectedEquipment.value.history_dates.push(form.pms_date)

      Swal.fire({
        icon: "success",
        title: "Saved!",
        text: "PMS history has been recorded successfully.",
        timer: 2000,
        showConfirmButton: false
      })

      showModal.value = false
      form.reset("equipment_id", "pms_date", "description", "type", "cost_of_repair", "remarks")
    },
    onError: () => {
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "Failed to save PMS history. Please try again."
      })
    }
  })
}


// Export PDF
const exportPDF = () => {
  const content = document.getElementById("pdfContent")
  const opt = {
    margin: 0.5,
    filename: `PMS_${props.pms.office_area}_${props.pms.school_year}.pdf`,
    image: { type: "jpeg", quality: 0.98 },
    html2canvas: { scale: 2, backgroundColor: null },
    jsPDF: { unit: "in", format: "a4", orientation: "landscape" }
  }
  html2pdf().set(opt).from(content).save()
}

// Scheduled map
const monthStatusMap = computed(() => {
  const map = {}
  if (props.pms?.schedule_dates?.length) {
    props.pms.schedule_dates.forEach(d => {
      const date = new Date(d.schedule_date)
      const month = date.toLocaleString("en-US", { month: "short" })
      const idx = months.findIndex(m => m.toLowerCase().startsWith(month.toLowerCase()))
      if (idx !== -1) {
        map[idx] = d.status
      }
    })
  }
  return map
})

// History map: equipment -> [monthIdx]
const equipmentHistoryMap = computed(() => {
  const map = {}
  props.equipments.forEach(eq => {
    map[eq.id] = []
    if (eq.history_dates?.length) {
      eq.history_dates.forEach(d => {
        const date = new Date(d)
        const month = date.toLocaleString("en-US", { month: "short" })
        const idx = months.findIndex(m => m.toLowerCase().startsWith(month.toLowerCase()))
        if (idx !== -1) map[eq.id].push(idx)
      })
    }
  })
  return map
})
</script>

<template>
  <Head title="Preventive Maintenance Schedule" />
  <AdminLayout title="Preventive Maintenance Schedule">

    <!-- Export Button -->
    <div class="flex justify-end gap-2 mb-4">
      <button 
        @click="exportPDF" 
        class="flex items-center gap-1 px-3 py-1 border border-blue-600 rounded text-blue-600 bg-white hover:bg-blue-50"
      >
        <ArrowDownTrayIcon class="w-5 h-5" />
        <span>Export</span>
      </button>
    </div>

    <!-- Card Display -->
    <div class="p-8 bg-white rounded-xl shadow border border-gray-300">
      <div id="pdfContent">
        <!-- Header -->
        <div class="text-center mb-4">
          <h2 class="text-sm font-semibold">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</h2>
          <h3 class="text-sm">CAMPUS: CARAGA REGION</h3>
          <h1 class="text-lg font-bold mt-2 underline">PREVENTIVE MAINTENANCE SCHEDULE</h1>
          <p class="mt-1">For the School Year <span class="underline">{{ pms.school_year }}</span></p>
        </div>

        <!-- Area / Page -->
        <div class="flex justify-between text-sm mb-4">
          <p><strong>AREA:</strong> <span class="underline">{{ pms.office_area }}</span></p>
          <p><strong>Page:</strong>  ___ of ____</p>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="border-collapse w-full text-center text-[10pt] border border-black">
            <thead>
              <tr>
                <th class="border border-black p-1">Eqpt/Instrmt No.</th>
                <th class="border border-black p-1">Description</th>
                <th class="border border-black p-1">Location</th>
                <th class="border border-black p-1">Frequency</th>
                <th v-for="m in months" :key="m" class="border border-black p-1">{{ m }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(eq, i) in equipments" :key="eq.id">
                <td class="border border-black p-1">{{ i+1 }}</td>
                <td class="border border-black p-1">{{ eq.description }}</td>
                <td class="border border-black p-1">{{ eq.location ?? '-' }}</td>
                <td class="border border-black p-1">{{ pms.frequency }}</td>

                <!-- Refactored cell -->
                <td v-for="(m, idx) in months" :key="m" class="border border-black p-1">
                  <!-- PMS History always wins -->
                  <span v-if="equipmentHistoryMap[eq.id]?.includes(idx)" class="text-blue-600">*</span>

                  <!-- Scheduled but no history yet -->
                  <span 
                    v-else-if="monthStatusMap[idx]" 
                    class="text-green-600 cursor-pointer" 
                    @click="openModal(eq, idx)"
                  >
                    ✓
                  </span>
                </td>
              </tr>
              <tr v-if="equipments.length === 0">
                <td :colspan="months.length + 4" class="border border-black p-1 text-gray-500">
                  No equipments assigned.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ✅ Legend + Footer + Form Number remain the same -->
        <div class="mt-4 text-xs">
          <p>
            <strong>Legend:</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <span class="text-green-600">✓</span> - scheduled; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <span class="text-red-600">×</span> - not attended; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <span class="text-blue-600">*</span> - done as scheduled
          </p>
        </div>

        <div class="mt-4 border text-sm">
          <table class="w-full border-collapse">
            <tbody>
              <tr>
                <td class="border border-black px-2 py-2 align-top w-1/4">
                  <p class="mb-2">Prepared By:</p><br />
                  <p class="font-semibold underline text-center">JUNLOU R. TORDOS</p>
                  <p class="text-center">Information Systems Analyst I</p>
                </td>
                <td class="border border-black px-2 py-2 w-1/4 align-top">
                  <p>Date:</p>
                </td>
                <td class="border border-black px-2 py-2 align-top w-1/4">
                  <p class="mb-2">Noted By:</p><br />
                  <p class="font-semibold underline text-center">ENGR. RAMIL A. SANCHEZ</p>
                  <p class="text-center">Campus Director</p>
                </td>
                <td class="border border-black px-2 py-2 w-1/4 align-top">
                  <p>Date:</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-between text-sm mb-4 mt-2">
          <p>PSHS-00-F-GSM-06-Ver02-Rev0-02/01/20</p>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg w-96 max-h-[80vh] overflow-y-auto">
        <h3 class="text-lg font-semibold mb-4">PMS Checklist</h3>
        <p class="mb-2"><strong>Equipment:</strong> {{ selectedEquipment.description }}</p>
        <p class="mb-2"><strong>Month:</strong> {{ months[selectedMonth] }}</p>

        <!-- Checklist -->
        <div class="mb-4">
          <label v-for="(item, idx) in checklistItems" :key="idx" class="flex items-center gap-2 mb-1">
            <input type="checkbox" v-model="checklistStatus[idx]" />
            <span>{{ item }}</span>
          </label>
        </div>

        <!-- Additional Inputs -->
        <div class="mb-4 space-y-2">
          <div>
            <label class="block text-sm font-medium">Actual PMS Date:</label>
            <input type="date" v-model="modalData.actualDate" class="w-full border rounded px-2 py-1" />
          </div>
          <input type="hidden" v-model="modalData.pmsType" />
          <div>
            <label class="block text-sm font-medium">Cost of Repair (if any):</label>
            <input type="number" v-model="modalData.cost" class="w-full border rounded px-2 py-1" min="0" step="0.01" />
          </div>
          <div>
            <label class="block text-sm font-medium">Remarks:</label>
            <textarea v-model="modalData.remarks" class="w-full border rounded px-2 py-1"></textarea>
          </div>
        </div>

        <!-- Modal Buttons -->
        <div class="flex justify-end gap-2">
          <button 
            @click="showModal = false" 
            class="px-4 py-2 border rounded text-gray-700 bg-white hover:bg-gray-100"
          >
            Cancel
          </button>
          <button 
            @click="saveActivity" 
            class="px-4 py-2 border border-blue-600 rounded text-blue-600 bg-white hover:bg-blue-50"
          >
            Save
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
