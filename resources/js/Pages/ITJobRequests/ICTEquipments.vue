<script setup>
import { Head, usePage } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import {
  EyeIcon,
  PencilSquareIcon,
  PrinterIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  ClockIcon,
} from "@heroicons/vue/24/outline"
import useEquipments from "@/Composables/useEquipments.js"

// Props from backend
const props = defineProps({
  equipments: Array,
  users: Array, // ✅ Accept users from backend
})

// include ALL returned properties you use in template
const {
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
  storeEquipment,
  updateEquipment,
  destroyEquipment,
  sortBy,
  openModal,
  closeModal,
  formatDate,
  submitEquipment,
  viewEquipment,
  exportCSV,
  printTable,
  printPmsHistory,

  // PMS-related (these were missing before and caused your error)
  showPmsModal,
  selectedPmsHistory,
  openPmsHistory,
} = useEquipments(props.equipments, props.users)

const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null

// ✅ Print Modal Content
function printModal() {
  const printArea = document.getElementById("printArea");
  if (!printArea) return;

  const clonedContent = printArea.cloneNode(true);

  // Fix QR image path
  const images = clonedContent.getElementsByTagName("img");
  for (let img of images) {
    if (img.src.startsWith("/")) {
      img.src = `${window.location.origin}${img.src}`;
    }
  }

  const newWindow = window.open("", "", "width=900,height=650");
  newWindow.document.write(`
    <html>
      <head>
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; }
          h2 { text-align: center; margin-bottom: 20px; }
          .print-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            align-items: start;
          }
          .print-left { text-align: center; }
          .print-left img {
            max-width: 200px;
            height: auto;
            display: block;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 8px;
            border-radius: 8px;
          }
          .print-right p { margin: 6px 0; font-size: 14px; }
          .print-right strong {
            display: inline-block;
            width: 120px;
          }
        </style>
      </head>
      <body>
        
        <div class="print-container">
          <div class="print-left">
            ${clonedContent.querySelector("img")?.outerHTML || ""}
          </div>
          <div class="print-right">
            ${Array.from(clonedContent.querySelectorAll("p"))
              .map((p) => p.outerHTML)
              .join("")}
          </div>
        </div>
      </body>
    </html>
  `);

  newWindow.document.close();
  newWindow.onload = function () {
    newWindow.focus();
    newWindow.print();
    newWindow.close();
  };
}
</script>

