<template>
  <Head title="Class Records" />
  <AdminLayout title="Class Records">
    <div class="space-y-5">

      <AppPageHeader title="Class Records" subtitle="Manage grade class records per subject and section">
        <template #actions>
          <!-- Grading options editor — class-records.admin OR the scoped
               class-records.grading-options grant (AUHs) -->
          <div v-if="canManageGradingOptions" class="relative group">
            <AppButton variant="secondary">
              <Cog6ToothIcon class="h-4 w-4" /> Grading Options
            </AppButton>
            <!-- Dropdown of options to edit -->
            <div class="absolute right-0 mt-1 w-72 bg-white rounded-xl shadow-lg border border-slate-100 z-10 hidden group-hover:block">
              <div class="py-1">
                <button @click="openCreateOptionModal"
                  class="w-full text-left px-4 py-2.5 text-sm text-indigo-600 hover:bg-indigo-50 flex items-center gap-2 font-medium border-b border-slate-100">
                  <PlusIcon class="h-4 w-4" /> Add New Grading Option
                </button>
                <p class="px-4 py-2 text-xs text-slate-400 font-semibold uppercase tracking-wide">Edit existing</p>
                <div v-for="opt in managedGradingOptions" :key="opt.id"
                  class="flex items-center justify-between px-4 py-2 hover:bg-slate-50">
                  <button @click="openManageModal(opt)"
                    class="text-left text-sm text-slate-700 flex items-center gap-2 flex-1 min-w-0">
                    <span class="truncate">{{ opt.name }}</span>
                    <AppBadge color="indigo">{{ unitLabel(opt) }}</AppBadge>
                    <AppBadge v-if="!opt.is_active" color="slate">inactive</AppBadge>
                  </button>
                  <AppIconButton label="Delete grading option" variant="danger" size="sm" @click="deleteOption(opt, $event)">
                    <TrashIcon class="h-3.5 w-3.5" />
                  </AppIconButton>
                </div>
              </div>
            </div>
          </div>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" /> New Class Record
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <!-- Search -->
      <AppFilterBar>
        <input v-model="searchQuery" type="text" placeholder="Search by subject, section, or school year…"
          class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        <select v-model="statusFilter" @change="applyStatusFilter"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">Active (all)</option>
          <option value="draft">Draft</option>
          <option value="submitted">Submitted</option>
          <option value="checked">Checked</option>
          <option value="archived">Archived</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filtered.length" :skeleton-cols="isAdmin ? 8 : 7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Subject</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Year Level &amp; Section</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">School Year</th>
            <th v-if="isAdmin" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Teacher</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Grading Option</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Quarters</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="r in filtered" :key="r.id"
          class="hover:bg-indigo-50/40 cursor-pointer"
          @click="navigateTo(r)">
          <td class="px-4 py-3 font-medium text-slate-800">{{ r.subject_name }}</td>
          <td class="px-4 py-3 text-slate-600">{{ r.year_level_section }}</td>
          <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ r.school_year }}</td>
          <td v-if="isAdmin" class="px-4 py-3 text-slate-600">{{ r.teacher?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-600 text-xs">{{ r.grading_option?.name ?? '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusBadge(r.status)">
              {{ r.status === 'checked' ? 'Checked ✓' : r.status.charAt(0).toUpperCase() + r.status.slice(1) }}
            </AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <span v-for="q in [1,2,3,4]" :key="q"
                :class="['inline-flex items-center justify-center h-5 w-5 rounded-full text-[9px] font-bold', quarterDot(r, q)]">
                Q{{ q }}
              </span>
            </div>
          </td>
          <td class="px-4 py-3" @click.stop>
            <div class="flex items-center justify-end gap-2">
              <button @click="navigateTo(r)"
                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                Open <ArrowRightIcon class="h-3.5 w-3.5" />
              </button>
              <AppIconButton v-if="viewingArchived" label="Restore class record" variant="secondary" size="sm" @click="restoreRecord(r, $event)">
                <ArrowUturnLeftIcon class="h-3.5 w-3.5" />
              </AppIconButton>
              <AppIconButton v-else label="Archive class record" variant="danger" size="sm" @click="openArchive(r, $event)">
                <TrashIcon class="h-3.5 w-3.5" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="r in filtered" :key="r.id" class="p-4 space-y-2" @click="navigateTo(r)">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-medium text-slate-800 truncate">{{ r.subject_name }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ r.year_level_section }} &middot; {{ r.school_year }}</p>
                <p v-if="isAdmin" class="text-xs text-slate-400 mt-0.5">{{ r.teacher?.name ?? '—' }}</p>
              </div>
              <AppBadge :color="statusBadge(r.status)">
                {{ r.status === 'checked' ? 'Checked ✓' : r.status.charAt(0).toUpperCase() + r.status.slice(1) }}
              </AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ r.grading_option?.name ?? '—' }}</p>
            <div class="flex items-center gap-1">
              <span v-for="q in [1,2,3,4]" :key="q"
                :class="['inline-flex items-center justify-center h-5 w-5 rounded-full text-[9px] font-bold', quarterDot(r, q)]">
                Q{{ q }}
              </span>
            </div>
            <div class="flex justify-end items-center gap-2 pt-1">
              <button @click.stop="navigateTo(r)"
                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                Open <ArrowRightIcon class="h-3.5 w-3.5" />
              </button>
              <AppIconButton v-if="viewingArchived" label="Restore class record" variant="secondary" size="sm" @click="restoreRecord(r, $event)">
                <ArrowUturnLeftIcon class="h-3.5 w-3.5" />
              </AppIconButton>
              <AppIconButton v-else label="Archive class record" variant="danger" size="sm" @click="openArchive(r, $event)">
                <TrashIcon class="h-3.5 w-3.5" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No class records yet">
            <AppButton size="sm" class="mt-3" @click="openCreate">
              <PlusIcon class="h-4 w-4" /> Create your first class record
            </AppButton>
          </EmptyState>
        </template>
      </AppTable>
    </div>

    <!-- Create modal -->
    <AppModal :show="showModal" title="New Class Record"
      :subtitle="currentSchoolYear ? `SY ${currentSchoolYear}` : null"
      size="lg" body-class="px-6 py-4" @close="showModal = false">

      <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">
        {{ isAdmin ? 'Step 1 — Select teaching assignment(s)' : 'Select your teaching assignment(s)' }}
      </p>
      <p class="text-xs text-slate-400 mb-3">
        Select one or more subjects — a class record will be created for each once you choose a grading option.
      </p>

      <!-- Loading -->
      <div v-if="loadFetching" class="flex items-center gap-2 text-sm text-slate-400 py-4">
        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
        Loading assignments…
      </div>

      <!-- No assignments -->
      <div v-else-if="!myLoad.length"
           class="rounded-xl border border-warning-100 bg-warning-50 px-4 py-4 text-sm text-warning-700">
        <p class="font-medium mb-1">No teaching assignments found for SY {{ loadSyName ?? currentSchoolYear ?? '—' }}.</p>
        <p class="text-xs text-warning-700">Faculty Loading for the current school year must be completed first. Contact the Academic Affairs Office.</p>
      </div>

      <!-- Assignment list -->
      <div v-else class="space-y-1.5">
        <button v-for="la in myLoad" :key="la.load_assignment_id"
                type="button"
                :disabled="la.already_created"
                @click="pickLoad(la)"
                class="w-full text-left rounded-xl px-4 py-3 border transition"
                :class="[
                  la.already_created ? 'opacity-50 cursor-not-allowed border-slate-100' :
                  isSelected(la)
                    ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200'
                    : 'border-slate-200 hover:border-indigo-200 hover:bg-slate-50',
                ]">
          <div class="flex items-center justify-between gap-2">
            <div class="min-w-0 flex items-center gap-2.5">
              <span class="shrink-0 flex items-center justify-center h-4 w-4 rounded border"
                    :class="isSelected(la) ? 'bg-indigo-600 border-indigo-600' : 'border-slate-300'">
                <svg v-if="isSelected(la)" class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
              </span>
              <div class="min-w-0">
                <p class="font-medium text-sm text-slate-800 truncate">{{ la.subject_name }}</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ la.scope_label }}
                  <span v-if="isAdmin && la.teacher_name" class="ml-1 text-slate-400">· {{ la.teacher_name }}</span>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <AppBadge :color="la.subject_type === 'elective' ? 'amber' : 'slate'">{{ la.subject_type }}</AppBadge>
              <AppBadge v-if="la.already_created" color="green">✓ created</AppBadge>
            </div>
          </div>
        </button>
      </div>

      <p v-if="createErrors.assignment" class="text-xs text-red-500 mt-2">{{ createErrors.assignment[0] }}</p>

      <!-- Step 2: Grading option (only shown when at least one assignment selected) -->
      <div v-if="selectedLoads.length" class="mt-5 pt-4 border-t border-slate-100">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">
          Step 2 — Grading Option
        </p>

        <!-- Confirmation card(s) -->
        <div class="rounded-xl bg-indigo-50 border border-indigo-200 px-4 py-3 mb-4 text-sm space-y-2">
          <p class="font-semibold text-indigo-800">
            {{ selectedLoads.length === 1
              ? 'Creating a class record for:'
              : `Applying this grading option to ${selectedLoads.length} selected subjects:` }}
          </p>
          <div v-for="la in selectedLoads" :key="la.load_assignment_id" class="flex items-center justify-between gap-2">
            <p class="text-indigo-700 text-xs">
              <span class="font-medium">{{ la.subject_name }}</span> — {{ la.scope_label }}
              <span v-if="isAdmin && la.teacher_name" class="text-indigo-500"> · {{ la.teacher_name }}</span>
            </p>
            <button type="button" @click="pickLoad(la)" class="text-indigo-400 hover:text-indigo-600 shrink-0">
              <XMarkIcon class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>

        <select v-model="gradingOptionId"
          class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          :class="createErrors.grading_option_id ? 'border-red-400' : 'border-slate-200'">
          <option value="">— Select a grading option —</option>
          <option v-for="opt in gradingOptions" :key="opt.id" :value="opt.id">
            {{ opt.name }}{{ opt.owner_designation ? ` (${unitLabel(opt)})` : '' }}
          </option>
        </select>
        <p v-if="createErrors.grading_option_id" class="text-xs text-red-500 mt-1">{{ createErrors.grading_option_id[0] }}</p>

        <GradingOptionDetails :option="selectedGradingOption" class="mt-3" />
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="creating" :disabled="!selectedLoads.length || !gradingOptionId || !currentSchoolYear" @click="handleCreate">
          {{ creating ? 'Creating…' : (selectedLoads.length > 1 ? `Create ${selectedLoads.length} Class Records` : 'Create Class Record') }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Manage Grading Option Modal -->
    <AppModal :show="showManageModal"
      :title="managingOption ? 'Edit Grading Option' : 'New Grading Option'"
      :subtitle="managingOption ? 'Changes affect all future class records using this option.' : 'Define the name and grading categories.'"
      size="2xl" @close="showManageModal = false">

      <div class="space-y-5">
        <!-- Option meta -->
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Option Name <span class="text-red-500">*</span></label>
            <input v-model="manageOptionForm.name" type="text"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <input v-model="manageOptionForm.description" type="text"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
          </div>
          <div class="col-span-2 flex items-center gap-2">
            <input type="checkbox" v-model="manageOptionForm.is_active" id="opt-active" class="rounded text-indigo-600" />
            <label for="opt-active" class="text-sm text-slate-700 cursor-pointer">Active (visible in dropdown)</label>
          </div>

          <!-- Unit ownership: only settable at creation time -->
          <div v-if="managingOption === null" class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Unit</label>
            <select v-if="showUnitPicker" v-model="manageOptionForm.owner_designation_id"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
              <option v-if="isAdmin" :value="null">Campus-wide (all units)</option>
              <option v-for="unit in gradingOptionUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
            </select>
            <p v-else class="text-sm text-slate-600 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
              {{ manageOptionForm.owner_designation_id ? unitNameById(manageOptionForm.owner_designation_id) : 'Campus-wide (all units)' }}
            </p>
          </div>
          <div v-else class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Unit</label>
            <p class="text-sm text-slate-600 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
              {{ unitLabel(managingOption) }}
            </p>
          </div>
        </div>

        <!-- Applies to quarters -->
        <div>
          <p class="text-sm font-semibold text-slate-700 mb-2">Applies to</p>
          <div class="flex flex-wrap items-center gap-4">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
              <input type="radio" :value="true" v-model="manageOptionForm.applies_all" class="text-indigo-600" />
              All quarters
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
              <input type="radio" :value="false" v-model="manageOptionForm.applies_all" class="text-indigo-600" />
              Specific quarter(s)
            </label>
            <div v-if="!manageOptionForm.applies_all" class="flex items-center gap-2">
              <label v-for="q in [1,2,3,4]" :key="q"
                class="inline-flex items-center gap-1 text-sm text-slate-600 px-2 py-1 rounded-lg border"
                :class="manageOptionForm.quarters.includes(q) ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200'">
                <input type="checkbox" :checked="manageOptionForm.quarters.includes(q)" @change="toggleQuarter(q)" class="text-indigo-600" />
                Q{{ q }}
              </label>
            </div>
          </div>
          <p v-if="!manageOptionForm.applies_all && !manageOptionForm.quarters.length" class="text-[11px] text-amber-600 mt-1">
            Select at least one quarter, or choose "All quarters".
          </p>
        </div>

        <!-- Categories editor (with sub-categories) -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-semibold text-slate-700">Categories &amp; Sub-categories</p>
            <AppBadge :color="weightTotal === 100 ? 'green' : 'red'">
              Total: {{ weightTotal }}% {{ weightTotal === 100 ? '✓' : '≠ 100%' }}
            </AppBadge>
          </div>
          <p v-if="manageErrors.categories" class="text-xs text-red-500 mb-2">{{ manageErrors.categories[0] }}</p>
          <p class="text-[11px] text-slate-400 mb-3">
            Add sub-categories to a category to break it down; leaf categories/sub-categories carry the weight (all leaves must total 100%).
          </p>

          <div class="space-y-3">
            <div v-for="(cat, idx) in manageCategories" :key="idx" class="rounded-lg border border-slate-200 p-3">
              <div class="flex items-start gap-2">
                <input v-model="cat.name" type="text" placeholder="Category name (e.g. Written Works)"
                  class="flex-1 rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                <input v-model="cat.code" type="text" maxlength="10" placeholder="Code"
                  class="w-20 rounded border border-slate-200 px-2 py-1 text-sm uppercase focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                <template v-if="!cat.children || !cat.children.length">
                  <div class="flex items-center gap-1">
                    <input v-model.number="cat.weight" type="number" min="0.01" max="1" step="0.05"
                      class="w-16 rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                    <span class="text-xs text-slate-400">{{ Math.round((cat.weight || 0) * 100) }}%</span>
                  </div>
                  <input v-model.number="cat.max_assessments" type="number" min="1" max="20" title="Max items"
                    class="w-16 rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                </template>
                <span v-else class="text-xs text-slate-400 whitespace-nowrap px-2 py-1">
                  Group · {{ Math.round(cat.children.reduce((s, c) => s + Number(c.weight || 0), 0) * 100) }}%
                </span>
                <AppIconButton label="Remove category" variant="danger" size="sm" @click="removeCategory(idx)">
                  <TrashIcon class="h-4 w-4" />
                </AppIconButton>
              </div>

              <!-- Sub-categories -->
              <div v-if="cat.children && cat.children.length" class="mt-2 pl-4 border-l-2 border-slate-100 space-y-2">
                <div v-for="(sub, cidx) in cat.children" :key="cidx" class="flex items-center gap-2">
                  <span class="text-slate-300 text-xs">↳</span>
                  <input v-model="sub.name" type="text" placeholder="Sub-category name"
                    class="flex-1 rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                  <input v-model="sub.code" type="text" maxlength="10" placeholder="Code"
                    class="w-20 rounded border border-slate-200 px-2 py-1 text-sm uppercase focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                  <div class="flex items-center gap-1">
                    <input v-model.number="sub.weight" type="number" min="0.01" max="1" step="0.05"
                      class="w-16 rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                    <span class="text-xs text-slate-400">{{ Math.round((sub.weight || 0) * 100) }}%</span>
                  </div>
                  <input v-model.number="sub.max_assessments" type="number" min="1" max="20" title="Max items"
                    class="w-16 rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                  <AppIconButton label="Remove sub-category" variant="danger" size="sm" @click="removeSubCategory(idx, cidx)">
                    <TrashIcon class="h-4 w-4" />
                  </AppIconButton>
                </div>
              </div>

              <button type="button" @click="addSubCategory(idx)"
                class="mt-2 inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                <PlusIcon class="h-3.5 w-3.5" /> Add Sub-category
              </button>
            </div>
          </div>

          <button type="button" @click="addCategory"
            class="mt-3 inline-flex items-center gap-1.5 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
            <PlusIcon class="h-4 w-4" /> Add Category
          </button>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showManageModal = false">Cancel</AppButton>
        <AppButton :loading="manageSaving" :disabled="weightTotal !== 100 || (!manageOptionForm.applies_all && !manageOptionForm.quarters.length)" @click="saveManageOption">
          {{ manageSaving ? 'Saving…' : (managingOption ? 'Save Changes' : 'Create Option') }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Archive confirmation + PIN -->
    <DigitalSignaturePin
      :show="archiveModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      :loading="archiving"
      confirm-label="Archive Record"
      @confirm="confirmArchive"
      @cancel="archiveModal = false"
    />

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import GradingOptionDetails from './components/GradingOptionDetails.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import Swal from 'sweetalert2'
import { PlusIcon, ArrowRightIcon, Cog6ToothIcon, TrashIcon, ArrowUturnLeftIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  classRecords:      Array,
  gradingOptions:    Array,
  managedGradingOptions: { type: Array, default: () => [] },
  gradingOptionUnits:    { type: Array, default: () => [] },
  isAdmin:           { type: Boolean, default: false },
  canManageGradingOptions: { type: Boolean, default: false },
  currentSchoolYear: { type: String, default: null },
  filters:           { type: Object, default: () => ({}) },
  hasPin:            { type: Boolean, default: false },
  signatureUri:      { type: String, default: null },
})

// ── Academic unit labeling ────────────────────────────────────────────────────
function unitNameById(id) {
  return props.gradingOptionUnits.find(u => u.id === id)?.name ?? 'Campus-wide (all units)'
}
function unitLabel(opt) {
  if (!opt?.owner_designation) return 'Campus-wide'
  return opt.owner_designation.name.replace(/^Academic Unit Head\s*-\s*/i, '')
}

// ── Search ────────────────────────────────────────────────────────────────────
const searchQuery = ref('')
const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  if (!q) return props.classRecords
  return props.classRecords.filter(r =>
    r.subject_name?.toLowerCase().includes(q) ||
    r.year_level_section?.toLowerCase().includes(q) ||
    r.school_year?.toLowerCase().includes(q) ||
    r.teacher?.name?.toLowerCase().includes(q)
  )
})

