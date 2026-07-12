<script setup>
import { ref, computed } from "vue";
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from "@/Components/AppPageHeader.vue";
import AppButton from "@/Components/AppButton.vue";
import AppBadge from "@/Components/AppBadge.vue";
import AppTable from "@/Components/AppTable.vue";
import EmptyState from "@/Components/EmptyState.vue";
import { PlusIcon, CheckCircleIcon, PencilSquareIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
  types: {
    type: Array,
    default: () => [],
  },
});

// ─── Flash ───────────────────────────────────────────────────────────────────
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

// ─── Category filter ─────────────────────────────────────────────────────────
const categoryFilter = ref("all");
const categoryTabs = [
  { key: "all",       label: "All" },
  { key: "allowance", label: "Allowance" },
  { key: "deduction", label: "Deduction" },
  { key: "bonus",     label: "Bonus" },
  { key: "other",     label: "Other" },
];

const filteredTypes = computed(() => {
  if (categoryFilter.value === "all") return props.types;
  return props.types.filter((t) => t.category === categoryFilter.value);
});

// ─── Category badge ───────────────────────────────────────────────────────────
function categoryBadgeColor(category) {
  const map = { allowance: "green", deduction: "red", bonus: "blue", other: "slate" };
  return map[category] ?? "slate";
}

// ─── Modal ────────────────────────────────────────────────────────────────────
const showModal   = ref(false);
const editingType = ref(null);

const form = useForm({
  code:             "",
  name:             "",
  description:      "",
  category:         "allowance",
  is_fixed_amount:  false,
  default_amount:   "",
  is_taxable:       false,
  is_mandatory:     false,
  is_active:        true,
  sort_order:       "",
});

function openCreate() {
  editingType.value = null;
  form.reset();
  form.category  = "allowance";
  form.is_active = true;
  showModal.value = true;
}

function openEdit(type) {
  editingType.value = type;
  form.code             = type.code;
  form.name             = type.name;
  form.description      = type.description ?? "";
  form.category         = type.category;
  form.is_fixed_amount  = type.is_fixed_amount;
  form.default_amount   = type.default_amount ?? "";
  form.is_taxable       = type.is_taxable;
  form.is_mandatory     = type.is_mandatory;
  form.is_active        = type.is_active;
  form.sort_order       = type.sort_order ?? "";
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingType.value = null;
  form.reset();
  form.clearErrors();
}

