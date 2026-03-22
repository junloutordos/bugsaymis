<script setup>
import { Head, useForm, router as inertiaRouter } from "@inertiajs/vue3";
import { ref, reactive, computed, watch } from "vue";
import { PencilSquareIcon, TrashIcon, PrinterIcon, CheckIcon, XMarkIcon, ListBulletIcon, PaperAirplaneIcon } from "@heroicons/vue/24/outline";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { useSubmit } from "@/Composables/useSubmit";

const props = defineProps({ procurements: Array, currentUser: Object, units: Array });
const { isSubmitting: isDeleting, submit: submitDelete } = useSubmit();
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
    let destroyUrl;
    try { destroyUrl = route('procurements.destroy', p.id) } catch (e) { destroyUrl = `/procurements/${p.id}` }
    submitDelete.delete(destroyUrl, {
      onSuccess: () => { Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: () => { Swal.fire({ icon: 'error', title: 'Failed to delete' }) }
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

    <div class="space-y-5">

      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-bold text-slate-800">Purchase Requests</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage and track all purchase requests</p>
        </div>
        <button
          @click="openModal()"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
        >
          New Purchase Request
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">

        <!-- Card header / search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by PR No, requester, purpose…"
            class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PR No</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requested By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Purpose</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Items</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="p in filtered" :key="p.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700 font-mono">{{ p.pr_no }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ formatDate(p.pr_date) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ isAdmin ? ((p.requester && p.requester.name) || p.requested_by) : p.requested_by }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ p.purpose }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">
                    {{ (p.items || []).length }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <button @click.prevent="openModal(p)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click.prevent="openItemsModal(p)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Manage Items">
                      <ListBulletIcon class="w-4 h-4" />
                    </button>
                    <button @click.prevent="destroy(p)" :disabled="isDeleting" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                    <button @click.prevent="sendForApproval(p)" class="p-1.5 rounded-lg hover:bg-amber-50 text-slate-500 hover:text-amber-600 transition-colors" title="Send for Approval">
                      <PaperAirplaneIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="(filtered || []).length === 0">
                <td class="px-4 py-10 text-center text-sm text-slate-400" colspan="6">No purchase requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-2">
            <button
              @click="currentPage = Math.max(1, currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >Prev</button>
            <button
              @click="currentPage = Math.min(totalPages, currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >Next</button>
          </div>
        </div>
      </div>

      <!-- Create / Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white w-full sm:max-w-md rounded-2xl shadow-xl flex flex-col">

          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">{{ editMode ? 'Edit Purchase Request' : 'New Purchase Request' }}</h3>
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">PR Date</label>
              <input
                type="date"
                v-model="form.pr_date"
                :min="today"
                @change="() => validateField('pr_date')"
                :class="[
                  'w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500',
                  fieldErrors.pr_date ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400'
                ]"
              />
              <p v-if="fieldErrors.pr_date" class="text-red-600 text-xs mt-1">{{ fieldErrors.pr_date }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
              <textarea
                v-model="form.purpose"
                @input="() => validateField('purpose')"
                rows="3"
                :class="[
                  'w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500',
                  fieldErrors.purpose ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400'
                ]"
              ></textarea>
              <p v-if="fieldErrors.purpose" class="text-red-600 text-xs mt-1">{{ fieldErrors.purpose }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeModal" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50 transition-colors">Cancel</button>
            <button
              :disabled="form.processing"
              @click="submit"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60"
            >
              {{ form.processing ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Items Modal -->
      <div v-if="showItemsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white w-full sm:max-w-4xl rounded-2xl shadow-xl flex flex-col max-h-[90vh]">

          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">
              Items — <span class="text-indigo-600 font-mono">{{ currentProcurement ? currentProcurement.pr_no : '' }}</span>
            </h3>
            <button @click="closeItemsModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="overflow-y-auto px-6 py-5 space-y-4">

            <!-- Add item row -->
            <div class="bg-slate-50 rounded-xl p-4">
              <p class="text-xs font-medium text-slate-500 mb-3 uppercase tracking-wide">Add Item</p>
              <div class="grid grid-cols-12 gap-2 items-end">
                <div class="col-span-2">
                  <label class="block text-xs font-medium text-slate-600 mb-1">PPMP No</label>
                  <input
                    v-model="newItem.ppmp_line_item_no"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                  />
                </div>
                <div class="col-span-2">
                  <label class="block text-xs font-medium text-slate-600 mb-1">Unit</label>
                  <select
                    v-model="newItem.unit"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                  >
                    <option value="">Select unit</option>
                    <option v-for="u in units" :key="u.id" :value="u.name">{{ u.name }}</option>
                  </select>
                </div>
                <div class="col-span-4">
                  <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                  <input
                    v-model="newItem.description"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                  />
                </div>
                <div class="col-span-1">
                  <label class="block text-xs font-medium text-slate-600 mb-1">Qty</label>
                  <input
                    type="number"
                    v-model.number="newItem.quantity"
                    min="1"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                  />
                </div>
                <div class="col-span-2">
                  <label class="block text-xs font-medium text-slate-600 mb-1">Unit Cost</label>
                  <input
                    type="number"
                    step="0.01"
                    v-model="newItem.unit_cost"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                  />
                </div>
                <div class="col-span-1">
                  <button
                    @click="addItemRow"
                    class="w-full inline-flex items-center justify-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                  >
                    Add
                  </button>
                </div>
              </div>
            </div>

            <!-- Items table -->
            <div class="overflow-x-auto">
              <table class="min-w-full">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PPMP No</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Unit</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Qty</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Unit Cost</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(it, idx) in itemsForm.items" :key="idx" class="hover:bg-slate-50/60">
                    <td class="px-4 py-3 text-sm text-slate-700">{{ it.ppmp_line_item_no }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ it.unit }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ it.description }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ it.quantity }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ it.unit_cost }}</td>
                    <td class="px-4 py-3">
                      <button
                        @click="removeItemRow(idx)"
                        class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors"
                        title="Remove"
                      >
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!(itemsForm.items || []).length">
                    <td class="px-4 py-10 text-center text-sm text-slate-400" colspan="6">No items. Use the form above to add items.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
            <button
              @click="closeItemsModal"
              class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
            >Close</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