// ── Status filter (active view vs archived) ────────────────────────────────────
const statusFilter = ref(props.filters?.status ?? '')

function applyStatusFilter() {
  const query = {}
  if (statusFilter.value) query.status = statusFilter.value
  router.get(route('class-records.page.index'), query, { preserveState: true, preserveScroll: true })
}

const viewingArchived = computed(() => statusFilter.value === 'archived')

// ── Status badge ──────────────────────────────────────────────────────────────
function statusBadge(status) {
  return {
    draft:     'slate',
    submitted: 'blue',
    checked:   'green',
    archived:  'amber',
  }[status] ?? 'slate'
}

// ── Archive (soft-delete) + restore ─────────────────────────────────────────────
const archiveModal = ref(false)
const archiveTarget = ref(null)
const archiving = ref(false)

function openArchive(record, event) {
  event?.stopPropagation()
  archiveTarget.value = record
  archiveModal.value = true
}

async function confirmArchive(pin) {
  if (!archiveTarget.value) return
  archiving.value = true
  try {
    await axios.delete(route('class-records.destroy', archiveTarget.value.id), { data: { pin } })
    archiveModal.value = false
    archiveTarget.value = null
    await Swal.fire({ icon: 'success', title: 'Archived', text: 'The class record was archived and can be restored.', timer: 1400, showConfirmButton: false })
    router.reload({ only: ['classRecords'] })
  } catch (err) {
    Swal.fire('Cannot Archive', err.response?.data?.errors?.pin?.[0] ?? err.response?.data?.message ?? 'Failed to archive.', 'error')
  } finally {
    archiving.value = false
  }
}