<template>
  <Head title="ICT Equipment Inventory" />
  <AdminLayout title="ICT Equipment Inventory">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">ICT Equipment Inventory</h1>
        <button
          @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          + Add Equipment
        </button>
      </div>

      <!-- Search & Actions -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <div class="flex justify-between items-center mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search equipment..."
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
          
          <div class="flex gap-2">
            <button @click="exportCSV" title="Export CSV">
              <ArrowDownTrayIcon class="w-5 h-5 text-blue-600" />
            </button>
            <button @click="printTable" title="Print table">
              <PrinterIcon class="w-5 h-5 text-blue-600" />
            </button>
          </div>
        </div>

        <!-- Equipment Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('id')">ID</th>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('property_no')">Serial No</th>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('description')">Description</th>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('owner_id')">Owner</th>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('status')">Status</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="eq in filteredEquipments" :key="eq.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ eq.id }}</td>
                
                <td class="px-4 py-3">{{ eq.serial_no }}</td>
                <td class="px-4 py-3">{{ eq.description }}</td>
                <td class="px-4 py-3">
                  {{ props.users.find(u => u.id === eq.owner_id)?.name || 'N/A' }}
                </td>
                <td class="px-4 py-3">{{ eq.status }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <button @click="viewEquipment(eq)" class="p-1 hover:bg-gray-100 rounded" title="View">
                      <EyeIcon class="w-5 h-5 text-blue-600"/>
                    </button>
                    <button @click="openModal('edit', eq)" class="p-1 hover:bg-gray-100 rounded" title="Edit">
                      <PencilSquareIcon class="w-5 h-5 text-yellow-600"/>
                    </button>
                    <button
                      @click="openPmsHistory(eq)"
                      class="p-1 hover:bg-gray-100 rounded"
                      title="PMS History"
                    >
                      <ClockIcon class="w-5 h-5 text-green-600" />
                    </button>

                    <button @click="destroyEquipment(eq)" class="p-1 hover:bg-gray-100 rounded" title="Delete">
                      <TrashIcon class="w-5 h-5 text-red-600"/>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredEquipments.length===0">
                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                  No equipment found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage===1"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >
            Prev
          </button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage = Math.min(totalPages, currentPage + 1)"
            :disabled="currentPage===totalPages"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
          <!-- Close button -->
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode==='create' ? 'New Equipment Form' : modalMode==='edit' ? 'Edit Equipment' : 'View Equipment Details' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedEquipment" class="space-y-2">
            <div id="printArea">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- First column: QR Code -->
                <div class="flex items-center justify-center">
                  <img
                    v-if="selectedEquipment.qr_code_path"
                    :src="`/${selectedEquipment.qr_code_path}`"
                    alt="QR Code"
                    class="w-48 h-48 border rounded p-2"
                  />
                </div>

                <!-- Second column: Details -->
                <div class="space-y-1">
                  <p><strong>Owner:</strong> {{ props.users.find(u => u.id === selectedEquipment.owner_id)?.name || 'N/A' }}</p>
                  <p><strong>Category:</strong> {{ selectedEquipment.category }}</p>
                  <p><strong>Property No:</strong> {{ selectedEquipment.property_no }}</p>
                  <p><strong>Serial No:</strong> {{ selectedEquipment.serial_no }}</p>
                  <p><strong>Description:</strong> {{ selectedEquipment.description }}</p>
                  <p><strong>Date Acquired:</strong> {{ selectedEquipment.date_acquired }}</p>
                  <p><strong>Amount:</strong> {{ selectedEquipment.amount }}</p>
                  <p><strong>Status:</strong> {{ selectedEquipment.status }}</p>
                  <p><strong>Location:</strong> {{ selectedEquipment.location }}</p>
                  <p><strong>Remarks:</strong> {{ selectedEquipment.remarks }}</p>
                </div>
              </div>
            </div>

            <!-- Print button -->
            <div class="mt-4 text-right">
              <button @click="printModal" title="Print">
                <PrinterIcon class="w-5 h-5 text-blue-600" />
              </button>
            </div>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitEquipment" class="grid grid-cols-2 gap-4">
            <!-- Equipment Category -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700">Equipment Category <span class="text-red-500">*</span></label>
              <select v-model="form.category" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required>
                <option value="">Please select category</option>
                <option value="CPU/System Unit">CPU/System Unit</option>
                <option value="Monitor">Monitor</option>
                <option value="Mouse">Mouse</option>
                <option value="Keyboard">Keyboard</option>
                <option value="UPS">UPS</option>
                <option value="AVR">AVR</option>
                <option value="Printer">Printer</option>
                <option value="Laptop">Laptop</option>
                <option value="Scanner">Scanner</option>
                <option value="Projector">Projector</option>
                <option value="Network Devices">Network Devices</option>
                <option value="CCTV Camera">CCTV Camera</option>
                <option value="CCTV NVR/DVR">CCTV NVR/DVR</option>
                <option value="Access Point">Access Point</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <!-- Owner (Dropdown) -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Owner <span class="text-red-500">*</span></label>
              <select v-model="form.owner_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required>
                <option value="">Select Owner</option>
                <option v-for="user in props.users" :key="user.id" :value="user.id">
                  {{ user.name }}
                </option>
              </select>
            </div>

            <!-- Property No -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Property No</label>
              <input v-model="form.property_no" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>

            <!-- Serial No -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700">Serial No <span class="text-red-500">*</span></label>
              <input v-model="form.serial_no" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>

            <!-- Device Description -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700">Device Description / Model <span class="text-red-500">*</span></label>
              <input v-model="form.description" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>

            <!-- Date Acquired -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Date Acquired</label>
              <input v-model="form.date_acquired" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>

            <!-- Amount -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Amount</label>
              <input v-model="form.amount" type="number" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>

            <!-- Equipment Status -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Equipment Status <span class="text-red-500">*</span></label>
              <select v-model="form.status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required>
                <option value="">Select Status</option>
                <option value="Good Working">Good Working</option>
                <option value="For Repair">For Repair</option>
                <option value="Disposed">Disposed</option>
              </select>
            </div>

            <!-- Location -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Location <span class="text-red-500">*</span></label>
              <input v-model="form.location" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>

            <!-- Remarks -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700">Remarks</label>
              <textarea v-model="form.remarks" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"></textarea>
            </div>

            <!-- Buttons -->
            <div class="col-span-2 flex justify-end space-x-3 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            </div>
          </form>
        </div>
      </div>

      <!-- PMS HISTORY MODAL -->
      <div
        v-if="showPmsModal"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      >
        <div class="bg-white w-full max-w-3xl rounded-lg p-6 relative">

          <!-- Close button -->
          <button
            @click="showPmsModal = false"
            class="absolute top-3 right-3 text-gray-500 hover:text-gray-800"
          >
            ✕
          </button>

          <h2 class="text-2xl font-bold mb-4">
            PMS History for {{ selectedEquipment?.description }} / {{ selectedEquipment?.serial_no }}
          </h2>

          <div v-if="selectedPmsHistory.length === 0" class="text-center text-gray-500 p-4">
            No PMS history found.
          </div>

          <ul v-else class="space-y-4 max-h-96 overflow-y-auto">
            <li
              v-for="pms in selectedPmsHistory"
              :key="pms.id"
              class="border p-4 rounded-lg bg-gray-50"
            >
              <div class="font-semibold">{{ formatDate(pms.pms_date) }}</div>
              <div class="text-sm text-gray-700">
                <b>Type:</b> {{ pms.type }}
              </div>
              <div class="text-sm text-gray-700">
                <b>Description:</b> {{ pms.description }}
              </div>
              <div class="text-sm text-gray-700">
                <b>Cost of Repair:</b> ₱{{ pms.cost_of_repair }}
              </div>
              <div class="text-sm text-gray-700">
                <b>Remarks:</b> {{ pms.remarks }}
              </div>
              <div class="text-sm text-gray-700">
                <b>Created By:</b> User ID {{ pms.created_by }}
              </div>
            </li>
          </ul>
          <!-- Print button -->
          <div class="mt-4 text-right">
            <button @click="printPmsHistory" title="Print History" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
              <PrinterIcon class="w-5 h-5 inline" /> Print History
            </button>
          </div>

        </div>
      </div>

    </div>
  </AdminLayout>
</template>
