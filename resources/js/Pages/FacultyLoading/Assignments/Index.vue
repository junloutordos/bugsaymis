<template>
  <Head title="Load Assignments" />
  <AdminLayout title="Load Assignments">
    <div class="space-y-5">

      <AppPageHeader title="Load Assignments" subtitle="Assign teaching, research, admin and co-curricular loads to faculty">
        <template #actions>
          <AppButton variant="secondary" @click="syncLoads">
            <ArrowPathIcon class="h-4 w-4" /> Re-sync Loads
          </AppButton>
          <AppButton variant="secondary" @click="openAutoAssign()">
            <SparklesIcon class="h-4 w-4 text-purple-500" /> Auto-Assign
          </AppButton>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> Add Assignment
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

      <!-- Term filter -->
      <AppFilterBar>
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <div class="w-48">
          <AppInput v-model="search" type="search" placeholder="Search faculty..." />
        </div>
      </AppFilterBar>

      <!-- Faculty list -->
      <AppTable :is-empty="filteredFaculty.length === 0" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Faculty</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Position</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Teaching</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Other</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="fl in filteredFaculty" :key="fl.faculty_id" class="hover:bg-slate-50/50">
          <td class="px-4 py-3 font-medium text-slate-800">{{ fl.faculty_name }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ fl.position ?? '—' }}</td>
          <td class="px-4 py-3 text-center font-semibold text-indigo-700">{{ fl.teaching_units }}u</td>
          <td class="px-4 py-3 text-center text-slate-500">{{ fl.other_units }}u</td>
          <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ fl.total_units }}u</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadge(fl.load_status)" class="capitalize">{{ fl.load_status ?? '—' }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-right">
            <AppIconButton label="View assignments" variant="success" @click="openDetail(fl)">
              <EyeIcon class="h-4 w-4" />
            </AppIconButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="fl in filteredFaculty" :key="fl.faculty_id" class="p-4 space-y-2" @click="openDetail(fl)">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ fl.faculty_name }}</p>
                <p class="text-xs text-slate-500">{{ fl.position ?? '—' }}</p>
              </div>
              <AppBadge :color="statusBadge(fl.load_status)" class="capitalize">{{ fl.load_status ?? '—' }}</AppBadge>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-500">
              <span>Teaching: <strong class="text-indigo-700">{{ fl.teaching_units }}u</strong></span>
              <span>Other: <strong class="text-slate-700">{{ fl.other_units }}u</strong></span>
              <span>Total: <strong class="text-slate-800">{{ fl.total_units }}u</strong></span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No assignments found for this term"
            subtitle="Run Auto-Assign or add assignments manually to get started."
            :icon="ClipboardDocumentListIcon" />
        </template>
      </AppTable>

    </div>

    <!-- ── Detail panel (slide-over — kept as bespoke chrome, no shared drawer component exists) ── -->
    <div v-if="detail.open" class="fixed inset-0 z-50 flex justify-end bg-black/30 backdrop-blur-sm"
      @click.self="detail.open = false">
      <div class="bg-white w-full max-w-xl h-full shadow-2xl flex flex-col overflow-hidden">

        <!-- Panel header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
          <div>
            <h2 class="text-base font-semibold text-slate-800">{{ detail.faculty?.faculty_name }}</h2>
            <p class="text-xs text-slate-500 mt-0.5">{{ detail.faculty?.position ?? '' }}</p>
          </div>
          <div class="flex items-center gap-2">
            <AppButton size="sm" @click="openForm(null, detail.faculty?.faculty_id)">
              <PlusIcon class="h-3.5 w-3.5" /> Add
            </AppButton>
            <AppIconButton label="Close" @click="detail.open = false"><XMarkIcon class="h-5 w-5" /></AppIconButton>
          </div>
        </div>

        <!-- Load summary strip -->
        <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 flex items-center gap-4 text-xs shrink-0">
          <span class="text-slate-500">Teaching: <strong class="text-indigo-700">{{ detail.faculty?.teaching_units }}u</strong></span>
          <span class="text-slate-500">Other: <strong class="text-slate-700">{{ detail.faculty?.other_units }}u</strong></span>
          <span class="text-slate-500">Total: <strong class="text-slate-800">{{ detail.faculty?.total_units }}u</strong></span>
          <AppBadge v-if="detail.faculty?.is_locked" color="amber" class="ml-auto">Locked</AppBadge>
        </div>

        <!-- Assignment list -->
        <div class="flex-1 overflow-y-auto divide-y divide-slate-50">
          <div v-if="!detail.faculty?.assignments?.length" class="py-12 text-center text-sm text-slate-400">
            No assignments yet.
          </div>
          <div v-for="a in detail.faculty?.assignments" :key="a.id"
            class="px-6 py-3 flex items-center justify-between gap-3 hover:bg-indigo-50/40">
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800 truncate">{{ assignmentLabel(a) }}</p>
              <div class="flex items-center gap-2 mt-0.5">
                <AppBadge :color="typeBadge(a.assignment_type)">{{ typeLabel(a.assignment_type) }}</AppBadge>
                <span class="text-xs text-slate-500">{{ a.load_units }}u</span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <AppIconButton label="Edit assignment" @click="openForm(a)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton label="Remove assignment" variant="danger" @click="remove(a)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <AppModal :show="modal" :title="`${form.id ? 'Edit' : 'Add'} Load Assignment`" size="lg" @close="modal = false">
      <div class="space-y-3">
        <!-- Faculty (create only) -->
        <div v-if="!form.id">
          <label class="block text-xs font-medium text-slate-600 mb-1">Faculty *</label>
          <select v-model="form.user_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Select faculty...</option>
            <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </div>

        <!-- Term (create only) -->
        <div v-if="!form.id">
          <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term *</label>
          <select v-model="form.academic_term_id" @change="onTermChange"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Select term...</option>
            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
          </select>
        </div>

        <!-- Type -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Assignment Type *</label>
          <select v-model="form.assignment_type" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">Select type...</option>
            <option v-for="t in assignmentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>

        <!-- Subject (teaching only) — shown before section so elective check can react -->
        <div v-if="form.assignment_type === 'teaching'">
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Subject *
            <span v-if="form.section_id && !selectedSubjectIsElective" class="text-indigo-500 font-normal ml-1">
              ({{ filteredSubjects.length }} for Grade {{ selectedSectionGrade }})
            </span>
          </label>
          <select v-model="form.subject_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">{{ form.section_id && !selectedSubjectIsElective ? 'Select subject...' : 'Select subject...' }}</option>
            <option v-for="s in filteredSubjects" :key="s.id" :value="s.id">{{ s.code }} — {{ s.name }}</option>
          </select>
        </div>

        <!-- Section (teaching only, non-elective subjects) -->
        <div v-if="form.assignment_type === 'teaching' && !selectedSubjectIsElective">
          <label class="block text-xs font-medium text-slate-600 mb-1">Section *</label>
          <select v-model="form.section_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Select section...</option>
            <option v-for="s in filteredSections" :key="s.id" :value="s.id">Grade {{ s.levelid }} — {{ s.sectionname }}</option>
          </select>
        </div>

        <!-- Elective notice (no section needed) -->
        <div v-if="form.assignment_type === 'teaching' && selectedSubjectIsElective"
          class="rounded-lg bg-warning-50 border border-warning-100 px-3 py-2 text-xs text-warning-700">
          Elective subject — offered across sections, no specific section required.
        </div>

        <!-- Description (non-teaching) -->
        <div v-if="form.assignment_type && form.assignment_type !== 'teaching'">
          <AppInput v-model="form.description" label="Description" placeholder="Brief description..." />
        </div>

        <!-- Load units -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Load Units *
            <span v-if="form.subject_id" class="text-indigo-500 font-normal ml-1">(auto-filled from subject)</span>
          </label>
          <input v-model.number="form.load_units" type="number" step="0.5" min="0.5" max="30"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            :class="form.subject_id ? 'bg-indigo-50 border-indigo-200' : ''" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Save' }}</AppButton>
      </template>
    </AppModal>

    <!-- ── Auto-Assign Modal ──────────────────────────────────────── -->
    <AppModal :show="autoAssign.open" title="Smart Auto-Assign Loads"
      subtitle="Matches faculty to subjects by specialization · auto-assigns sections by grade level · balances section load."
      size="4xl" body-class="" @close="autoAssign.open = false">

      <!-- Controls -->
      <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term</label>
          <select v-model="autoAssign.termId" @change="autoAssign.proposals = null; autoAssign.selected = []"
            class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}{{ t.is_current ? ' (current)' : '' }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Faculty (optional)</label>
          <select v-model="autoAssign.facultyId"
            class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <option :value="null">All Faculty</option>
            <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </div>
        <AppButton :loading="autoAssign.loading" @click="runPreview">
          <MagnifyingGlassIcon v-if="!autoAssign.loading" class="h-4 w-4" />
          {{ autoAssign.loading ? 'Generating…' : 'Generate Preview' }}
        </AppButton>
      </div>

      <!-- Alert -->
      <div v-if="autoAssign.error" class="mx-6 mt-4 bg-danger-50 border border-danger-100 text-danger-700 rounded-lg px-4 py-3 text-sm">
        {{ autoAssign.error }}
      </div>

      <!-- Summary bar -->
      <div v-if="autoAssign.proposals" class="px-6 py-3 bg-purple-50 border-b border-purple-100 flex flex-wrap gap-4 text-xs text-purple-700">
        <span><strong>{{ autoAssign.gapCount }}</strong> unfilled slots detected</span>
        <span><strong>{{ allProposedItems.length }}</strong> proposed to fill</span>
        <span><strong>{{ autoAssign.proposals.filter(p => p.assignments.length).length }}</strong> faculty matched</span>
        <span v-if="autoAssign.gapCount > allProposedItems.length" class="text-warning-600 font-medium">
          ⚠ {{ autoAssign.gapCount - allProposedItems.length }} slots may still need manual assignment
        </span>
      </div>

      <!-- Coverage gaps accordion -->
      <div v-if="autoAssign.gaps?.length" class="px-6 pt-4">
        <button @click="autoAssign.gapsOpen = !autoAssign.gapsOpen"
          class="w-full flex items-center justify-between text-xs font-medium text-slate-600 hover:text-slate-800 bg-warning-50 border border-warning-100 rounded-lg px-3 py-2">
          <span>
            <span class="text-warning-700 font-semibold">{{ autoAssign.gapCount }} uncovered subject-section slots</span>
            — click to {{ autoAssign.gapsOpen ? 'hide' : 'view' }} details by grade level
          </span>
          <span class="text-warning-500 ml-2">{{ autoAssign.gapsOpen ? '▲' : '▼' }}</span>
        </button>
        <div v-if="autoAssign.gapsOpen" class="mt-2 border border-warning-100 rounded-lg overflow-hidden">
          <div v-for="(slots, grade) in gapsByGrade" :key="grade" class="border-b border-warning-50 last:border-0">
            <div class="px-3 py-1.5 bg-warning-50 text-xs font-semibold text-warning-700">Grade {{ grade }}</div>
            <div class="divide-y divide-slate-50">
              <div v-for="slot in slots" :key="`${slot.section_id}-${slot.subject_id}`"
                class="px-3 py-1.5 flex items-center gap-3 text-xs text-slate-600">
                <span class="font-mono text-slate-500 w-20 shrink-0">{{ slot.subject_code }}</span>
                <span class="flex-1 text-slate-700">{{ slot.subject_name }}</span>
                <span class="text-slate-400 shrink-0">{{ slot.section_name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Preview results -->
      <div v-if="autoAssign.proposals" class="px-6 py-4 space-y-3 max-h-[52vh] overflow-y-auto">
        <div v-if="autoAssign.proposals.length === 0" class="text-center py-8 text-slate-400 text-sm">
          No faculty found for the selected term.
        </div>

        <div v-for="p in autoAssign.proposals" :key="p.faculty_id"
          class="border rounded-xl overflow-hidden"
          :class="p.assignments.length ? 'border-purple-100' : 'border-slate-100'">

          <!-- Faculty header -->
          <div class="px-4 py-2.5 flex items-center justify-between"
            :class="p.assignments.length ? 'bg-purple-50' : 'bg-slate-50'">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-semibold text-slate-800">{{ p.faculty_name }}</span>
              <span v-if="p.position" class="text-xs text-slate-500">{{ p.position }}</span>
              <span v-if="p.specialization"
                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 text-xs">
                <AcademicCapIcon class="h-3 w-3" /> {{ p.specialization }}
              </span>
            </div>
            <div class="shrink-0 text-xs text-slate-500 flex items-center gap-2 flex-wrap justify-end">
              <!-- Non-teaching load badges -->
              <span v-for="(units, type) in p.non_teaching_loads" :key="type"
                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold"
                :class="nonTeachingBadge(type)">
                {{ typeLabel(type) }}: {{ units }}u
              </span>
              <!-- Capacity summary -->
              <span class="text-slate-400">|</span>
              <span>Total: <strong>{{ p.already_total }}u</strong>/{{ p.total_cap }}u</span>
              <span>Teaching: <strong>{{ p.already_teaching }}u</strong>/{{ p.teaching_cap }}u</span>
              <span v-if="p.assignments.length" class="text-purple-700 font-medium">
                +{{ p.assignments.reduce((s, a) => s + a.load_units, 0).toFixed(1) }}u proposed
              </span>
            </div>
          </div>

          <!-- No matches -->
          <div v-if="!p.assignments.length" class="px-4 py-2 text-xs text-slate-400 italic">
            {{ p.skipped_reason ?? 'No matching subjects' }}
          </div>

          <!-- Proposed assignments table — grouped by subject -->
          <table v-else class="w-full text-sm divide-y divide-slate-50">
            <thead class="bg-white">
              <tr class="text-left text-xs text-slate-400 font-medium">
                <th class="px-4 py-1.5 w-6"></th>
                <th class="px-2 py-1.5">Subject</th>
                <th class="px-2 py-1.5 text-center">Grade</th>
                <th class="px-2 py-1.5">Sections</th>
                <th class="px-2 py-1.5 text-center">Match</th>
                <th class="px-2 py-1.5 text-right">Total Units</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="group in groupedAssignments(p.assignments)" :key="group.subject_id" class="hover:bg-slate-50">
                <!-- Checkbox — selects/deselects all sections for this subject -->
                <td class="px-4 py-2">
                  <input type="checkbox"
                    :checked="isGroupSelected(p.faculty_id, group)"
                    @change="toggleGroup(p.faculty_id, group, $event.target.checked)"
                    class="rounded text-purple-600 focus:ring-purple-500" />
                </td>
                <!-- Subject -->
                <td class="px-2 py-2">
                  <span class="font-medium text-slate-700">{{ group.subject_code }}</span>
                  <span class="text-slate-400 ml-1 text-xs">{{ group.subject_name }}</span>
                </td>
                <!-- Grade -->
                <td class="px-2 py-2 text-center text-slate-500">
                  {{ group.grade_level || '—' }}
                </td>
                <!-- Sections — all assigned -->
                <td class="px-2 py-2">
                  <div v-if="group.sections.length" class="flex flex-wrap gap-1">
                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-success-50 text-success-700">
                      {{ group.sections.length }} section{{ group.sections.length !== 1 ? 's' : '' }}
                    </span>
                    <span v-for="sec in group.sections" :key="sec.id"
                      class="inline-block px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-500">
                      {{ sec.label.split('—')[1]?.trim() ?? sec.label }}
                    </span>
                  </div>
                  <span v-else class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-warning-50 text-warning-700">
                    No section
                  </span>
                </td>
                <!-- Match score -->
                <td class="px-2 py-2 text-center">
                  <span :class="scoreClass(group.match_score)"
                    class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold">
                    {{ scoreLabel(group.match_score) }}
                  </span>
                </td>
                <!-- Total units across all sections -->
                <td class="px-2 py-2 text-right font-semibold text-purple-700">
                  {{ group.totalUnits.toFixed(1) }}u
                  <span class="text-xs font-normal text-slate-400 ml-0.5">
                    ({{ group.load_units }}u × {{ group.sections.length || 1 }})
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Select/deselect all -->
        <div v-if="allProposedItems.length" class="flex items-center gap-3 pt-1 text-xs text-slate-500">
          <button @click="selectAllProposed" class="underline hover:text-slate-700">Select all</button>
          <button @click="autoAssign.selected = []" class="underline hover:text-slate-700">Deselect all</button>
          <span>{{ autoAssign.selected.length }} of {{ allProposedItems.length }} selected</span>
        </div>

        <!-- Manual assignment panel for remaining unfilled slots -->
        <div v-if="autoAssign.manualSlots.length" class="mt-3 border border-warning-100 rounded-xl overflow-hidden">
          <div class="px-4 py-3 bg-warning-50 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-warning-700">
                Needs Manual Assignment
                <span class="ml-1.5 inline-flex items-center rounded-full bg-warning-100 text-warning-700 px-2 py-0.5 text-xs font-bold">
                  {{ autoAssign.manualSlots.length }} slot{{ autoAssign.manualSlots.length !== 1 ? 's' : '' }}
                </span>
              </p>
              <p class="text-xs text-warning-600 mt-0.5">
                These subjects have no matching faculty specialization — select a faculty for each slot to include in Apply.
              </p>
            </div>
            <span v-if="manualSelected.length" class="text-xs font-semibold text-success-700 bg-success-50 border border-success-100 rounded-full px-2.5 py-1">
              {{ manualSelected.length }} assigned
            </span>
          </div>

          <div v-for="group in manualSlotsBySection" :key="`${group.grade}-${group.section_id}`">
            <!-- Section header -->
            <div class="px-4 py-1.5 bg-slate-50 border-t border-warning-100 text-xs font-semibold text-slate-600 uppercase tracking-wide">
              Grade {{ group.grade }} — {{ group.section_name }}
            </div>

            <!-- Subject rows -->
            <div v-for="slot in group.subjects" :key="`${slot.section_id}-${slot.subject_id}`"
              class="border-t border-slate-50 px-4 py-2.5 flex items-center gap-3 hover:bg-slate-50/50">

              <!-- Subject info -->
              <div class="flex-1 min-w-0">
                <span class="font-medium text-slate-700 text-sm">{{ slot.subject_code }}</span>
                <span class="text-slate-500 text-xs ml-1.5">{{ slot.subject_name }}</span>
              </div>

              <!-- Units badge -->
              <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-500 px-2 py-0.5 text-xs font-semibold shrink-0">
                {{ slot.load_units }}u
              </span>

              <!-- Faculty picker -->
              <select v-model="slot.faculty_id"
                class="text-sm border rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-warning-500 focus:border-transparent shrink-0 max-w-[220px]"
                :class="slot.faculty_id ? 'border-success-100 bg-success-50 text-success-700' : 'border-slate-200 text-slate-600'">
                <option :value="null">Select faculty…</option>
                <option v-for="f in props.faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Helper note (kept in body so the AppModal footer stays a simple right-aligned action row) -->
        <p class="text-xs text-slate-400 pt-2 border-t border-slate-100">
          Sections are auto-assigned by grade level. You can override any section above before applying.
        </p>

      </div>

      <template #footer>
        <AppButton variant="secondary" @click="autoAssign.open = false">Cancel</AppButton>
        <AppButton variant="success"
          :disabled="(!autoAssign.selected.length && !manualSelected.length) || autoAssign.applying"
          :loading="autoAssign.applying"
          @click="applyAutoAssign">
          <CheckIcon v-if="!autoAssign.applying" class="h-4 w-4" />
          Apply
          <span v-if="autoAssign.selected.length || manualSelected.length">
            ({{ autoAssign.selected.length + manualSelected.length }})
          </span>
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
  AcademicCapIcon, ArrowPathIcon, CheckCircleIcon, CheckIcon, ClipboardDocumentListIcon,
  EyeIcon, MagnifyingGlassIcon, PencilIcon, PlusIcon, SparklesIcon, TrashIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  facultyLoads: { type: Array,  default: () => [] },
  terms:        { type: Array,  default: () => [] },
  faculty:      { type: Array,  default: () => [] },
  subjects:     { type: Array,  default: () => [] },
  sections:     { type: Array,  default: () => [] },
  currentTerm:  { type: Object, default: null },
  filters:      { type: Object, default: () => ({}) },
})

const assignmentTypes = [
  { value: 'teaching',     label: 'Teaching' },
  { value: 'research',     label: 'Research' },
  { value: 'admin',        label: 'Administrative' },
  { value: 'cocurricular', label: 'Co-curricular' },
  { value: 'committee',    label: 'Committee' },
]

// The currently selected subject object
const selectedSubject = computed(() =>
  form.subject_id ? props.subjects.find(s => s.id === form.subject_id) ?? null : null
)

// True when the selected subject is an elective (no section assignment needed)
const selectedSubjectIsElective = computed(() =>
  selectedSubject.value?.subject_type === 'elective'
)

// Grade level of the currently selected section
const selectedSectionGrade = computed(() => {
  if (!form.section_id) return null
  return props.sections.find(s => s.id === form.section_id)?.levelid ?? null
})

// Subjects filtered to the selected section's grade level.
// Shows all subjects when no section is selected yet; electives always visible.
const filteredSubjects = computed(() => {
  if (!form.section_id) return props.subjects
  const grade = selectedSectionGrade.value
  if (!grade) return props.subjects
  return props.subjects.filter(s =>
    s.subject_type === 'elective' || s.grade_level === grade || s.grade_level === 0
  )
})

// Sections filtered to match the selected subject's grade level (non-elective only)
const filteredSections = computed(() => {
  if (!form.subject_id || selectedSubjectIsElective.value) return props.sections
  const grade = selectedSubject.value?.grade_level
  if (!grade) return props.sections
  return props.sections.filter(s => s.levelid === grade)
})

// Auto-fill load_units when a subject is selected; clear section if elective
watch(() => form.subject_id, (subjectId) => {
  if (!subjectId) return
  const subject = props.subjects.find(s => s.id === subjectId)
  if (subject?.load_units) form.load_units = subject.load_units
  if (subject?.subject_type === 'elective') form.section_id = null
})

// Clear subject and reset units when assignment type changes
watch(() => form.assignment_type, () => {
  form.subject_id = null
  form.section_id = null
  form.load_units = 3
})

const filters = reactive({
  term_id: props.filters.term_id ?? props.currentTerm?.id ?? null,
})
const search = ref('')

const filteredFaculty = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.facultyLoads
  return props.facultyLoads.filter(fl => fl.faculty_name.toLowerCase().includes(q))
})

