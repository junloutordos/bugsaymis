<script setup>
import { Head, usePage } from "@inertiajs/vue3"
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import { PencilSquareIcon, TrashIcon, EyeIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const props = defineProps({
  assets: Array,
  buildings: Array,
  rooms: Array,
  users: Array,
})

const assets = ref(props.assets || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

// responsive: track window width to switch to card layout on small screens
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)
const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

const filteredAssets = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const results = (assets.value || []).filter(a => (a.asset_name || '').toString().toLowerCase().includes(q) || (a.property_no || '').toString().toLowerCase().includes(q) || (a.category || '').toString().toLowerCase().includes(q))
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil((assets.value || []).filter(a => (a.asset_name || '').toString().toLowerCase().includes(searchQuery.value.trim().toLowerCase()) || (a.property_no || '').toString().toLowerCase().includes(searchQuery.value.trim().toLowerCase()) || (a.category || '').toString().toLowerCase().includes(searchQuery.value.trim().toLowerCase())).length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })

const showModal = ref(false)
const photoFile = ref(null)
const editingId = ref(null)

const form = ref({
  property_no: '',
  asset_name: '',
  description: '',
  category: '',
  serial_number: '',
  purchase_date: '',
  cost: '',
  condition: 'Good',
    building_id: '',
    room_id: '',
  assigned_user_id: '',
  warranty_until: '',
  status: 'Active',
  remarks: '',
})

const filteredRooms = computed(() => {
  if (!form.value.building_id) return props.rooms || []
  return (props.rooms || []).filter(r => String(r.building_id) === String(form.value.building_id))
})

watch(() => form.value.building_id, (newVal) => {
  if (!newVal) {
    form.value.room_id = ''
    return
  }
  const selectedRoom = (props.rooms || []).find(r => String(r.id) === String(form.value.room_id))
  if (!selectedRoom || String(selectedRoom.building_id) !== String(newVal)) {
    form.value.room_id = ''
  }
})

const openModal = () => { resetForm(); showModal.value = true }
const closeModal = () => { showModal.value = false; photoFile.value = null; resetForm() }

const handlePhoto = (e) => {
  photoFile.value = e.target.files && e.target.files[0] ? e.target.files[0] : null
}

const submitAsset = () => {
  const fd = new FormData()
  for (const key in form.value) {
    if (form.value[key] !== null && form.value[key] !== undefined) fd.append(key, form.value[key])
  }
  if (photoFile.value) fd.append('photo', photoFile.value)

  if (editingId.value) {
    // When sending files, PHP/Laravel does not reliably parse multipart PUT requests.
    // Use POST with method spoofing so Laravel receives the form data and files.
    fd.append('_method', 'PUT')
    router.post(`/assets/${editingId.value}`, fd, {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Asset updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errors).flat().join('\n') }) }
    })
  } else {
    router.post('/assets', fd, {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Asset added', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to add', text: Object.values(errors).flat().join('\n') }) }
    })
  }
}

const openEdit = (asset) => {
  editingId.value = asset.id
  form.value = {
    property_no: asset.property_no || '',
    asset_name: asset.asset_name || '',
    description: asset.description || '',
    category: asset.category || '',
    serial_number: asset.serial_number || '',
    purchase_date: asset.purchase_date || '',
    cost: asset.cost || '',
    condition: asset.condition || 'Good',
    building_id: asset.building_id || '',
    room_id: asset.room_id || '',
    assigned_user_id: asset.assigned_user_id || '',
    warranty_until: asset.warranty_until || '',
    status: asset.status || 'Active',
    remarks: asset.remarks || '',
  }
  showModal.value = true
}

const deleteAsset = (asset) => {
  Swal.fire({ title: `Delete asset ${asset.property_no}?`, text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel' }).then((res) => {
    if (!res.isConfirmed) return
    router.delete(`/assets/${asset.id}`, {
      onSuccess: () => { Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errors).flat().join('\n') || 'Failed to delete' }) }
    })
  })
}

const resetForm = () => {
  editingId.value = null
  form.value = {
    property_no: '',
    asset_name: '',
    description: '',
    category: '',
    serial_number: '',
    purchase_date: '',
    cost: '',
    condition: 'Good',
    building_id: '',
    room_id: '',
    assigned_user_id: '',
    warranty_until: '',
    status: 'Active',
    remarks: '',
  }
  photoFile.value = null
}

// View modal
const viewModal = ref(false)
const viewAsset = ref(null)
const openView = (asset) => {
  viewAsset.value = asset
  viewModal.value = true
}
const closeView = () => { viewModal.value = false; viewAsset.value = null }

const getPhotoUrl = (asset) => {
  if (!asset || !asset.photo) return null
  const p = asset.photo
  if (p.indexOf('http') === 0) return p
  if (p.startsWith('assets/')) return '/storage/' + p
  return '/storage/assets/' + p
}

</script>

