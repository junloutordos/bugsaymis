<script setup>
import { Head, usePage } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import {
  EyeIcon,
  PencilSquareIcon,
  PrinterIcon,
  TrashIcon,
  ArrowDownTrayIcon,
} from "@heroicons/vue/24/outline"
import useEquipments from "@/Composables/useEquipments.js"

// Props from backend
const props = defineProps({ equipments: Array })



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
  submitEquipment,
  viewEquipment,
  exportCSV,
  printTable,
} = useEquipments(props.equipments)

// Auth info
const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null
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
            <button @click="exportCSV">
              <ArrowDownTrayIcon class="w-5 h-5 text-blue-600" />
            </button>
            <button @click="printTable">
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
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('property_no')">Property No</th>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('description')">Description</th>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('status')">Status</th>
                <th class="px-4 py-3 text-left">QR Code</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="eq in filteredEquipments" :key="eq.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ eq.id }}</td>
                <td class="px-4 py-3">{{ eq.property_no }}</td>
                <td class="px-4 py-3">{{ eq.description }}</td>
                <td class="px-4 py-3">{{ eq.status }}</td>
                <td class="px-4 py-3">
                  <img v-if="eq.qr_code_path" :src="`/${eq.qr_code_path}`" class="w-12 h-12" />
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <button @click="viewEquipment(eq)" class="p-1 hover:bg-gray-100 rounded">
                      <EyeIcon class="w-5 h-5 text-blue-600"/>
                    </button>
                    <button @click="openModal('edit', eq)" class="p-1 hover:bg-gray-100 rounded">
                      <PencilSquareIcon class="w-5 h-5 text-yellow-600"/>
                    </button>
                    <button @click="deleteEquipment(eq)" class="p-1 hover:bg-gray-100 rounded">
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
          <button @click="currentPage--" :disabled="currentPage===1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage===totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode==='create' ? 'Add Equipment' : modalMode==='edit' ? 'Edit Equipment' : 'View Equipment' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedEquipment" class="space-y-2">
            <p>Property No: <strong>{{ selectedEquipment.property_no }}</strong></p>
            <p>Description: <strong>{{ selectedEquipment.description }}</strong></p>
            <p>Location: <strong>{{ selectedEquipment.location }}</strong></p>
            <p>Status: <strong>{{ selectedEquipment.status }}</strong></p>
            <p>Category: <strong>{{ selectedEquipment.category }}</strong></p>
            <p>Amount: <strong>{{ selectedEquipment.amount }}</strong></p>
            <img v-if="selectedEquipment.qr_code_path" :src="`/${selectedEquipment.qr_code_path}`" class="w-24 h-24 mt-2"/>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitEquipment" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Property No</label>
              <input v-model="form.property_no" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <input v-model="form.description" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Location</label>
              <input v-model="form.location" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Status</label>
              <input v-model="form.status" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Category</label>
              <input v-model="form.category" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Amount</label>
              <input v-model="form.amount" type="number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