function applyFilters() {
  router.get(route('faculty-loading.assignments.index'), filters, { preserveState: true })
}

// Detail slide-over panel
const detail = reactive({ open: false, faculty: null })

function openDetail(fl) {
  detail.faculty = fl
  detail.open    = true
}

function onTermChange() {
  const t = props.terms.find(t => t.id === Number(form.academic_term_id))
  form.school_year_id = t?.school_year_id ?? null
}

const modal = ref(false)
const form = useForm({
  id: null,
  user_id: null,
  school_year_id: null,
  academic_term_id: null,
  assignment_type: '',
  subject_id: null,
  section_id: null,
  load_units: 3,
  description: '',
})

function openForm(a = null, prefillFacultyId = null) {
  if (a) {
    Object.assign(form, {
      id: a.id,
      user_id: null,
      school_year_id: null,
      academic_term_id: null,
      assignment_type: a.assignment_type,
      subject_id: a.subject?.id ?? null,
      section_id: a.section_id,
      load_units: a.load_units,
      description: a.description ?? '',
    })
  } else {
    form.reset()
    form.id = null
    form.academic_term_id = filters.term_id ? Number(filters.term_id) : null
    form.load_units = 3
    if (prefillFacultyId) form.user_id = prefillFacultyId
    // Pre-derive school_year_id from the pre-selected term
    const t = props.terms.find(t => t.id === form.academic_term_id)
    form.school_year_id = t?.school_year_id ?? null
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.assignments.update', form.id), {
      onSuccess: () => { modal.value = false; router.reload({ only: ['assignments'] }) },
    })
  } else {
    // Always re-derive school_year_id from the selected term before submitting
    const t = props.terms.find(t => t.id === Number(form.academic_term_id))
    if (t) form.school_year_id = t.school_year_id
    form.post(route('faculty-loading.assignments.store'), {
      onSuccess: () => { modal.value = false; router.reload({ only: ['assignments'] }) },
    })
  }
}