async function restoreRecord(record, event) {
  event?.stopPropagation()
  const confirmed = await confirmAction({
    title: 'Restore class record?',
    text: `"${record.subject_name}" will return to its previous status.`,
    confirmText: 'Restore',
  })
  if (!confirmed) return
  try {
    await axios.post(route('class-records.restore', record.id))
    await Swal.fire({ icon: 'success', title: 'Restored', timer: 1200, showConfirmButton: false })
    router.reload({ only: ['classRecords'] })
  } catch (err) {
    Swal.fire('Cannot Restore', err.response?.data?.message ?? 'Failed to restore.', 'error')
  }
}

// ── Quarter progress dots ──────────────────────────────────────────────────────
function quarterDot(record, q) {
  const quarter = record.quarters?.find(qt => qt.quarter === q)
  if (!quarter)          return 'bg-slate-200 text-slate-400'
  if (quarter.is_locked) return 'bg-success-500 text-white'
  return 'bg-warning-500 text-white'
}

// ── Create modal ──────────────────────────────────────────────────────────────
const showModal       = ref(false)
const creating        = ref(false)
const createErrors    = ref({})
const selectedLoads   = ref([])   // array of chosen load assignment objects (multi-select)
const gradingOptionId = ref('')

const selectedGradingOption = computed(() =>
  props.gradingOptions.find(o => o.id === gradingOptionId.value) ?? null
)