<template>
  <Head title="Assets" />
  <AdminLayout title="Assets">
    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Assets</h1>
        <button @click="openModal" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
          New Asset
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="mb-4">
          <input v-model="searchQuery" type="text" placeholder="Search assets..." class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Property No</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-left">Condition</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Assigned To</th>
                <th class="px-4 py-3 text-left">Location</th>
                <th class="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="asset in filteredAssets" :key="asset.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ asset.id }}</td>
                <td class="px-4 py-3">{{ asset.property_no }}</td>
                <td class="px-4 py-3">{{ asset.asset_name }}</td>
                <td class="px-4 py-3">{{ asset.category }}</td>
                <td class="px-4 py-3">{{ asset.condition }}</td>
                <td class="px-4 py-3">{{ asset.status }}</td>
                <td class="px-4 py-3">{{ asset.assigned_user?.name ?? '—' }}</td>
                <td class="px-4 py-3">{{ asset.building?.name ?? asset.room?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openView(asset)" class="p-1 hover:bg-gray-100 rounded" title="View">
                      <EyeIcon class="w-5 h-5 text-blue-600" />
                    </button>
                    <button @click="openEdit(asset)" class="p-1 hover:bg-gray-100 rounded" title="Edit">
                      <PencilSquareIcon class="w-5 h-5 text-yellow-600" />
                    </button>
                    <button @click="deleteAsset(asset)" class="p-1 hover:bg-gray-100 rounded" title="Delete">
                      <TrashIcon class="w-5 h-5 text-red-600" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredAssets.length === 0">
                <td colspan="9" class="px-4 py-6 text-center text-gray-500">No assets found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile / small screens: card list -->
        <div v-else class="space-y-3">
          <div v-for="asset in filteredAssets" :key="asset.id" class="bg-white border rounded-lg p-4 shadow-sm">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-sm text-gray-500">ID: {{ asset.id }}</div>
                <div class="text-lg font-semibold">{{ asset.asset_name }}</div>
                <div class="text-sm text-gray-600">Property No: {{ asset.property_no ?? '—' }}</div>
                <div class="text-sm text-gray-600">Category: {{ asset.category ?? '—' }}</div>
                <div class="text-sm text-gray-600">Condition: {{ asset.condition ?? '—' }}</div>
                <div class="text-sm text-gray-600">Status: {{ asset.status ?? '—' }}</div>
                <div class="text-sm text-gray-600">Assigned: {{ asset.assigned_user?.name ?? '—' }}</div>
                <div class="text-sm text-gray-600">Location: {{ asset.building?.name ?? asset.room?.name ?? '—' }}</div>
              </div>
              <div class="flex flex-col items-end gap-2">
                <button @click.prevent="openView(asset)" class="px-3 py-1 bg-gray-200 text-gray-800 rounded">View</button>
                <button @click.prevent="openEdit(asset)" class="px-3 py-1 bg-yellow-500 text-white rounded">Edit</button>
                <button @click.prevent="deleteAsset(asset)" class="px-3 py-1 bg-red-600 text-white rounded">Delete</button>
              </div>
            </div>
          </div>
          <div v-if="filteredAssets.length === 0" class="text-center text-gray-500 py-6">No assets found.</div>
        </div>
        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage === totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- View Photo Modal -->
      <div v-show="viewModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeView">✕</button>
          <h2 class="text-xl font-semibold mb-4">Asset Photo</h2>
          <div class="flex justify-center">
            <div v-if="getPhotoUrl(viewAsset)" class="max-w-full">
              <img :src="getPhotoUrl(viewAsset)" alt="asset photo" class="max-h-96 object-contain" />
            </div>
            <div v-else class="text-gray-500">No photo available.</div>
          </div>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">New Asset</h2>
          <form @submit.prevent="submitAsset" class="space-y-3">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Property No</label>
                <input v-model="form.property_no" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input v-model="form.asset_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Category</label>
              <select v-model="form.category" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required>
                <option value="">-- Select Category --</option>
                <option value="IT and Technology">IT and Technology</option>
                <option value="Office Equipment">Office Equipment</option>
                <option value="Furniture and Fixtures">Furniture and Fixtures</option>
                <option value="Laboratory and Scientific Equipment">Laboratory and Scientific Equipment</option>
                <option value="Audio-Visual and Media Equipment">Audio-Visual and Media Equipment</option>
                <option value="Machineries">Machineries</option>
                <option value="Building Fixtures">Building Fixtures</option>
              </select>
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Condition</label>
                <select v-model="form.condition" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                  <option>New</option>
                  <option>Good</option>
                  <option>Fair</option>
                  <option>Poor</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select v-model="form.status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                  <option>Active</option>
                  <option>Disposed</option>
                  <option>Under Repair</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Assigned User</label>
                <select v-model="form.assigned_user_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                  <option value="">--</option>
                  <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Building</label>
                <select v-model="form.building_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                  <option value="">--</option>
                  <option v-for="b in props.buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Room</label>
                <select v-model="form.room_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                  <option value="">--</option>
                  <option v-for="r in filteredRooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Photo</label>
              <input type="file" accept="image/*" class="mt-1 block w-full" @change="handlePhoto" />
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