function assignmentLabel(a) {
  if (a.assignment_type === 'teaching' && a.subject) {
    return a.section_name ? `${a.subject.name} (${a.section_name})` : a.subject.name
  }
  return a.display_label
}

function syncLoads() {
  useForm({ term_id: filters.term_id }).post(route('faculty-loading.assignments.sync-loads'), {
    onSuccess: () => router.reload({ only: ['facultyLoads'] }),
  })
}

async function remove(a) {
  if (! await confirmDelete(`Remove "${assignmentLabel(a)}" assignment?`)) return
  useForm({}).delete(route('faculty-loading.assignments.destroy', a.id), {
    onSuccess: () => router.reload({ only: ['assignments'] }),
  })
}

// Color-mapping helpers for AppBadge (simple list/status pills)
function statusBadge(status) {
  return {
    underload: 'amber',
    full_load: 'green',
    overload:  'red',
  }[status] ?? 'slate'
}

function typeBadge(type) {
  return {
    teaching:     'indigo',
    research:     'purple',
    admin:        'blue',
    cocurricular: 'green',
    committee:    'orange',
  }[type] ?? 'slate'
}

// Raw class-map helpers kept for the dense Auto-Assign preview widget (rows/badges below)
function nonTeachingBadge(type) {
  return {
    research:     'bg-violet-100 text-violet-700',
    admin:        'bg-blue-100 text-blue-700',
    cocurricular: 'bg-teal-100 text-teal-700',
    committee:    'bg-orange-100 text-orange-700',
  }[type] ?? 'bg-slate-100 text-slate-600'
}

