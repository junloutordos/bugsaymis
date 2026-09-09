<template>
  <Head title="Committees" />
  <AdminLayout title="Committees">
    <div class="space-y-5">

      <AppPageHeader title="Committees" subtitle="Committee catalog, per-term assignments, and ratings.">
        <template #actions>
          <AppButton v-if="canManage && activeTab === 'assignments'" @click="openForm()">
            <PlusIcon class="h-4 w-4" /> Assign Committee
          </AppButton>
          <AppButton v-if="activeTab === 'catalog'" @click="openCatalogModal('create')">
            <PlusIcon class="w-4 h-4" /> New Committee
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <AppTabs v-model="activeTab" :tabs="pageTabs">
        <template #tab-assignments>
          <div class="space-y-5">
            <!-- Filters + Search -->
            <AppFilterBar>
              <div class="w-56">
                <AppInput v-model="search" type="text" placeholder="Search faculty or committee…" />
              </div>
              <select v-model="filters.term_id" @change="applyFilters"
                class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option v-for="t in terms" :key="t.id" :value="t.id">
                  {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
                </option>
              </select>
              <select v-model="filters.faculty_id" @change="applyFilters"
                class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option :value="null">All Faculty</option>
                <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </AppFilterBar>

            <!-- Table -->
            <AppTable :is-empty="displayed.length === 0" :skeleton-cols="6">
              <template #head>
                <tr>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Faculty</th>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Committee</th>
                  <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Role</th>
                  <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Units</th>
                  <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                  <th class="px-4 py-3"></th>
                </tr>
              </template>

              <tr v-for="a in displayed" :key="a.id" class="hover:bg-slate-50/50">
                <td class="px-4 py-3 font-medium text-slate-800">{{ a.faculty?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                  <p v-if="getParentName(a)" class="text-xs text-indigo-500 font-medium">
                    {{ getParentName(a) }} ›
                  </p>
                  <p class="text-slate-800">{{ a.committee_name }}</p>
                  <p v-if="a.committee?.code" class="text-xs text-slate-400 font-mono">{{ a.committee.code }}</p>
                </td>
                <td class="px-4 py-3 text-center">
                  <AppBadge :color="roleBadge(a.role)">
                    <StarIcon v-if="a.is_chairperson" class="h-3 w-3" />
                    {{ roleLabel(a.role) }}
                  </AppBadge>
                </td>
                <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ a.load_units }}</td>
                <td class="px-4 py-3 text-center">
                  <AppBadge :color="statusBadge(a.status)">{{ a.status }}</AppBadge>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <AppButton v-if="a.committee_id" as="link" variant="ghost" size="sm" title="View detail"
                      :href="route('pm-committees.show', a.committee_id)">
                      <ArrowRightIcon class="h-4 w-4" />
                    </AppButton>
                    <AppIconButton label="Edit" @click="openForm(a)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                    <AppIconButton label="Remove" variant="danger" @click="remove(a)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                  </div>
                </td>
              </tr>

              <template #mobileCard>
                <div v-for="a in displayed" :key="a.id" class="p-4 space-y-2">
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <p class="font-medium text-slate-800">{{ a.faculty?.name ?? '—' }}</p>
                      <p v-if="getParentName(a)" class="text-xs text-indigo-500 font-medium">{{ getParentName(a) }} ›</p>
                      <p class="text-sm text-slate-700">{{ a.committee_name }}</p>
                    </div>
                    <AppBadge :color="statusBadge(a.status)">{{ a.status }}</AppBadge>
                  </div>
                  <div class="flex items-center gap-2">
                    <AppBadge :color="roleBadge(a.role)">
                      <StarIcon v-if="a.is_chairperson" class="h-3 w-3" />
                      {{ roleLabel(a.role) }}
                    </AppBadge>
                    <span class="text-xs text-slate-500">{{ a.load_units }} unit(s)</span>
                  </div>
                  <div class="flex items-center gap-1 pt-1">
                    <AppButton v-if="a.committee_id" as="link" size="sm"
                      :href="route('pm-committees.show', a.committee_id)">View</AppButton>
                    <AppIconButton label="Edit" @click="openForm(a)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                    <AppIconButton label="Remove" variant="danger" @click="remove(a)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                  </div>
                </div>
              </template>

              <template #empty>
                <EmptyState title="No committee assignments found" :icon="UserGroupIcon" />
              </template>

              <template #footer>
                <PaginationControl
                  :current-page="page"
                  :total-pages="totalPages"
                  :total="filtered.length"
                  @prev="page--"
                  @next="page++"
                  @page="page = $event"
                />
              </template>
            </AppTable>
          </div>
        </template>

        <template #tab-catalog>
          <div class="space-y-5">
            <AppFilterBar>
              <AppInput v-model="catalogSearch" placeholder="Search committees..." class="w-full sm:w-72" />
              <select v-model="fyFilter" @change="applyCatalogFilters"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option v-for="y in fiscalYears" :key="y" :value="String(y)">FY {{ y }}</option>
                <option value="all">All years</option>
              </select>
            </AppFilterBar>

            <AppTable :is-empty="paginatedCatalog.length === 0" :skeleton-cols="6">
              <template #head>
                <tr>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Name</th>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Structure</th>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Head</th>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active This Term</th>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Plans</th>
                  <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
              </template>

              <template v-for="committee in paginatedCatalog" :key="committee.id">
                <tr class="hover:bg-indigo-50/40">
                  <td class="px-4 py-3 font-medium text-slate-800">{{ committee.name }}</td>
                  <td class="px-4 py-3">
                    <AppBadge :color="structureBadge(committee)">
                      {{ committee.sub_committees?.length ? `Main (${committee.sub_committees.length} sub)` : 'Simple' }}
                    </AppBadge>
                  </td>
                  <td class="px-4 py-3 text-slate-700">{{ committee.head?.name ?? "—" }}</td>
                  <td class="px-4 py-3 text-slate-700">{{ committee.active_assignment_count ?? 0 }}</td>
                  <td class="px-4 py-3 text-slate-700">{{ committee.work_distribution_plans?.length ?? 0 }}</td>
                  <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                      <Link :href="route('pm-committees.show', committee.id)" title="View Performance"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                        <ArrowRightIcon class="w-4 h-4" />
                      </Link>
                      <AppIconButton v-if="canManage" label="Edit" @click="openCatalogModal('edit', committee)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
                      <AppIconButton v-if="canManage" label="Delete" variant="danger" @click="deleteCatalogCommittee(committee)"><TrashIcon class="w-4 h-4" /></AppIconButton>
                    </div>
                  </td>
                </tr>
                <tr v-for="sub in committee.sub_committees" :key="'sub-' + sub.id"
                  class="bg-slate-50/40 hover:bg-slate-50">
                  <td class="px-4 py-2 pl-10 text-slate-600">
                    <span class="text-slate-400 mr-1">└</span>
                    {{ sub.name }}
                    <span class="ml-1 text-xs italic text-slate-400">Sub-committee</span>
                  </td>
                  <td class="px-4 py-2"></td>
                  <td class="px-4 py-2 text-slate-600 text-xs">{{ sub.head?.name ?? "—" }}</td>
                  <td class="px-4 py-2 text-slate-600 text-xs">{{ sub.active_assignment_count ?? 0 }}</td>
                  <td class="px-4 py-2 text-slate-400 text-xs">—</td>
                  <td class="px-4 py-2 text-center">
                    <Link :href="route('pm-committees.show', sub.id)"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors mx-auto" title="View Performance">
                      <ArrowRightIcon class="w-4 h-4" />
                    </Link>
                  </td>
                </tr>
              </template>

              <template #empty>
                <EmptyState title="No committees found" />
              </template>

              <template #footer>
                <PaginationControl
                  :current-page="catalogPage"
                  :total-pages="totalCatalogPages"
                  @prev="catalogPage--"
                  @next="catalogPage++"
                  @page="catalogPage = $event"
                />
              </template>
            </AppTable>
          </div>
        </template>
      </AppTabs>

    </div>

    <!-- Create / Edit Assignment Modal -->
    <AppModal :show="modal" :title="`${form.id ? 'Edit' : 'Assign'} Committee`" size="lg" @close="modal = false">
      <div class="grid grid-cols-2 gap-3">
        <!-- Faculty + term (create only) -->
        <template v-if="!form.id">
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Faculty *</label>
            <select v-model="form.user_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select faculty...</option>
              <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term *</label>
            <select v-model="form.academic_term_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select term...</option>
              <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">School Year *</label>
            <select v-model="form.school_year_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select SY...</option>
              <option v-for="t in terms" :key="'sy-' + t.id" :value="t.id">{{ t.label }}</option>
            </select>
          </div>
        </template>

        <!-- Level 1: Top-level committee picker. Committees themselves are
             created exclusively via the Catalog tab's "New Committee" form
             (the original Performance Management creation flow) — this
             modal only assigns people to a committee that already exists
             in the catalog, so there is no "custom / not in catalog"
             escape hatch here. -->
        <div class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Committee *</label>
          <select v-model="selectedParentId" @change="onParentCommitteeChange" required
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null" disabled>— Select a committee —</option>
            <option v-for="c in committees" :key="c.id" :value="c.id">
              {{ c.name }}{{ c.sub_committees?.length ? ' (Main)' : '' }}{{ c.code ? ' (' + c.code + ')' : '' }}
            </option>
          </select>
          <p v-if="!committees.length" class="text-xs text-slate-400 mt-1">
            No committees in the catalog yet — create one first from the Committee Catalog tab.
          </p>
        </div>

        <!-- Level 2: Sub-committee picker (only when parent has sub-committees) -->
        <div v-if="selectedParent?.sub_committees?.length" class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Sub-committee *</label>
          <select v-model="form.committee_id" @change="onSubCommitteeChange"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">— Select sub-committee —</option>
            <option v-for="s in selectedParent.sub_committees" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>

        <div class="col-span-2">
          <AppInput v-model="form.committee_name" label="Committee Name" disabled />
        </div>

        <AppSelect :model-value="form.role" label="Role" required :show-blank="false"
          @update:model-value="v => { form.role = v; onRoleChange() }">
          <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
        </AppSelect>

        <AppInput v-model.number="form.load_units" type="number" step="0.25" min="0" max="5" label="Load Units" required />

        <div v-if="form.id" class="col-span-2">
          <AppSelect v-model="form.status" label="Status" :show-blank="false">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </AppSelect>
        </div>

        <div class="col-span-2">
          <AppTextarea v-model="form.remarks" label="Remarks" :rows="2" />
        </div>

        <!-- Tagged WDP Plans with search -->
        <div class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Tagged Work Distribution Plans</label>
          <AppInput v-model="planSearch" type="text" placeholder="Search plans..." />
          <div class="mt-2 border border-slate-200 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 text-sm">
            <label v-for="p in filteredPlans" :key="p.id" class="flex items-start gap-2 cursor-pointer hover:bg-slate-50 px-1 py-0.5 rounded">
              <input type="checkbox" :checked="form.plan_ids.includes(p.id)" @change="togglePlan(p.id)"
                class="mt-0.5 rounded border-slate-300 text-indigo-600" />
              <span class="text-slate-700 leading-snug">{{ p.success_indicator }}
                <span v-if="p.rated_by" class="text-slate-400 text-xs">({{ p.rated_by }})</span>
              </span>
            </label>
            <p v-if="!filteredPlans.length" class="text-slate-400 text-xs px-1">
              {{ planSearch ? 'No plans match your search.' : 'No plans available.' }}
            </p>
          </div>
          <p class="text-xs text-slate-400 mt-1">{{ form.plan_ids.length }} plan(s) selected</p>
        </div>

        <!-- This member's own linked WDPs (edit only — needs a persisted assignment id) -->
        <div v-if="form.id" class="col-span-2 border-t border-slate-100 pt-3">
          <label class="block text-xs font-medium text-slate-600 mb-1">
            This Member's Own Linked Work Distribution Plans (IPCR)
          </label>
          <p class="text-xs text-slate-400 mb-1">
            {{ form.load_units > 0 ? 'This assignment carries units and defaults to a Core Function on IPCR.' : 'This assignment has no load and defaults to a Support Function on IPCR.' }}
            Select specific plans to link explicitly to this member; leave blank to use the automatic default.
          </p>
          <AppInput v-model="ownPlanSearch" type="text" placeholder="Search plans..." />
          <div class="mt-2 border border-slate-200 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 text-sm">
            <label v-for="p in filteredOwnPlans" :key="p.id" class="flex items-start gap-2 cursor-pointer hover:bg-slate-50 px-1 py-0.5 rounded">
              <input type="checkbox" :checked="ownPlanIds.includes(p.id)" @change="toggleOwnPlan(p.id)"
                class="mt-0.5 rounded border-slate-300 text-indigo-600" />
              <span class="text-slate-700 leading-snug">{{ p.success_indicator }}
                <span v-if="p.rated_by" class="text-slate-400 text-xs">({{ p.rated_by }})</span>
              </span>
            </label>
            <p v-if="!filteredOwnPlans.length" class="text-slate-400 text-xs px-1">
              {{ ownPlanSearch ? 'No plans match your search.' : 'No plans available.' }}
            </p>
          </div>
          <p class="text-xs text-slate-400 mt-1">{{ ownPlanIds.length }} plan(s) selected for this member</p>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" :disabled="!canSaveAssignment" @click="save">{{ form.id ? 'Update' : 'Save' }}</AppButton>
      </template>
    </AppModal>

    <!-- Create / Edit Catalog Committee Modal -->
    <AppModal :show="showCatalogModal" :title="catalogModalMode === 'create' ? 'New Committee' : 'Edit Committee'" size="2xl" @close="closeCatalogModal">
      <form @submit.prevent="submitCatalogCommittee" class="space-y-4">
        <AppInput v-model="catalogForm.name" label="Name" required />

        <AppSelect v-model="catalogForm.head_id" :label="catalogForm.has_subcommittees ? 'Main Chairperson' : 'Committee Head'" placeholder="— None —">
          <option v-for="u in users" :key="u.id" :value="u.id">
            {{ u.name }}<span v-if="u.position"> ({{ u.position }})</span>
          </option>
        </AppSelect>

        <AppTextarea v-model="catalogForm.description" label="Description" :rows="2" />

        <AppSelect v-model="catalogForm.fiscal_year" label="Fiscal Year">
          <option :value="null">All years (unscoped)</option>
          <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
        </AppSelect>

        <!-- Structure Toggle -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Committee Structure</label>
          <div class="flex gap-2">
            <AppButton type="button" size="sm" :variant="!catalogForm.has_subcommittees ? 'primary' : 'secondary'" @click="catalogForm.has_subcommittees = false">Simple Committee</AppButton>
            <AppButton type="button" size="sm" :variant="catalogForm.has_subcommittees ? 'primary' : 'secondary'" @click="catalogForm.has_subcommittees = true">Main Committee (with Sub-committees)</AppButton>
          </div>
        </div>

        <!-- WDP Plans (always at main committee level) -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Tagged Work Distribution Plans</label>
          <AppInput v-model="catalogPlanSearch" placeholder="Search plans..." class="mb-2" />
          <div class="border border-slate-200 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 text-sm">
            <div v-for="p in filteredCatalogPlans" :key="p.id" class="flex items-start gap-2">
              <input type="checkbox" :value="p.id" :checked="catalogForm.plan_ids.includes(p.id)"
                @change="toggleCatalogPlan(p.id)" class="mt-0.5 rounded border-slate-300" />
              <span class="text-slate-700">{{ p.success_indicator }}
                <span v-if="p.rated_by" class="text-slate-400 text-xs">({{ p.rated_by }})</span>
              </span>
            </div>
            <p v-if="!filteredCatalogPlans.length" class="text-slate-400 text-xs px-1">
              {{ catalogPlanSearch ? 'No plans match your search.' : 'No plans available.' }}
            </p>
          </div>
          <p class="text-xs text-slate-400 mt-1">{{ catalogForm.plan_ids.length }} plan(s) selected</p>
        </div>

        <!-- Simple: Members -->
        <div v-if="!catalogForm.has_subcommittees">
          <label class="block text-xs font-medium text-slate-600 mb-1">Members</label>
          <AppInput v-model="catalogMemberSearch" placeholder="Search members..." class="mb-2" />
          <div class="border border-slate-200 rounded-lg p-2 max-h-52 overflow-y-auto space-y-2 text-sm">
            <div v-for="u in filteredCatalogUsers" :key="u.id" class="flex items-start gap-2">
              <input type="checkbox" :value="u.id" :checked="catalogForm.member_ids.includes(u.id)"
                @change="toggleCatalogMember(u.id)" class="mt-1 rounded border-slate-300" />
              <div class="flex-1">
                <span class="text-slate-700">{{ u.name }}<span v-if="u.position" class="text-slate-400"> ({{ u.position }})</span></span>
                <input v-if="catalogForm.member_ids.includes(u.id)" v-model="catalogForm.member_tasks[u.id]"
                  type="text" placeholder="Task / Role..."
                  class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>
            <p v-if="!filteredCatalogUsers.length" class="text-slate-400 text-xs px-1">No members match.</p>
          </div>
          <p class="text-xs text-slate-400 mt-1">{{ catalogForm.member_ids.length }} member(s) selected</p>
        </div>

        <!-- Main: Sub-committees -->
        <div v-else>
          <div class="flex items-center justify-between mb-2">
            <label class="block text-xs font-medium text-slate-600">Sub-committees</label>
            <button type="button" @click="addCatalogSubCommittee"
              class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
              <PlusIcon class="w-3 h-3" /> Add Sub-committee
            </button>
          </div>
          <p v-if="catalogForm.sub_committees.length === 0" class="text-xs text-slate-400 border border-dashed border-slate-200 rounded-lg p-4 text-center">
            No sub-committees yet. Click "Add Sub-committee" to create one.
          </p>
          <div v-for="(sub, idx) in catalogForm.sub_committees" :key="idx"
            class="border border-slate-200 rounded-lg p-4 space-y-3 mb-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-600">Sub-committee {{ idx + 1 }}</span>
              <button type="button" @click="removeCatalogSubCommittee(idx)"
                class="text-xs text-danger-600 hover:text-danger-700">Remove</button>
            </div>
            <AppInput v-model="sub.name" label="Name" required />
            <AppSelect v-model="sub.head_id" label="Head" placeholder="— None —">
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </AppSelect>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Members</label>
              <AppInput v-model="sub.memberSearch" placeholder="Search members..." class="mb-2" />
              <div class="border border-slate-100 rounded-lg p-2 max-h-36 overflow-y-auto space-y-1 text-sm">
                <div v-for="u in filteredCatalogSubUsers(sub)" :key="u.id" class="flex items-start gap-2">
                  <input type="checkbox" :checked="sub.member_ids.includes(u.id)"
                    @change="toggleCatalogSubMember(idx, u.id)" class="mt-1 rounded border-slate-300" />
                  <div class="flex-1">
                    <span class="text-slate-700 text-xs">{{ u.name }}</span>
                    <input v-if="sub.member_ids.includes(u.id)" v-model="sub.member_tasks[u.id]"
                      type="text" placeholder="Task..."
                      class="mt-1 w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                  </div>
                </div>
              </div>
              <p class="text-xs text-slate-400 mt-1">{{ sub.member_ids.length }} member(s)</p>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="closeCatalogModal">Cancel</AppButton>
          <AppButton type="submit" :loading="catalogIsSubmitting" :disabled="catalogIsSubmitting">{{ catalogIsSubmitting ? 'Saving…' : 'Save' }}</AppButton>
        </div>
      </form>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppTabs from '@/Components/AppTabs.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { useSubmit } from '@/Composables/useSubmit'
import {
  ArrowRightIcon, CheckCircleIcon, PencilSquareIcon,
  PencilIcon, PlusIcon, StarIcon, TrashIcon, UserGroupIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  assignments: { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  committees:  { type: Array,  default: () => [] },
  catalog:     { type: Array,  default: () => [] },
  plans:       { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
  users:       { type: Array,  default: () => [] },
  authUser:    { type: Object, default: null },
  canManage:   { type: Boolean, default: false },
  fiscalYears: { type: Array, default: () => [] },
  selectedFiscalYear: { type: [String, Number], default: "" },
  currentFiscalYear: { type: Number, default: null },
})

// ── Tabs ─────────────────────────────────────────────────────────────────
// Non-managers never see the campus-wide Assignments admin table (the
// backend sends it empty for them) — only the Catalog tab, scoped to their
// own committees, is relevant.
const activeTab = ref(props.canManage ? 'assignments' : 'catalog')
const pageTabs = computed(() => props.canManage
  ? [{ key: 'assignments', label: 'Assignments' }, { key: 'catalog', label: 'Committee Catalog' }]
  : [{ key: 'catalog', label: 'Committee Catalog' }])

const PER_PAGE = 15
const roles = [
  { value: 'member',      label: 'Member' },
  { value: 'secretary',   label: 'Secretary' },
  { value: 'co_chair',    label: 'Co-Chairperson' },
  { value: 'chairperson', label: 'Chairperson' },
]

// ── Assignments tab: filters + search + pagination ────────────────────────
const search = ref('')
const page   = ref(1)

const filters = reactive({
  term_id:    props.filters.term_id    ?? props.currentTerm?.id ?? null,
  faculty_id: props.filters.faculty_id ?? null,
})

watch(search, () => { page.value = 1 })

function applyFilters() {
  router.get(route('pm-committees.index'), filters, { preserveState: true })
}

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return props.assignments
  return props.assignments.filter(a =>
    a.faculty?.name?.toLowerCase().includes(q) ||
    a.committee_name?.toLowerCase().includes(q)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const s = (page.value - 1) * PER_PAGE
  return filtered.value.slice(s, s + PER_PAGE)
})

function getParentName(assignment) {
  const parentId = assignment.committee?.parent_committee_id
  if (!parentId) return null
  return props.committees.find(c => c.id === parentId)?.name ?? null
}

// ── Assignments tab: WDP plan search ──────────────────────────────────────
const planSearch = ref('')
const filteredPlans = computed(() => {
  if (!planSearch.value) return props.plans
  const q = planSearch.value.toLowerCase()
  return props.plans.filter(p => p.success_indicator.toLowerCase().includes(q))
})

// ── Assignments tab: committee cascading picker ───────────────────────────
const selectedParentId = ref(null)
const selectedParent = computed(() => props.committees.find(c => c.id === selectedParentId.value) ?? null)

function findCommitteeById(id) {
  if (!id) return null
  const top = props.committees.find(c => c.id === id)
  if (top) return top
  for (const parent of props.committees) {
    const sub = parent.sub_committees?.find(s => s.id === id)
    if (sub) return sub
  }
  return null
}

// ── Assignments tab: modal form ────────────────────────────────────────────
const modal = ref(false)
const form  = useForm({
  id: null, user_id: null, school_year_id: null, academic_term_id: null,
  committee_id: null, committee_name: '', role: 'member',
  load_units: 0.5, status: 'active', remarks: '', plan_ids: [],
})

const ownPlanIds = ref([])
const ownPlanSearch = ref('')
const filteredOwnPlans = computed(() => {
  if (!ownPlanSearch.value) return props.plans
  const q = ownPlanSearch.value.toLowerCase()
  return props.plans.filter(p => p.success_indicator.toLowerCase().includes(q))
})
function toggleOwnPlan(id) {
  const idx = ownPlanIds.value.indexOf(id)
  if (idx === -1) ownPlanIds.value.push(id)
  else ownPlanIds.value.splice(idx, 1)
}

function openForm(a = null) {
  planSearch.value = ''
  ownPlanSearch.value = ''
  if (a) {
    const parentId = a.committee?.parent_committee_id ?? a.committee?.id ?? null
    selectedParentId.value = parentId

    const committee = findCommitteeById(a.committee_id)
    Object.assign(form, {
      id: a.id, user_id: null, school_year_id: null, academic_term_id: null,
      committee_id: a.committee_id, committee_name: a.committee_name,
      role: a.role, load_units: a.load_units, status: a.status,
      remarks: a.remarks ?? '', plan_ids: committee?.plan_ids ? [...committee.plan_ids] : [],
    })
    ownPlanIds.value = a.plan_ids ? [...a.plan_ids] : []
  } else {
    selectedParentId.value = null
    form.reset()
    form.id = null
    form.role = 'member'
    form.load_units = 0.5
    form.status = 'active'
    form.plan_ids = []
    form.academic_term_id = filters.term_id ?? null
    ownPlanIds.value = []
  }
  modal.value = true
}

function onParentCommitteeChange() {
  const parent = selectedParent.value
  if (!parent) {
    form.committee_id   = null
    form.committee_name = ''
    form.plan_ids       = []
    return
  }
  if (!parent.sub_committees?.length) {
    form.committee_id   = parent.id
    form.committee_name = parent.name
    form.plan_ids       = [...(parent.plan_ids ?? [])]
    onRoleChange()
  } else {
    form.committee_id   = null
    form.committee_name = ''
    form.plan_ids       = []
  }
}

function onSubCommitteeChange() {
  const sub = selectedParent.value?.sub_committees?.find(s => s.id === form.committee_id)
  if (sub) {
    form.committee_name = sub.name
    form.plan_ids       = []
    onRoleChange()
  }
}

function onRoleChange() {
  const c = findCommitteeById(form.committee_id)
  if (!c) return
  const isChair = ['chairperson', 'co_chair'].includes(form.role)
  form.load_units = isChair ? c.chairperson_load_units : c.member_load_units
}

function togglePlan(id) {
  const idx = form.plan_ids.indexOf(id)
  if (idx === -1) form.plan_ids.push(id)
  else form.plan_ids.splice(idx, 1)
}

function saveOwnPlans(assignmentId) {
  useForm({ plan_ids: ownPlanIds.value }).put(route('pm-committees.plans.sync', assignmentId))
}

// New assignments must reference a real catalog committee (creation
// happens exclusively via the Catalog tab's "New Committee" form) — edits
// of a pre-existing assignment are left alone even if it predates this
// rule and has no committee_id.
const canSaveAssignment = computed(() => !!form.id || !!form.committee_id)

function save() {
  if (!canSaveAssignment.value) return

  if (form.id) {
    form.put(route('pm-committees.update', form.id), {
      onSuccess: () => { saveOwnPlans(form.id); modal.value = false },
    })
  } else {
    form.post(route('pm-committees.store'), {
      onSuccess: () => { modal.value = false },
    })
  }
}

async function remove(a) {
  if (! await confirmDelete(`Remove "${a.committee_name}" assignment for ${a.faculty?.name}?`)) return
  useForm({}).delete(route('pm-committees.destroy', a.id))
}

function roleBadge(role) {
  return {
    chairperson: 'amber',
    co_chair:    'amber',
    secretary:   'blue',
    member:      'slate',
  }[role] ?? 'slate'
}

function roleLabel(role) { return roles.find(r => r.value === role)?.label ?? role }

function statusBadge(status) {
  return {
    active:   'green',
    inactive: 'slate',
  }[status] ?? 'slate'
}

// ── Catalog tab (ported from the retired PMS Committees/Index.vue) ───────
const { isSubmitting: catalogIsSubmitting, submit: catalogSubmit } = useSubmit()

const fyFilter = ref(String(props.selectedFiscalYear ?? ""))
const applyCatalogFilters = () => {
  router.get(route("pm-committees.index"), { fiscal_year: fyFilter.value }, { preserveState: true, preserveScroll: true })
}

const catalogSearch = ref("")
const catalogPage = ref(1)
const CATALOG_PER_PAGE = 10

const filteredCatalog = computed(() => {
  const q = catalogSearch.value.toLowerCase()
  return props.catalog.filter(c => c.name?.toLowerCase().includes(q))
})
const paginatedCatalog = computed(() => {
  const start = (catalogPage.value - 1) * CATALOG_PER_PAGE
  return filteredCatalog.value.slice(start, start + CATALOG_PER_PAGE)
})
const totalCatalogPages = computed(() => Math.max(1, Math.ceil(filteredCatalog.value.length / CATALOG_PER_PAGE)))

const showCatalogModal = ref(false)
const catalogModalMode = ref("create")
const catalogMemberSearch = ref("")
const catalogPlanSearch = ref("")

const emptyCatalogSubCommittee = () => ({ id: null, name: '', head_id: '', member_ids: [], member_tasks: {}, memberSearch: '' })

const emptyCatalogForm = () => ({
  id: null,
  name: "",
  head_id: "",
  description: "",
  fiscal_year: props.currentFiscalYear ?? null,
  has_subcommittees: false,
  member_ids: [],
  member_tasks: {},
  plan_ids: [],
  sub_committees: [],
})

const catalogForm = ref(emptyCatalogForm())

const filteredCatalogUsers = computed(() => {
  const q = catalogMemberSearch.value.toLowerCase()
  if (!q) return props.users
  return props.users.filter(u => u.name.toLowerCase().includes(q))
})

const filteredCatalogSubUsers = (sub) => {
  const q = (sub.memberSearch || '').toLowerCase()
  if (!q) return props.users
  return props.users.filter(u => u.name.toLowerCase().includes(q))
}

const filteredCatalogPlans = computed(() => {
  const q = catalogPlanSearch.value.toLowerCase()
  if (!q) return props.plans
  return props.plans.filter(p => p.success_indicator.toLowerCase().includes(q))
})

const openCatalogModal = (mode, committee = null) => {
  catalogModalMode.value = mode
  showCatalogModal.value = true
  catalogMemberSearch.value = ''
  catalogPlanSearch.value = ''
  if ((mode === "edit" || mode === "view") && committee) {
    const memberTasks = {}
    committee.members?.forEach(m => { memberTasks[m.id] = m.pivot?.task ?? "" })
    const hasSubs = (committee.sub_committees?.length ?? 0) > 0
    catalogForm.value = {
      id: committee.id,
      name: committee.name ?? "",
      head_id: committee.head_id ?? "",
      description: committee.description ?? "",
      fiscal_year: committee.fiscal_year ?? null,
      has_subcommittees: hasSubs,
      member_ids: hasSubs ? [] : (committee.members?.map(m => m.id) ?? []),
      member_tasks: hasSubs ? {} : memberTasks,
      plan_ids: committee.work_distribution_plans?.map(p => p.id) ?? [],
      sub_committees: hasSubs ? committee.sub_committees.map(sub => {
        const subTasks = {}
        sub.members?.forEach(m => { subTasks[m.id] = m.pivot?.task ?? "" })
        return {
          id: sub.id,
          name: sub.name,
          head_id: sub.head_id ?? '',
          member_ids: sub.members?.map(m => m.id) ?? [],
          member_tasks: subTasks,
          memberSearch: '',
        }
      }) : [],
    }
  } else {
    catalogForm.value = emptyCatalogForm()
  }
}

const closeCatalogModal = () => { showCatalogModal.value = false }

const toggleCatalogMember = (userId) => {
  const idx = catalogForm.value.member_ids.indexOf(userId)
  if (idx === -1) {
    catalogForm.value.member_ids.push(userId)
  } else {
    catalogForm.value.member_ids.splice(idx, 1)
    delete catalogForm.value.member_tasks[userId]
  }
}

const toggleCatalogSubMember = (subIdx, userId) => {
  const sub = catalogForm.value.sub_committees[subIdx]
  const idx = sub.member_ids.indexOf(userId)
  if (idx === -1) {
    sub.member_ids.push(userId)
  } else {
    sub.member_ids.splice(idx, 1)
    delete sub.member_tasks[userId]
  }
}

const toggleCatalogPlan = (planId) => {
  const idx = catalogForm.value.plan_ids.indexOf(planId)
  if (idx === -1) catalogForm.value.plan_ids.push(planId)
  else catalogForm.value.plan_ids.splice(idx, 1)
}

const addCatalogSubCommittee = () => catalogForm.value.sub_committees.push(emptyCatalogSubCommittee())
const removeCatalogSubCommittee = (idx) => catalogForm.value.sub_committees.splice(idx, 1)

const submitCatalogCommittee = () => {
  const payload = {
    name: catalogForm.value.name,
    head_id: catalogForm.value.head_id || null,
    description: catalogForm.value.description,
    fiscal_year: catalogForm.value.fiscal_year ? Number(catalogForm.value.fiscal_year) : null,
    has_subcommittees: catalogForm.value.has_subcommittees,
    plan_ids: catalogForm.value.plan_ids,
  }

  if (catalogForm.value.has_subcommittees) {
    payload.sub_committees = catalogForm.value.sub_committees.map(sub => ({
      id: sub.id || undefined,
      name: sub.name,
      head_id: sub.head_id || null,
      member_ids: sub.member_ids,
      member_tasks: sub.member_tasks,
    }))
  } else {
    payload.member_ids = catalogForm.value.member_ids
    payload.member_tasks = catalogForm.value.member_tasks
  }

  if (catalogModalMode.value === "create") {
    catalogSubmit.post(route("pm-committees.catalog.store"), payload, {
      onSuccess: () => closeCatalogModal(),
    })
  } else {
    catalogSubmit.put(route("pm-committees.catalog.update", catalogForm.value.id), payload, {
      onSuccess: () => closeCatalogModal(),
    })
  }
}

const deleteCatalogCommittee = async (committee) => {
  const ok = await confirmDelete(`Delete "${committee.name}"? This cannot be undone.`)
  if (!ok) return
  catalogSubmit.delete(route("pm-committees.catalog.destroy", committee.id))
}

const structureBadge = (committee) => committee.sub_committees?.length ? 'indigo' : 'slate'
</script>
