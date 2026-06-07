<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { MagnifyingGlassIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  items: Array,
  summary: Object,
})

const search = ref('')
const filterType = ref('')
const filterLowStock = ref(false)

const PER_PAGE = 20
const currentPage = ref(1)

const filtered = computed(() => {
  let list = props.items
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(i =>
      i.description.toLowerCase().includes(q) ||
      i.item_code.toLowerCase().includes(q) ||
      (i.category_name || '').toLowerCase().includes(q)
    )
  }
  if (filterType.value) {
    list = list.filter(i => i.category_type === filterType.value)
  }
  if (filterLowStock.value) {
    list = list.filter(i => i.is_low_stock)
  }
  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

const typeLabel = { consumable: 'Consumable', semi_expendable: 'Semi-Expendable', equipment: 'Equipment' }
</script>

<template>
  <Head title="Stock Card / Inventory" />
  <AdminLayout title="Stock Card / Inventory Dashboard">

    <!-- Summary cards -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Active Items</div>
        <div class="text-2xl font-bold text-slate-800 mt-1">{{ summary.total_items }}</div>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Low Stock Alerts</div>
        <div class="text-2xl font-bold mt-1" :class="summary.low_stock_count > 0 ? 'text-red-600' : 'text-slate-800'">
          {{ summary.low_stock_count }}
        </div>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Inventory Value</div>
        <div class="text-2xl font-bold text-emerald-700 mt-1">
          ₱{{ summary.total_value.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 items-center mb-4">
      <div class="relative flex-1 min-w-48">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
        <input v-model="search" @input="currentPage=1" type="text" placeholder="Search item code, description, category…"
          class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
      </div>
      <select v-model="filterType" @change="currentPage=1"
        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Types</option>
        <option value="consumable">Consumable</option>
        <option value="semi_expendable">Semi-Expendable</option>
        <option value="equipment">Equipment</option>
      </select>
      <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
        <input v-model="filterLowStock" @change="currentPage=1" type="checkbox" class="rounded" />
        Low Stock Only
      </label>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead>
          <tr class="bg-slate-50">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Item Code</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Balance</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Avg Cost</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Value</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Ledger</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-if="displayed.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-slate-400 text-sm">No items found.</td>
          </tr>
          <tr v-for="item in displayed" :key="item.id">
            <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ item.item_code }}</td>
            <td class="px-4 py-3">
              <div class="text-sm font-medium text-slate-800">{{ item.description }}</div>
              <div v-if="item.is_low_stock" class="flex items-center gap-1 text-xs text-red-600 mt-0.5">
                <ExclamationTriangleIcon class="h-3 w-3" /> Reorder point reached
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="text-sm text-slate-600">{{ item.category_name }}</div>
              <div v-if="item.account_code" class="text-xs text-slate-400">{{ item.account_code }}</div>
            </td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
            <td class="px-4 py-3 text-right text-sm font-medium"
              :class="item.is_low_stock ? 'text-red-600' : 'text-slate-800'">
              {{ item.balance_quantity.toLocaleString('en-PH', { minimumFractionDigits: 3 }) }}
            </td>
            <td class="px-4 py-3 text-right text-sm text-slate-600">
              ₱{{ item.average_unit_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
            </td>
            <td class="px-4 py-3 text-right text-sm font-medium text-emerald-700">
              ₱{{ item.total_value.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
            </td>
            <td class="px-4 py-3 text-center">
              <Link :href="route('supply.stock-card.show', item.id)"
                class="text-indigo-600 hover:text-indigo-800 text-xs font-medium underline">
                View
              </Link>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-600">
        <span>Page {{ currentPage }} of {{ totalPages }}</span>
        <div class="flex gap-2">
          <button @click="currentPage--" :disabled="currentPage <= 1"
            class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Prev</button>
          <button @click="currentPage++" :disabled="currentPage >= totalPages"
            class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