function typeLabel(type) {
  return assignmentTypes.find(t => t.value === type)?.label ?? type
}

// ── Auto-Assign ───────────────────────────────────────────────────────────────

const autoAssign = reactive({
  open:         false,
  termId:       props.filters.term_id ? Number(props.filters.term_id) : (props.currentTerm?.id ?? null),
  facultyId:    null,
  loading:      false,
  applying:     false,
  error:        null,
  proposals:    null,
  sections:     [],   // available sections returned from preview (for override dropdowns)
  selected:     [],
  gaps:         [],   // all uncovered section×subject slots (initial state)
  gapCount:     0,
  gapsOpen:     false,
  manualSlots:  [],   // remaining gaps the algo couldn't fill — user assigns faculty manually
})

// Manual slots grouped by section for the "needs manual assignment" panel
const manualSlotsBySection = computed(() => {
  const grouped = {}
  for (const slot of autoAssign.manualSlots) {
    const key = `${slot.grade}-${slot.section_id}`
    if (!grouped[key]) grouped[key] = { grade: slot.grade, section_name: slot.section_name, section_id: slot.section_id, subjects: [] }
    grouped[key].subjects.push(slot)
  }
  return Object.values(grouped).sort((a, b) => a.grade - b.grade || a.section_name.localeCompare(b.section_name))
})

