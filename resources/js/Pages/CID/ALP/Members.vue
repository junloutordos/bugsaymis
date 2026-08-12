<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { DocumentArrowDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  members: { type: Array, default: () => [] },
  schoolYears: { type: Array, default: () => [] },
  selectedSchoolYearId: [Number, String],
})

const selectYear = (schoolYearId) => router.get(route('alp.members.index'), { school_year_id: schoolYearId }, { preserveState: true })

const search = ref('')
const gradeFilter = ref('')
const sectionFilter = ref('')

const gradeOptions = computed(() => [...new Set(props.members.map(m => m.grade_level).filter(Boolean))].sort((a, b) => a - b))
const sectionOptions = computed(() => [...new Set(props.members.map(m => m.section).filter(Boolean))].sort())

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.members.filter(m => {
    if (gradeFilter.value && String(m.grade_level) !== String(gradeFilter.value)) return false
    if (sectionFilter.value && m.section !== sectionFilter.value) return false
    if (!q) return true
    return `${m.name ?? ''} ${m.alp ?? ''}`.toLowerCase().includes(q)
  })
})

const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

const TH = 'px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap'
const TD = 'px-4 py-3 text-sm text-slate-600'
</script>

<template>
  <Head title="ALP Active Members" />
  <AdminLayout title="ALP Active Members">
    <div class="space-y-5">
      <AppPageHeader
        title="Active Members"
        subtitle="All active ALP members across every program cycle."
        :breadcrumb="[{ label: 'Alternative Learning Program', href: route('alp.index') }, { label: 'Active Members' }]"
      >
        <template #actions>
          <AppSelect :model-value="selectedSchoolYearId" :show-blank="false" aria-label="School year" class="min-w-40" @update:model-value="selectYear">
            <option v-for="year in schoolYears" :key="year.id" :value="year.id">{{ year.name }}</option>
          </AppSelect>
          <AppButton as="a" :href="route('alp.members.pdf', { school_year_id: selectedSchoolYearId })" target="_blank" variant="secondary">
            <DocumentArrowDownIcon class="h-4 w-4" />
            Download PDF
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <div class="relative">
          <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input v-model="search" type="text" placeholder="Search name or ALP..."
            class="pl-9 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-64" />
        </div>
        <select v-model="gradeFilter" class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Grades</option>
          <option v-for="g in gradeOptions" :key="g" :value="g">Grade {{ g }}</option>
        </select>
        <select v-model="sectionFilter" class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Sections</option>
          <option v-for="s in sectionOptions" :key="s" :value="s">{{ s }}</option>
        </select>
      </AppFilterBar>

      <AppCard :padded="false">
        <AppTable :is-empty="displayed.length === 0" :skeleton-cols="4" :card="false">
          <template #head>
            <tr>
              <th :class="TH">Name</th>
              <th :class="TH">Grade</th>
              <th :class="TH">Section</th>
              <th :class="TH">ALP</th>
            </tr>
          </template>

          <tr v-for="(m, idx) in displayed" :key="idx" class="hover:bg-indigo-50/40">
            <td :class="TD" class="font-medium text-slate-800">{{ m.name }}</td>
            <td :class="TD">{{ m.grade_level }}</td>
            <td :class="TD">{{ m.section || '—' }}</td>
            <td :class="TD">{{ m.alp }}</td>
          </tr>

          <template #empty>
            <EmptyState title="No active members found" />
          </template>
        </AppTable>

        <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
          <p class="text-xs text-slate-500">{{ filtered.length }} member(s) — page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-1">
            <AppButton variant="secondary" size="sm" :disabled="currentPage === 1" @click="currentPage--">Prev</AppButton>
            <AppButton variant="secondary" size="sm" :disabled="currentPage === totalPages" @click="currentPage++">Next</AppButton>
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