// Teaching load list
const myLoad       = ref([])
const loadFetching = ref(false)
const loadSyName   = ref(null)

async function fetchMyLoad() {
  loadFetching.value = true
  try {
    const { data } = await axios.get(route('class-records.my-teaching-load'))
    myLoad.value    = data.assignments ?? []
    loadSyName.value = data.school_year ?? null
  } catch {
    myLoad.value = []
  } finally {
    loadFetching.value = false
  }
}

function openCreate() {
  selectedLoads.value    = []
  gradingOptionId.value = ''
  createErrors.value    = {}
  showModal.value       = true
  fetchMyLoad()
}

function isSelected(assignment) {
  return selectedLoads.value.some(la => la.load_assignment_id === assignment.load_assignment_id)
}

function pickLoad(assignment) {
  if (assignment.already_created) return
  const idx = selectedLoads.value.findIndex(la => la.load_assignment_id === assignment.load_assignment_id)
  if (idx >= 0) {
    selectedLoads.value.splice(idx, 1)
  } else {
    selectedLoads.value.push(assignment)
  }
}

async function handleCreate() {
  createErrors.value = {}

  if (!selectedLoads.value.length) {
    createErrors.value = { assignment: ['Please select at least one teaching assignment.'] }
    return
  }
  if (!gradingOptionId.value) {
    createErrors.value = { grading_option_id: ['Please select a grading option.'] }
    return
  }

  creating.value = true
  try {
    const payload = {
      grading_option_id: gradingOptionId.value,
      items: selectedLoads.value.map(la => {
        const item = {
          subject_id: la.subject_id,
          section_id: la.section_id,
        }
        // Admins creating on behalf of another teacher
        if (props.isAdmin && la.teacher_id) {
          item.teacher_id = la.teacher_id
        }
        return item
      }),
    }

    const { data } = await axios.post(route('class-records.bulk-store'), payload)
    showModal.value = false

    const count = data.created?.length ?? 0
    const skippedCount = data.skipped?.length ?? 0
    let message = count === 1 ? '1 class record created.' : `${count} class records created.`
    if (skippedCount) {
      message += ` ${skippedCount} skipped (already exist).`
    }

    router.visit(route('class-records.page.index'), {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: message, timer: 2000, showConfirmButton: false })
      },
    })
  } catch (err) {
    if (err.response?.status === 422) {
      createErrors.value = err.response.data.errors ?? {}
      if (!Object.keys(createErrors.value).length) {
        Swal.fire('Error', err.response.data.message ?? 'Could not create class records.', 'error')
      }
    } else {
      Swal.fire('Error', err.response?.data?.message ?? 'Could not create class record.', 'error')
    }
  } finally {
    creating.value = false
  }
}