const manualSelected = computed(() => autoAssign.manualSlots.filter(s => s.faculty_id))

// Coverage gaps grouped by grade level for the accordion display
const gapsByGrade = computed(() => {
  const grouped = {}
  for (const slot of autoAssign.gaps) {
    const g = slot.grade
    if (!grouped[g]) grouped[g] = []
    grouped[g].push(slot)
  }
  // Sort grades ascending
  return Object.fromEntries(Object.entries(grouped).sort(([a], [b]) => Number(a) - Number(b)))
})

// Flat list of all proposed items across all faculty (used for select-all and count)
const allProposedItems = computed(() => {
  if (!autoAssign.proposals) return []
  return autoAssign.proposals.flatMap(p =>
    p.assignments.map(a => ({ faculty_id: p.faculty_id, ...a }))
  )
})

function openAutoAssign() {
  autoAssign.proposals   = null
  autoAssign.sections    = []
  autoAssign.selected    = []
  autoAssign.error       = null
  autoAssign.gaps        = []
  autoAssign.gapCount    = 0
  autoAssign.gapsOpen    = false
  autoAssign.manualSlots = []
  autoAssign.open        = true
}

function selectAllProposed() {
  autoAssign.selected = []
  for (const p of (autoAssign.proposals ?? [])) {
    for (const group of groupedAssignments(p.assignments)) {
      toggleGroup(p.faculty_id, group, true)
    }
  }
}

