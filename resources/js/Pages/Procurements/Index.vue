<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref, reactive, computed, watch } from "vue";
import { PencilSquareIcon, TrashIcon, PrinterIcon, CheckIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({ procurements: Array, currentUser: Object, units: Array });
const procurementsList = ref(props.procurements || []);
const units = computed(() => props.units || []);

const isAdmin = computed(() => (props.currentUser && props.currentUser.role === 'Administrator'));

// search + client-side pagination (matches VehicleRequests Index behavior)
const searchQuery = ref('');
const currentPage = ref(1);
const perPage = 10;

const filtered = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  const results = (procurementsList.value || []).filter(p => {
    return (p.pr_no || '').toString().toLowerCase().includes(q)
      || ((p.requester && p.requester.name) || p.requested_by || '').toString().toLowerCase().includes(q)
      || (p.purpose || '').toString().toLowerCase().includes(q)
  })
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil((procurementsList.value || []).filter(p => {
  const q = searchQuery.value.trim().toLowerCase();
  return (p.pr_no || '').toString().toLowerCase().includes(q)
      || ((p.requester && p.requester.name) || p.requested_by || '').toString().toLowerCase().includes(q)
      || (p.purpose || '').toString().toLowerCase().includes(q)
}).length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })

// Add modal state + simple form (UI only)
const showModal = ref(false)
const today = new Date().toISOString().slice(0,10)
const form = useForm({ pr_date: today, purpose: '' })
const editMode = ref(false)
const editId = ref(null)

const banner = ref(null);

const setBanner = (type, message, ms = 5000) => {
  banner.value = { type, message };
  if (ms > 0) setTimeout(() => { banner.value = null; }, ms);
}

// client-side validation state
const fieldErrors = reactive({ pr_date: '', purpose: '' });

const validateField = (field) => {
  switch (field) {
    case 'pr_date':
      fieldErrors.pr_date = form.pr_date && form.pr_date >= today ? '' : 'PR Date is required and cannot be in the past';
      break;
    case 'purpose':
      fieldErrors.purpose = form.purpose && String(form.purpose).trim() ? '' : 'Purpose is required';
      break;
  }
}

const validateAll = () => {
  validateField('pr_date');
  validateField('purpose');
  return !Object.values(fieldErrors).some(v => v && v.length > 0);
}

const formatDate = (d) => {
  if (!d) return '';
  const dt = new Date(d);
  if (Number.isNaN(dt.getTime())) return d;
  return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}
const openModal = (p = null) => {
  showModal.value = true
  if (p) {
    editMode.value = true
    editId.value = p.id
    form.reset();
    form.pr_date = p.pr_date || today
    form.purpose = p.purpose || ''
  } else {
    editMode.value = false
    editId.value = null
    form.reset();
    form.pr_date = today
  }
}

const closeModal = () => { showModal.value = false; editMode.value = false; editId.value = null; form.reset(); }

const submit = () => {
  // Post to server to create procurement
  // Validate fields before submit
  if (!validateAll()) {
    setBanner('error', 'Please correct the highlighted fields');
    return;
  }
  try {
    if (editMode.value && editId.value) {
      const url = (() => { try { return route('procurements.update', editId.value) } catch (e) { return `/procurements/${editId.value}` } })();
      form.put(url, {
        onSuccess: () => {
          closeModal();
          Swal.fire({ icon: 'success', title: 'Purchase request updated', timer: 1200, showConfirmButton: false }).then(() => {
            window.location.reload();
          });
        },
        onError: (errs) => { console.error('Failed to update procurement', errs); }
      })
    } else {
      const url = (() => { try { return route('procurements.store') } catch (e) { return '/procurements' } })();
      form.post(url, {
        onSuccess: () => {
          closeModal();
          Swal.fire({ icon: 'success', title: 'Purchase request created', timer: 1200, showConfirmButton: false }).then(() => {
            window.location.reload();
          });
        },
        onError: (errs) => { console.error('Failed to create procurement', errs); }
      })
    }
  } catch (e) {
    console.error('Submit failed', e);
    alert('Unexpected error. See console.');
  }
}