function navigateTo(record) {
  router.visit(route('class-records.page.show', record.id))
}

// ── Grading Option Management Modal ──────────────────────────────────────────
const showManageModal   = ref(false)
const managingOption    = ref(null)   // null = create mode, object = edit mode
const manageOptionForm  = ref({ name: '', description: '', is_active: true, owner_designation_id: null, applies_all: true, quarters: [] })
const manageCategories  = ref([])
const manageSaving      = ref(false)
const manageErrors      = ref({})

// Admins choose any unit (or campus-wide); an AUH managing more than one unit
// picks among their own; an AUH with exactly one unit gets it auto-assigned.
const showUnitPicker = computed(() => props.isAdmin || props.gradingOptionUnits.length > 1)

/** Build the nested editor model (parents with children) from flat categories. */
function buildNestedCategories(cats) {
  const list = cats ?? []
  const parents = list.filter(c => !c.parent_id).sort((a, b) => a.sort_order - b.sort_order)
  return parents.map(p => ({
    id: p.id,
    name: p.name,
    code: p.code,
    weight: Number(p.weight),
    max_assessments: p.max_assessments,
    children: list
      .filter(c => c.parent_id === p.id)
      .sort((a, b) => a.sort_order - b.sort_order)
      .map(c => ({ id: c.id, name: c.name, code: c.code, weight: Number(c.weight), max_assessments: c.max_assessments })),
  }))
}