/** Group a flat assignments array by subject_id — one entry per subject with all its sections. */
function groupedAssignments(assignments) {
  const map = {}
  for (const a of assignments) {
    if (!map[a.subject_id]) {
      map[a.subject_id] = {
        subject_id:   a.subject_id,
        subject_code: a.subject_code,
        subject_name: a.subject_name,
        grade_level:  a.grade_level,
        load_units:   a.load_units,
        match_score:  a.match_score,
        sections:     [],
        totalUnits:   0,
      }
    }
    if (a.section_id) {
      map[a.subject_id].sections.push({ id: a.section_id, label: a.section_label })
    }
    map[a.subject_id].totalUnits += a.load_units
  }
  return Object.values(map)
}

/** True when every section of a grouped subject is in the selected list. */
function isGroupSelected(facultyId, group) {
  if (group.sections.length === 0) {
    return autoAssign.selected.some(
      s => s.faculty_id === facultyId && s.subject_id === group.subject_id && !s.section_id
    )
  }
  return group.sections.every(sec =>
    autoAssign.selected.some(
      s => s.faculty_id === facultyId && s.subject_id === group.subject_id && s.section_id === sec.id
    )
  )
}

/** Select or deselect all section assignments for a grouped subject. */
function toggleGroup(facultyId, group, checked) {
  // Remove any existing selections for this faculty+subject first
  autoAssign.selected = autoAssign.selected.filter(
    s => !(s.faculty_id === facultyId && s.subject_id === group.subject_id)
  )
  if (!checked) return

  if (group.sections.length === 0) {
    autoAssign.selected.push({
      faculty_id:   facultyId,
      subject_id:   group.subject_id,
      subject_code: group.subject_code,
      subject_name: group.subject_name,
      load_units:   group.load_units,
      grade_level:  group.grade_level,
      match_score:  group.match_score,
      section_id:   null,
    })
  } else {
    for (const sec of group.sections) {
      autoAssign.selected.push({
        faculty_id:   facultyId,
        subject_id:   group.subject_id,
        subject_code: group.subject_code,
        subject_name: group.subject_name,
        load_units:   group.load_units,
        grade_level:  group.grade_level,
        match_score:  group.match_score,
        section_id:   sec.id,
      })
    }
  }
}

