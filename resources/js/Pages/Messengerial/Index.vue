<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({ requests: Array });
const page = usePage();

const showModal = ref(false);
const form = useForm({ purpose: '', destination: '', reference_no: '', delivery_methods: [], messengerial_kinds: [], consignee_name: '', consignee_contact: '', consignee_email: '' });

const openModal = (req = null) => {
  if (req) {
    form.reset();
    form.purpose = req.purpose ?? '';
    form.destination = req.destination ?? '';
    form.reference_no = req.reference_no ?? '';
    form.delivery_methods = req.delivery_methods ?? [];
    form.messengerial_kinds = req.messengerial_kinds ?? [];
    form.consignee_name = req.consignee_name ?? '';
    form.consignee_contact = req.consignee_contact ?? '';
    form.consignee_email = req.consignee_email ?? '';
  } else {
    form.reset();
  }
  showModal.value = true;
};
const closeModal = () => { showModal.value = false; form.reset(); };

const submit = () => {
  form.post(route('messengerial.store'), {
    onSuccess: () => { closeModal(); },
    onError: (errors) => {
      console.warn('Validation errors:', errors);
    },
    onFinish: () => {
      // no-op; form.processing is handled by useForm
    }
  });
};

const destroy = (req) => {
  if (!confirm('Delete this messengerial request?')) return;
  import('@inertiajs/vue3').then(({ router }) => {
    router.delete(route('messengerial.destroy', req.id));
  });
};
</script>

<template>
  <Head title="Messengerial" />
  <AdminLayout title="Messengerial">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Messengerial Requests</h1>
        <button @click.prevent="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">+ New Request</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
            <tr>
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Requestor</th>
              <th class="px-4 py-3 text-left">Unit</th>
              <th class="px-4 py-3 text-left">Purpose</th>
              <th class="px-4 py-3 text-left">Destination</th>
              <th class="px-4 py-3 text-left">Reference No.</th>
              <th class="px-4 py-3 text-left">Consignee</th>
              <th class="px-4 py-3 text-left">Contact</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Kind(s)</th>
              <th class="px-4 py-3 text-left">Delivery</th>
              <th class="px-4 py-3 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="r in props.requests" :key="r.id">
              <td class="px-4 py-3">{{ r.id }}</td>
              <td class="px-4 py-3">{{ r.requestor }}</td>
              <td class="px-4 py-3">{{ r.unit ?? '—' }}</td>
              <td class="px-4 py-3">{{ r.purpose ?? '—' }}</td>
              <td class="px-4 py-3">{{ r.destination ?? '—' }}</td>
              <td class="px-4 py-3">{{ r.reference_no ?? '—' }}</td>
              <td class="px-4 py-3">{{ r.consignee_name ?? '—' }}</td>
              <td class="px-4 py-3">{{ r.consignee_contact ?? '—' }}</td>
              <td class="px-4 py-3">{{ r.status }}</td>
              <td class="px-4 py-3">{{ (Array.isArray(r.messengerial_kinds) ? r.messengerial_kinds.join(', ') : (r.messengerial_kinds || '—')) }}</td>
              <td class="px-4 py-3">{{ (Array.isArray(r.delivery_methods) ? r.delivery_methods.join(', ') : (r.delivery_methods || '—')) }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center gap-2 justify-center">
                  <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="openModal(r)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit"><PencilSquareIcon class="w-5 h-5"/></button>
                  <button v-if="page.props.auth?.user?.role?.name === 'Administrator'" @click.prevent="destroy(r)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete"><TrashIcon class="w-5 h-5"/></button>
                </div>
              </td>
            </tr>
            <tr v-if="props.requests.length === 0">
              <td colspan="9" class="px-4 py-6 text-center text-gray-500">No messengerial requests found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">New Messengerial Request</h2>
          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Purpose</label>
              <input v-model="form.purpose" type="text" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.purpose" class="text-red-600 text-sm mt-1">{{ form.errors.purpose }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Destination</label>
              <input v-model="form.destination" type="text" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.destination" class="text-red-600 text-sm mt-1">{{ form.errors.destination }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Reference No.</label>
              <input v-model="form.reference_no" type="text" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.reference_no" class="text-red-600 text-sm mt-1">{{ form.errors.reference_no }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Delivery Method(s)</label>
              <select v-model="form.delivery_methods" multiple class="mt-1 block w-full rounded border-gray-300">
                <option value="Thru Email">Thru Email</option>
                <option value="In-person Delivery">In-person Delivery</option>
                <option value="Courier Services">Courier Services</option>
              </select>
              <p v-if="form.errors.delivery_methods" class="text-red-600 text-sm mt-1">{{ form.errors.delivery_methods }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Kind(s) of Messengerial</label>
              <select v-model="form.messengerial_kinds" multiple class="mt-1 block w-full rounded border-gray-300">
                <option value="Communication Letter">Communication Letter</option>
                <option value="Package">Package</option>
              </select>
              <p v-if="form.errors.messengerial_kinds" class="text-red-600 text-sm mt-1">{{ form.errors.messengerial_kinds }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Consignee Name</label>
              <input v-model="form.consignee_name" type="text" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.consignee_name" class="text-red-600 text-sm mt-1">{{ form.errors.consignee_name }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Consignee Contact No.</label>
              <input v-model="form.consignee_contact" type="text" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.consignee_contact" class="text-red-600 text-sm mt-1">{{ form.errors.consignee_contact }}</p>
            </div>

            <div v-if="form.delivery_methods.includes('Thru Email')">
              <label class="block text-sm font-medium text-gray-700">Consignee Email</label>
              <input v-model="form.consignee_email" type="email" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.consignee_email" class="text-red-600 text-sm mt-1">{{ form.errors.consignee_email }}</p>
            </div>

            <div class="flex gap-2">
              <button :disabled="form.processing" type="submit" class="bg-blue-600 text-white px-4 py-2 rounded disabled:opacity-60">{{ form.processing ? 'Submitting...' : 'Submit' }}</button>
              <button @click.prevent="closeModal" type="button" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
