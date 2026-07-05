<script setup>
import { Head, usePage } from "@inertiajs/vue3"
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, watch } from 'vue'
import { PencilSquareIcon, TrashIcon, EyeIcon, PlusIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import { useSubmit } from "@/Composables/useSubmit"
import { storageUrl } from "@/Composables/useStorage.js"
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
  assets: Array,
  buildings: Array,
  rooms: Array,
  users: Array,
})

const { isSubmitting, submit } = useSubmit()

const assets = ref(props.assets || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

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
    submit.post(`/assets/${editingId.value}`, fd, {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Asset updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errors).flat().join('\n') }) }
    })
  } else {
    submit.post('/assets', fd, {
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
    submit.delete(`/assets/${asset.id}`, {
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
  if (p.startsWith('assets/')) return storageUrl(p)
  return storageUrl('assets/' + p)
}

const conditionBadge = (condition) => {
  if (condition === 'New')  return 'green'
  if (condition === 'Good') return 'blue'
  if (condition === 'Fair') return 'amber'
  if (condition === 'Poor') return 'red'
  return 'slate'
}

const statusBadgeColor = (status) => {
  const map = { Active: 'green', Disposed: 'red', 'Under Repair': 'amber' }
  return map[status] ?? 'slate'
}
</script>

<template>
  <Head title="Assets" />
  <AdminLayout title="Assets">
    <div class="space-y-5">

      <AppPageHeader title="Assets" subtitle="Manage asset inventory and assignments.">
        <template #actions>
          <AppButton @click="openModal">
            <PlusIcon class="h-4 w-4" /> New Asset
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by name, property no, category…"
          class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredAssets.length" :skeleton-cols="9">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Property No</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Category</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Condition</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Assigned To</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Location</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="asset in filteredAssets" :key="asset.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-500">{{ asset.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 font-mono">{{ asset.property_no }}</td>
          <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ asset.asset_name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ asset.category }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="conditionBadge(asset.condition)">{{ asset.condition }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="statusBadgeColor(asset.status)">{{ asset.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ asset.assigned_user?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ asset.building?.name ?? asset.room?.name ?? '—' }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton label="View asset photo" @click="openView(asset)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit asset" @click="openEdit(asset)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete asset" variant="danger" @click="deleteAsset(asset)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="asset in filteredAssets" :key="asset.id" class="p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate">{{ asset.asset_name }}</p>
                <p class="text-xs text-slate-500 font-mono mt-0.5">{{ asset.property_no }}</p>
                <div class="flex flex-wrap gap-1.5 mt-2">
                  <AppBadge :color="conditionBadge(asset.condition)">{{ asset.condition }}</AppBadge>
                  <AppBadge :color="statusBadgeColor(asset.status)">{{ asset.status }}</AppBadge>
                </div>
                <p class="text-xs text-slate-500 mt-1.5">{{ asset.category }}</p>
                <p class="text-xs text-slate-500">Assigned: {{ asset.assigned_user?.name ?? '—' }}</p>
                <p class="text-xs text-slate-500">Location: {{ asset.building?.name ?? asset.room?.name ?? '—' }}</p>
              </div>
              <div class="flex flex-col items-end gap-1.5 shrink-0">
                <AppIconButton label="View asset photo" @click="openView(asset)">
                  <EyeIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="Edit asset" @click="openEdit(asset)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="Delete asset" variant="danger" @click="deleteAsset(asset)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No assets found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

      <!-- View Photo Modal -->
      <AppModal :show="viewModal" title="Asset Photo" size="lg" @close="closeView">
        <div class="flex justify-center">
          <div v-if="getPhotoUrl(viewAsset)" class="max-w-full">
            <img :src="getPhotoUrl(viewAsset)" alt="asset photo" class="max-h-96 object-contain rounded-lg" />
          </div>
          <div v-else class="text-sm text-slate-400 py-8">No photo available.</div>
        </div>
      </AppModal>

      <!-- Add / Edit Asset Modal -->
      <AppModal
        :show="showModal"
        :title="editingId ? 'Edit Asset' : 'New Asset'"
        size="2xl"
        @close="closeModal"
      >
        <form @submit.prevent="submitAsset" class="space-y-4">

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Property No</label>
              <input
                v-model="form.property_no"
                type="text"
                required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
              <input
                v-model="form.asset_name"
                type="text"
                required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
            <select
              v-model="form.category"
              required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
            >
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
              <label class="block text-xs font-medium text-slate-600 mb-1">Condition</label>
              <select
                v-model="form.condition"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
              >
                <option>New</option>
                <option>Good</option>
                <option>Fair</option>
                <option>Poor</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
              <select
                v-model="form.status"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
              >
                <option>Active</option>
                <option>Disposed</option>
                <option>Under Repair</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Assigned User</label>
              <select
                v-model="form.assigned_user_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
              >
                <option value="">--</option>
                <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Building</label>
              <select
                v-model="form.building_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
              >
                <option value="">--</option>
                <option v-for="b in props.buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Room</label>
              <select
                v-model="form.room_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
              >
                <option value="">--</option>
                <option v-for="r in filteredRooms" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Photo</label>
            <input
              type="file"
              accept="image/*"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
              @change="handlePhoto"
            />
          </div>

        </form>

        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton @click="submitAsset">Save</AppButton>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>