const destroy = (p) => {
  Swal.fire({ title: 'Delete this purchase request?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel' }).then((res) => {
    if (!res.isConfirmed) return;
    import('@inertiajs/vue3').then(({ router }) => {
      let destroyUrl;
      try { destroyUrl = route('procurements.destroy', p.id) } catch (e) { destroyUrl = `/procurements/${p.id}` }
      router.delete(destroyUrl, {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false }).then(() => { window.location.reload() }) },
        onError: () => { Swal.fire({ icon: 'error', title: 'Failed to delete' }) }
      })
    })
  })
}

const sendForApproval = async (p) => {
  if (!p || !p.id) return;
  try {
    const url = (() => { try { return route('procurements.sendForApproval', p.id) } catch (e) { return `/procurements/${p.id}/send-for-approval` } })();
    const tokenEl = document.querySelector('meta[name="csrf-token"]');
    const csrf = tokenEl ? tokenEl.getAttribute('content') : null;
    const resp = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {})
      },
      body: JSON.stringify({})
    });
    if (!resp.ok) {
      const txt = await resp.text();
      console.error('Send for approval failed', resp.status, txt);
      Swal.fire({ icon: 'error', title: 'Failed to send for approval' });
      return;
    }
    Swal.fire({ icon: 'success', title: 'Sent for approval', timer: 1200, showConfirmButton: false });
  } catch (e) {
    console.error(e);
    Swal.fire({ icon: 'error', title: 'Unexpected error' });
  }
}

// Items modal state
const showItemsModal = ref(false)
const currentProcurement = ref(null)
const itemsForm = useForm({ items: [] })

const newItemRow = () => ({ ppmp_line_item_no: '', unit: '', description: '', quantity: 1, unit_cost: '' })
const newItem = ref(newItemRow())

const openItemsModal = (p) => {
  currentProcurement.value = p
  showItemsModal.value = true
  // initialize empty items list and fresh newItem
  itemsForm.reset()
  // populate with existing items if present
  itemsForm.items = (p && p.items && Array.isArray(p.items)) ? p.items.map(it => ({
    ppmp_line_item_no: it.ppmp_line_item_no || '',
    unit: it.unit || '',
    description: it.description || '',
    quantity: it.quantity || 1,
    unit_cost: it.unit_cost || '',
    id: it.id || null
  })) : []
  newItem.value = newItemRow()
}

const closeItemsModal = () => { showItemsModal.value = false; currentProcurement.value = null; itemsForm.reset(); newItem.value = newItemRow(); window.location.reload(); }

const addItemRow = async () => {
  // client-side validation
  if (!newItem.value.unit || !newItem.value.description || !newItem.value.quantity || newItem.value.quantity <= 0 || newItem.value.unit_cost === '' || newItem.value.unit_cost === null) {
    Swal.fire({ icon: 'error', title: 'Please provide unit, description, quantity and unit cost' })
    return;
  }
  if (!currentProcurement.value) return;
  try {
    const url = (() => { try { return route('procurements.items.store', currentProcurement.value.id) } catch (e) { return `/procurements/${currentProcurement.value.id}/items` } })();
    const payload = { ...newItem.value };
    const tokenEl = document.querySelector('meta[name="csrf-token"]');
    const csrf = tokenEl ? tokenEl.getAttribute('content') : null;
    const resp = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {})
      },
      body: JSON.stringify(payload)
    });
    if (!resp.ok) {
      const errText = await resp.text();
      console.error('Add item failed', resp.status, errText);
      throw new Error('Add item failed');
    }
    const json = await resp.json();
    // server returns created item under `item` or `created` array
    const created = json.item || (json.created && json.created[0]) || payload;
    itemsForm.items.push(created);
    newItem.value = newItemRow();
  } catch (e) {
    console.error(e)
    Swal.fire({ icon: 'error', title: 'Unexpected error' })
  }
}