function submitForm() {
  if (editingType.value) {
    form.put(route("payroll.allowances.update", editingType.value.id), {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post(route("payroll.allowances.store"), {
      onSuccess: () => closeModal(),
    });
  }
}

// ─── Toggle ───────────────────────────────────────────────────────────────────
function toggleActive(type) {
  router.patch(route("payroll.allowances.toggle", type.id), {}, {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head title="Allowance Types" />
  <AdminLayout title="Allowance Types">

    <div class="space-y-5">

      <AppPageHeader title="Allowance &amp; Deduction Types" subtitle="Manage allowances, deductions, bonuses, and other payroll components.">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            Add New
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash message -->
      <transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div
          v-if="flashSuccess"
          class="flex items-center gap-2 rounded-lg bg-success-50 border border-success-100 text-success-700 px-4 py-3 text-sm"
        >
          <CheckCircleIcon class="h-4 w-4 shrink-0" />
          {{ flashSuccess }}
        </div>
      </transition>

      <!-- Card -->
      <div class="rounded-xl border border-slate-100 bg-white shadow-sm">

        <!-- Category filter tabs -->
        <div class="border-b border-slate-100 px-4">
          <nav class="flex space-x-1 -mb-px overflow-x-auto">
            <button
              v-for="tab in categoryTabs"
              :key="tab.key"
              @click="categoryFilter = tab.key"
              :class="[
                'px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap',
                categoryFilter === tab.key
                  ? 'border-indigo-600 text-indigo-600'
                  : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
              ]"
            >
              {{ tab.label }}
              <span
                :class="[
                  'ml-1.5 rounded-full px-1.5 py-0.5 text-xs font-semibold',
                  categoryFilter === tab.key
                    ? 'bg-indigo-100 text-indigo-700'
                    : 'bg-slate-100 text-slate-500',
                ]"
              >
                {{ tab.key === 'all' ? props.types.length : props.types.filter(t => t.category === tab.key).length }}
              </span>
            </button>
          </nav>
        </div>

        <!-- Table -->
        <AppTable :is-empty="!filteredTypes.length" :skeleton-cols="9" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Code</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Category</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Default Amount</th>
              <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Fixed?</th>
              <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Taxable?</th>
              <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Mandatory?</th>
              <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Active</th>
              <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
            </tr>
          </template>

          <tr
            v-for="type in filteredTypes"
            :key="type.id"
            class="hover:bg-indigo-50/40 transition"
          >
            <!-- Code -->
            <td class="px-4 py-3 text-sm font-mono font-medium text-slate-700">
              {{ type.code }}
            </td>
            <!-- Name -->
            <td class="px-4 py-3">
              <div class="text-sm font-medium text-slate-800">{{ type.name }}</div>
              <div v-if="type.description" class="text-xs text-slate-400 truncate max-w-xs">{{ type.description }}</div>
            </td>
            <!-- Category badge -->
            <td class="px-4 py-3">
              <AppBadge :color="categoryBadgeColor(type.category)" class="capitalize">{{ type.category }}</AppBadge>
            </td>
            <!-- Default amount -->
            <td class="px-4 py-3 text-right text-sm text-slate-600">
              <span v-if="type.default_amount != null">
                {{ Number(type.default_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
              </span>
              <span v-else class="text-slate-300">—</span>
            </td>
            <!-- Fixed -->
            <td class="px-4 py-3 text-center">
              <span v-if="type.is_fixed_amount" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              </span>
              <span v-else class="text-slate-300 text-xs">—</span>
            </td>
            <!-- Taxable -->
            <td class="px-4 py-3 text-center">
              <span v-if="type.is_taxable" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              </span>
              <span v-else class="text-slate-300 text-xs">—</span>
            </td>
            <!-- Mandatory -->
            <td class="px-4 py-3 text-center">
              <span v-if="type.is_mandatory" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              </span>
              <span v-else class="text-slate-300 text-xs">—</span>
            </td>
            <!-- Active toggle -->
            <td class="px-4 py-3 text-center">
              <button
                @click="toggleActive(type)"
                :class="[
                  'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1',
                  type.is_active ? 'bg-indigo-600' : 'bg-slate-200',
                ]"
                :title="type.is_active ? 'Click to deactivate' : 'Click to activate'"
              >
                <span
                  :class="[
                    'inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                    type.is_active ? 'translate-x-4' : 'translate-x-0',
                  ]"
                />
              </button>
            </td>
            <!-- Actions -->
            <td class="px-4 py-3 text-center">
              <AppButton size="sm" variant="ghost" @click="openEdit(type)">
                <PencilSquareIcon class="h-3.5 w-3.5" />
                Edit
              </AppButton>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="type in filteredTypes" :key="type.id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-mono text-xs text-slate-500">{{ type.code }}</p>
                  <p class="font-medium text-slate-800">{{ type.name }}</p>
                  <p v-if="type.description" class="text-xs text-slate-400">{{ type.description }}</p>
                </div>
                <AppBadge :color="categoryBadgeColor(type.category)" class="capitalize">{{ type.category }}</AppBadge>
              </div>
              <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <span>
                  Default:
                  <span v-if="type.default_amount != null">{{ Number(type.default_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                  <span v-else>—</span>
                </span>
                <span>Fixed: {{ type.is_fixed_amount ? 'Yes' : 'No' }}</span>
                <span>Taxable: {{ type.is_taxable ? 'Yes' : 'No' }}</span>
                <span>Mandatory: {{ type.is_mandatory ? 'Yes' : 'No' }}</span>
              </div>
              <div class="flex items-center justify-between pt-1">
                <button
                  @click="toggleActive(type)"
                  :class="[
                    'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1',
                    type.is_active ? 'bg-indigo-600' : 'bg-slate-200',
                  ]"
                  :title="type.is_active ? 'Click to deactivate' : 'Click to activate'"
                >
                  <span
                    :class="[
                      'inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                      type.is_active ? 'translate-x-4' : 'translate-x-0',
                    ]"
                  />
                </button>
                <AppButton size="sm" variant="ghost" @click="openEdit(type)">
                  <PencilSquareIcon class="h-3.5 w-3.5" />
                  Edit
                </AppButton>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No allowance types found" subtitle="No allowance types found for the selected filter." />
          </template>
        </AppTable>
      </div>
    </div>

    <!-- ─── Modal ──────────────────────────────────────────────────────────── -->
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        @click.self="closeModal"
      >
        <transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="showModal"
            class="w-full max-w-lg rounded-xl bg-white shadow-xl border border-slate-100 flex flex-col max-h-[90vh]"
          >
            <!-- Modal header -->
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
              <h2 class="text-base font-semibold text-slate-800">
                {{ editingType ? 'Edit Allowance Type' : 'Add Allowance Type' }}
              </h2>
              <button
                @click="closeModal"
                class="rounded-md p-1 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Modal body -->
            <form @submit.prevent="submitForm" class="overflow-y-auto px-6 py-5 space-y-4">

              <!-- Code + Name (2-col) -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Code <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.code"
                    type="text"
                    maxlength="20"
                    placeholder="e.g. PERA"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': form.errors.code }"
                  />
                  <p v-if="form.errors.code" class="mt-1 text-xs text-red-500">{{ form.errors.code }}</p>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.name"
                    type="text"
                    maxlength="100"
                    placeholder="Full name"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': form.errors.name }"
                  />
                  <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                </div>
              </div>

              <!-- Description -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea
                  v-model="form.description"
                  rows="2"
                  maxlength="500"
                  placeholder="Optional description"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-none"
                  :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': form.errors.description }"
                />
                <p v-if="form.errors.description" class="mt-1 text-xs text-red-500">{{ form.errors.description }}</p>
              </div>

              <!-- Category + Sort Order (2-col) -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Category <span class="text-red-500">*</span></label>
                  <select
                    v-model="form.category"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white"
                    :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': form.errors.category }"
                  >
                    <option value="allowance">Allowance</option>
                    <option value="deduction">Deduction</option>
                    <option value="bonus">Bonus</option>
                    <option value="other">Other</option>
                  </select>
                  <p v-if="form.errors.category" class="mt-1 text-xs text-red-500">{{ form.errors.category }}</p>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Sort Order</label>
                  <input
                    v-model.number="form.sort_order"
                    type="number"
                    placeholder="e.g. 10"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': form.errors.sort_order }"
                  />
                  <p v-if="form.errors.sort_order" class="mt-1 text-xs text-red-500">{{ form.errors.sort_order }}</p>
                </div>
              </div>

              <!-- is_fixed_amount checkbox + Default Amount -->
              <div class="rounded-lg bg-slate-50 p-3 space-y-3 border border-slate-100">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                  <input
                    v-model="form.is_fixed_amount"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                  />
                  <span class="text-sm text-slate-700 font-medium">Fixed Amount</span>
                  <span class="text-xs text-slate-400">(uses a fixed monetary value)</span>
                </label>
                <transition
                  enter-active-class="transition ease-out duration-150"
                  enter-from-class="opacity-0 -translate-y-1"
                  enter-to-class="opacity-100 translate-y-0"
                  leave-active-class="transition ease-in duration-100"
                  leave-from-class="opacity-100 translate-y-0"
                  leave-to-class="opacity-0 -translate-y-1"
                >
                  <div v-if="form.is_fixed_amount">
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                      Default Amount <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="form.default_amount"
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="0.00"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                      :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': form.errors.default_amount }"
                    />
                    <p v-if="form.errors.default_amount" class="mt-1 text-xs text-red-500">{{ form.errors.default_amount }}</p>
                  </div>
                </transition>
              </div>

              <!-- Checkbox flags (3-col) -->
              <div class="grid grid-cols-3 gap-3">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                  <input
                    v-model="form.is_taxable"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                  />
                  <span class="text-sm text-slate-700">Taxable</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer select-none">
                  <input
                    v-model="form.is_mandatory"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                  />
                  <span class="text-sm text-slate-700">Mandatory</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer select-none">
                  <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                  />
                  <span class="text-sm text-slate-700">Active</span>
                </label>
              </div>

              <!-- Modal footer -->
              <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <AppButton type="button" variant="secondary" @click="closeModal">
                  Cancel
                </AppButton>
                <AppButton type="submit" :loading="form.processing">
                  {{ editingType ? 'Save Changes' : 'Create' }}
                </AppButton>
              </div>
            </form>
          </div>
        </transition>
      </div>
    </transition>

  </AdminLayout>
</template>