async function runPreview() {
  if (!autoAssign.termId || autoAssign.loading) return
  autoAssign.loading   = true
  autoAssign.error     = null
  autoAssign.proposals = null
  autoAssign.sections  = []
  autoAssign.selected  = []

  try {
    const { data } = await axios.get('/faculty-loading/auto-assign/preview', {
      params: { academic_term_id: autoAssign.termId, faculty_id: autoAssign.facultyId || undefined },
    })
    autoAssign.proposals = data.proposals
    autoAssign.sections  = data.sections ?? []
    autoAssign.gaps      = data.coverage_gaps ?? []
    autoAssign.gapCount  = autoAssign.gaps.length
    autoAssign.gapsOpen  = false

    // Compute remaining gaps: slots the algo couldn't auto-fill
    // Key format: "{subject_id}:{section_id}" for regular, "{subject_id}:elec" for electives
    const proposedKeys = new Set(
      (data.proposals ?? []).flatMap(p =>
        p.assignments.map(a => a.section_id ? `${a.subject_id}:${a.section_id}` : `${a.subject_id}:elec`)
      )
    )
    autoAssign.manualSlots = (data.coverage_gaps ?? [])
      .filter(g => {
        const key = g.section_id ? `${g.subject_id}:${g.section_id}` : `${g.subject_id}:elec`
        return !proposedKeys.has(key)
      })
      .map(g => {
        const subj = props.subjects.find(s => s.id === g.subject_id)
        return { ...g, load_units: subj?.load_units ?? 3, faculty_id: null }
      })
  } catch (err) {
    autoAssign.error = err.response?.data?.message ?? 'Failed to generate preview.'
  } finally {
    autoAssign.loading = false
  }
}