const removeItemRow = async (i) => {
  const it = itemsForm.items[i]
  if (!it) return;
  if (!currentProcurement.value) return;
  // If item has id, delete on server; otherwise just remove locally
  if (it.id) {
    import('@inertiajs/vue3').then(({ router }) => {
      const url = (() => { try { return route('procurements.items.destroy', [currentProcurement.value.id, it.id]) } catch (e) { return `/procurements/${currentProcurement.value.id}/items/${it.id}` } })();
      router.delete(url, {
        onSuccess: () => {
          itemsForm.items.splice(i,1)
        },
        onError: (errs) => {
          console.error('Failed to delete item', errs)
          Swal.fire({ icon: 'error', title: 'Failed to delete item' })
        }
      })
    })
  } else {
    itemsForm.items.splice(i,1)
  }
}

// submitItems removed — items are saved immediately on Add and removed immediately on Delete

</script>

<template>
  <Head title="Purchase Requests" />
  <AdminLayout>
    <template #title>Purchase Requests</template>

    <div class="p-4">
      <div class="mb-4 flex items-center justify-end">
        <button @click="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">New Purchase Request</button>
      </div>

      <h2 class="text-xl font-semibold mb-3">Purchase Request</h2>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by PR No, requester, purpose"
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <table class="table-fixed w-full border border-gray-200">
          <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
            <tr>
              <th class="px-4 py-2 text-left">PR No</th>
              <th class="px-4 py-2 text-left">Date</th>
              <th class="px-4 py-2 text-left">Requested By</th>
              <th class="px-4 py-2 text-left">Purpose</th>
              <th class="px-4 py-2 text-left">Items</th>
              <th class="px-4 py-2 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="p in filtered" :key="p.id">
              <td class="px-4 py-2">{{ p.pr_no }}</td>
              <td class="px-4 py-2">{{ formatDate(p.pr_date) }}</td>
              <td class="px-4 py-2">{{ isAdmin ? ((p.requester && p.requester.name) || p.requested_by) : p.requested_by }}</td>
              <td class="px-4 py-2">{{ p.purpose }}</td>
              <td class="px-4 py-2">{{ (p.items || []).length }}</td>
              <td class="px-4 py-2 text-center whitespace-normal break-words">
                <div class="flex items-center gap-2 justify-center">
                  <button @click.prevent="openModal(p)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit">
                    <PencilSquareIcon class="w-5 h-5" />
                  </button>
                  <button @click.prevent="openItemsModal(p)" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700" title="Manage Items">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a1 1 0 000 2h12a1 1 0 100-2H4zM3 7a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm1 4a1 1 0 000 2h9a1 1 0 100-2H4z"/></svg>
                  </button>
                  <button @click.prevent="destroy(p)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
                    <TrashIcon class="w-5 h-5" />
                  </button>
                  <button @click.prevent="sendForApproval(p)" class="p-2 rounded-full bg-yellow-100 hover:bg-yellow-200 text-yellow-700" title="Send for Approval">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a1 1 0 00-1 1v6H6a1 1 0 000 2h3v6a1 1 0 001 1h0a1 1 0 001-1v-6h3a1 1 0 100-2h-3V3a1 1 0 00-1-1z"/></svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="(filtered || []).length === 0">
              <td class="p-4 text-center" colspan="6">No purchase requests found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-3 flex items-center justify-center">
        <div class="flex items-center gap-3">
          <button @click="currentPage = Math.max(1, currentPage - 1)" class="px-3 py-1 border rounded">Prev</button>
          <div class="text-sm">Page {{ currentPage }} / {{ totalPages }}</div>
          <button @click="currentPage = Math.min(totalPages, currentPage + 1)" class="px-3 py-1 border rounded">Next</button>
        </div>
      </div>

      <!-- Modal (basic) -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white w-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-md p-4 sm:p-6 relative overflow-auto">
          <h3 class="text-lg font-semibold mb-2">{{ editMode ? 'Edit Purchase Request' : 'New Purchase Request' }}</h3>
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-1">
              <label class="block text-sm">PR Date</label>
              <input type="date" v-model="form.pr_date" :min="today" @change="() => validateField('pr_date')" :class="['border px-2 py-1 w-full', fieldErrors.pr_date ? 'rounded ring-1 ring-red-200 border-red-500' : 'rounded']" />
              <p v-if="fieldErrors.pr_date" class="text-red-600 text-sm mt-1">{{ fieldErrors.pr_date }}</p>
            </div>
            <div class="col-span-2">
              <label class="block text-sm">Purpose</label>
              <textarea v-model="form.purpose" @input="() => validateField('purpose')" :class="['border px-2 py-1 w-full', fieldErrors.purpose ? 'rounded ring-1 ring-red-200 border-red-500' : 'rounded']" rows="3"></textarea>
              <p v-if="fieldErrors.purpose" class="text-red-600 text-sm mt-1">{{ fieldErrors.purpose }}</p>
            </div>
          </div>

          <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 mr-2 border rounded" @click="closeModal">Cancel</button>
            <button class="px-3 py-1 bg-blue-600 text-white rounded" @click="submit">Save</button>
          </div>
        </div>
      </div>

      <!-- Items Modal -->
      <div v-if="showItemsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white w-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-4xl p-4 sm:p-6 relative overflow-auto">
          <h3 class="text-lg font-semibold mb-2">Add Items to {{ currentProcurement ? currentProcurement.pr_no : '' }}</h3>
          <div>
            <div class="grid grid-cols-12 gap-2 items-end mb-3">
              <div class="col-span-2">
                <label class="text-sm">PPMP No</label>
                <input v-model="newItem.ppmp_line_item_no" class="border px-2 py-1 w-full rounded" />
              </div>
                          <div class="col-span-2">
                            <label class="text-sm">Unit</label>
                            <select v-model="newItem.unit" class="border px-2 py-1 w-full rounded">
                              <option value="">Select unit</option>
                              <option v-for="u in units" :key="u.id" :value="u.name">{{ u.name }}</option>
                            </select>
                          </div>
              <div class="col-span-5">
                <label class="text-sm">Description</label>
                <input v-model="newItem.description" class="border px-2 py-1 w-full rounded" />
              </div>
              <div class="col-span-1">
                <label class="text-sm">Qty</label>
                <input type="number" v-model.number="newItem.quantity" min="1" class="border px-2 py-1 w-full rounded" />
              </div>
              <div class="col-span-1">
                <label class="text-sm">Unit Cost</label>
                <input type="number" step="0.01" v-model="newItem.unit_cost" class="border px-2 py-1 w-full rounded" />
              </div>
              <div class="col-span-1">
                <button class="px-3 py-1 bg-gray-100 rounded" @click="addItemRow">Add Item</button>
              </div>
            </div>

            <table class="w-full table-auto border-collapse border border-gray-200 text-sm">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-2 py-2 border">PPMP No</th>
                  <th class="px-2 py-2 border">Unit</th>
                  <th class="px-2 py-2 border">Description</th>
                  <th class="px-2 py-2 border">Qty</th>
                  <th class="px-2 py-2 border">Unit Cost</th>
                  <th class="px-2 py-2 border">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(it, idx) in itemsForm.items" :key="idx" class="odd:bg-white even:bg-gray-50">
                  <td class="px-2 py-2 border">{{ it.ppmp_line_item_no }}</td>
                  <td class="px-2 py-2 border">{{ it.unit }}</td>
                  <td class="px-2 py-2 border">{{ it.description }}</td>
                  <td class="px-2 py-2 border">{{ it.quantity }}</td>
                  <td class="px-2 py-2 border">{{ it.unit_cost }}</td>
                  <td class="px-2 py-2 border text-center">
                    <button class="px-2 py-1 bg-red-100 text-red-700 rounded" @click="removeItemRow(idx)">Remove</button>
                  </td>
                </tr>
                <tr v-if="!(itemsForm.items || []).length">
                  <td class="p-4 text-center" colspan="6">No items. Use the inputs above to add items.</td>
                </tr>
              </tbody>
            </table>
          </div>

            <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 mr-2 border rounded" @click="closeItemsModal">Close</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