function openManageModal(option) {
  managingOption.value   = option
  manageOptionForm.value = {
    name: option.name,
    description: option.description ?? '',
    is_active: option.is_active,
    owner_designation_id: option.owner_designation_id ?? null,
    applies_all: !(option.applicable_quarters && option.applicable_quarters.length),
    quarters: option.applicable_quarters ? [...option.applicable_quarters].map(Number) : [],
  }
  manageCategories.value = buildNestedCategories(option.categories)
  manageErrors.value     = {}
  showManageModal.value  = true
}

function openCreateOptionModal() {
  managingOption.value   = null
  manageOptionForm.value = {
    name: '',
    description: '',
    is_active: true,
    // Non-admin AUH holding exactly one unit: auto-assign it, no picker shown.
    owner_designation_id: (!props.isAdmin && props.gradingOptionUnits.length === 1)
      ? props.gradingOptionUnits[0].id
      : null,
    applies_all: true,
    quarters: [],
  }
  manageCategories.value = [{ id: null, name: '', code: '', weight: 0, max_assessments: 1, children: [] }]
  manageErrors.value     = {}
  showManageModal.value  = true
}

async function deleteOption(opt, event) {
  event.stopPropagation()
  const confirmed = await confirmDelete(`Delete "${opt.name}"? This cannot be undone. Will be blocked if any class record uses this option.`)
  if (!confirmed) return
  try {
    await axios.delete(route('grading-options.destroy', opt.id))
    await Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false })
    router.reload({ only: ['gradingOptions', 'managedGradingOptions'] })
  } catch (err) {
    Swal.fire('Cannot Delete', err.response?.data?.message ?? 'Failed to delete.', 'error')
  }
}