async function applyAutoAssign() {
  const hasAuto   = autoAssign.selected.length > 0
  const hasManual = manualSelected.value.length > 0
  if ((!hasAuto && !hasManual) || autoAssign.applying) return
  autoAssign.applying = true
  autoAssign.error    = null

  // Re-snapshot section_id from the live assignment objects (user may have overridden).
  // Must match on both subject_id AND section_id so multi-section subjects don't
  // all collapse to the first section's id, causing the rest to be skipped as duplicates.
  const autoPayload = autoAssign.selected.map(sel => {
    const proposal       = autoAssign.proposals?.find(p => p.faculty_id === sel.faculty_id)
    const liveAssignment = proposal?.assignments?.find(
      a => a.subject_id === sel.subject_id && a.section_id === sel.section_id
    )
    return { ...sel, section_id: liveAssignment?.section_id ?? sel.section_id }
  })

  const manualPayload = manualSelected.value.map(s => ({
    faculty_id:  s.faculty_id,
    subject_id:  s.subject_id,
    subject_code: s.subject_code,
    subject_name: s.subject_name,
    load_units:  s.load_units,
    section_id:  s.section_id,
    match_score: 0,
  }))

  const payload = [...autoPayload, ...manualPayload]

  try {
    const { data } = await axios.post('/faculty-loading/auto-assign/apply', {
      academic_term_id: autoAssign.termId,
      selected:         payload,
    })
    autoAssign.open = false
    router.reload({ only: ['assignments'] })
    alert(data.message)
  } catch (err) {
    autoAssign.error = err.response?.data?.message ?? 'Failed to apply assignments.'
  } finally {
    autoAssign.applying = false
  }
}

// ── Auto-assign UI helpers ────────────────────────────────────────────────────

/** Unique sorted grade levels from the sections list (for optgroup labels). */
function sectionGrades(sectionList) {
  return [...new Set((sectionList ?? []).map(s => s.grade))].sort((a, b) => a - b)
}

/** CSS class for the match-score badge. */
function scoreClass(score) {
  if (score >= 6) return 'bg-emerald-100 text-emerald-700'
  if (score >= 3) return 'bg-blue-100 text-blue-700'
  return 'bg-amber-100 text-amber-700'
}

/** Human-readable label for match score. */
function scoreLabel(score) {
  if (score >= 6) return '★ High'
  if (score >= 3) return '◆ Med'
  return '◇ Low'
}
</script>
