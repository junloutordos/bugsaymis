<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
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
    <div class="space-y-5">

      <AppPageHeader title="Stock Card / Inventory Dashboard" subtitle="Track supply item balances and inventory value." />

      <!-- Summary cards -->
      <div class="grid grid-cols-3 gap-4">
        <AppCard>
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active Items</div>
          <div class="text-2xl font-bold text-slate-800 mt-1">{{ summary.total_items }}</div>
        </AppCard>
        <AppCard>
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Low Stock Alerts</div>
          <div class="text-2xl font-bold mt-1" :class="summary.low_stock_count > 0 ? 'text-danger-600' : 'text-slate-800'">
            {{ summary.low_stock_count }}
          </div>
        </AppCard>
        <AppCard>
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Inventory Value</div>
          <div class="text-2xl font-bold text-success-700 mt-1">
            ₱{{ summary.total_value.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
          </div>
        </AppCard>
      </div>

      <!-- Filters -->
      <AppFilterBar>
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
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="displayed.length === 0" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Item Code</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Description</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Category</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Unit</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Balance</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Avg Cost</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Value</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Ledger</th>
          </tr>
        </template>

        <tr v-for="item in displayed" :key="item.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ item.item_code }}</td>
          <td class="px-4 py-3">
            <div class="text-sm font-medium text-slate-800">{{ item.description }}</div>
            <div v-if="item.is_low_stock" class="flex items-center gap-1 text-xs text-danger-600 mt-0.5">
              <ExclamationTriangleIcon class="h-3 w-3" /> Reorder point reached
            </div>
          </td>
          <td class="px-4 py-3">
            <div class="text-sm text-slate-600">{{ item.category_name }}</div>
            <div v-if="item.account_code" class="text-xs text-slate-400">{{ item.account_code }}</div>
          </td>
          <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
          <td class="px-4 py-3 text-right text-sm font-medium"
            :class="item.is_low_stock ? 'text-danger-600' : 'text-slate-800'">
            {{ item.balance_quantity.toLocaleString('en-PH', { minimumFractionDigits: 3 }) }}
          </td>
          <td class="px-4 py-3 text-right text-sm text-slate-600">
            ₱{{ item.average_unit_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
          </td>
          <td class="px-4 py-3 text-right text-sm font-medium text-success-700">
            ₱{{ item.total_value.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
          </td>
          <td class="px-4 py-3 text-center">
            <Link :href="route('supply.stock-card.show', item.id)"
              class="text-indigo-600 hover:text-indigo-800 text-xs font-medium underline">
              View
            </Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="item in displayed" :key="item.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-mono text-slate-600">{{ item.item_code }}</p>
                <p class="text-sm font-medium text-slate-800">{{ item.description }}</p>
                <div v-if="item.is_low_stock" class="flex items-center gap-1 text-xs text-danger-600 mt-0.5">
                  <ExclamationTriangleIcon class="h-3 w-3" /> Reorder point reached
                </div>
              </div>
              <Link :href="route('supply.stock-card.show', item.id)"
                class="text-indigo-600 hover:text-indigo-800 text-xs font-medium underline shrink-0">
                View
              </Link>
            </div>
            <div class="text-xs text-slate-500">{{ item.category_name }}<span v-if="item.account_code"> &middot; {{ item.account_code }}</span></div>
            <div class="flex justify-between text-xs text-slate-500">
              <span :class="item.is_low_stock ? 'text-danger-600 font-medium' : ''">
                Balance: {{ item.balance_quantity.toLocaleString('en-PH', { minimumFractionDigits: 3 }) }} {{ item.unit }}
              </span>
              <span class="font-semibold text-success-700">₱{{ item.total_value.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No items found" />
        </template>

        <template #footer>
          <PaginationControl
            v-if="totalPages > 1"
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