const weightTotal = computed(() => {
  let sum = 0
  for (const cat of manageCategories.value) {
    if (cat.children?.length) {
      for (const ch of cat.children) sum += Number(ch.weight || 0)
    } else {
      sum += Number(cat.weight || 0)
    }
  }
  return Math.round(sum * 100)
})

function addCategory() {
  manageCategories.value.push({
    id: null, name: '', code: '', weight: 0, max_assessments: 1, children: [],
  })
}

function removeCategory(idx) {
  manageCategories.value.splice(idx, 1)
}

function addSubCategory(idx) {
  const cat = manageCategories.value[idx]
  if (!cat.children) cat.children = []
  cat.children.push({ id: null, name: '', code: '', weight: 0, max_assessments: 1 })
}

function removeSubCategory(idx, cidx) {
  manageCategories.value[idx].children.splice(cidx, 1)
}

function toggleQuarter(q) {
  const set = manageOptionForm.value.quarters
  const i = set.indexOf(q)
  if (i === -1) set.push(q)
  else set.splice(i, 1)
}

async function saveManageOption() {
  manageSaving.value = true
  manageErrors.value = {}

  // Nested payload: a category with children is a parent (weight derived);
  // otherwise it is a leaf carrying weight + max items.
  const categories = manageCategories.value.map((cat, i) => {
    const base = { id: cat.id ?? null, name: cat.name, code: cat.code, sort_order: i }
    if (cat.children?.length) {
      base.children = cat.children.map((ch, j) => ({
        id: ch.id ?? null,
        name: ch.name,
        code: ch.code,
        weight: Number(ch.weight),
        max_assessments: Number(ch.max_assessments),
        sort_order: j,
      }))
    } else {
      base.weight = Number(cat.weight)
      base.max_assessments = Number(cat.max_assessments)
    }
    return base
  })

  const applicable_quarters = manageOptionForm.value.applies_all
    ? null
    : [...manageOptionForm.value.quarters].sort()

  const meta = {
    name: manageOptionForm.value.name,
    description: manageOptionForm.value.description,
    is_active: manageOptionForm.value.is_active,
    applicable_quarters,
  }

  try {
    if (managingOption.value === null) {
      await axios.post(route('grading-options.store'), {
        ...meta,
        owner_designation_id: manageOptionForm.value.owner_designation_id,
        categories,
      })
      await Swal.fire({ icon: 'success', title: 'Grading option created!', timer: 1200, showConfirmButton: false })
    } else {
      await axios.put(route('grading-options.update', managingOption.value.id), meta)
      await axios.put(route('grading-options.categories.update', managingOption.value.id), { categories })
      await Swal.fire({ icon: 'success', title: 'Grading option updated!', timer: 1200, showConfirmButton: false })
    }
    showManageModal.value = false
    router.reload({ only: ['gradingOptions', 'managedGradingOptions'] })
  } catch (err) {
    if (err.response?.status === 422) {
      manageErrors.value = err.response.data.errors ?? {}
      const msg = err.response.data.message ?? 'Please fix the errors below.'
      Swal.fire('Validation Error', msg, 'warning')
    } else {
      Swal.fire('Error', err.response?.data?.message ?? 'Failed to save.', 'error')
    }
  } finally {
    manageSaving.value = false
  }
}
</script>
