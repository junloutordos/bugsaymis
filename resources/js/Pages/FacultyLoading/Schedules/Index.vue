<template>
  <Head :title="pageTitle" />
  <AdminLayout :title="pageTitle">
    <div class="space-y-5">

      <AppPageHeader :title="pageTitle" :subtitle="pageSubtitle">
        <template #actions>
          <template v-if="isManage">
            <AppButton variant="secondary" as="link" :href="route('faculty-loading.auto-schedule.index')">
              <SparklesIcon class="h-4 w-4" /> AI Generate
            </AppButton>
            <AppButton @click="openForm()">
              <PlusIcon class="h-4 w-4" /> Assign Schedule
            </AppButton>
          </template>
          <AppButton :variant="isManage ? 'secondary' : 'primary'" @click="openNonTeachingForm()">
            <ClockIcon class="h-4 w-4" /> Add Non-teaching
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length"
        class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div v-if="!isSelf" class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm shrink-0">
          <button type="button" @click="setViewBy('section')"
            :class="['px-3 py-1.5 font-medium transition-colors', viewBy === 'section' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            By Section
          </button>
          <button type="button" @click="setViewBy('faculty')"
            :class="['px-3 py-1.5 font-medium border-l border-slate-200 transition-colors', viewBy === 'faculty' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            By Faculty
          </button>
          <button type="button" @click="setViewBy('grade')"
            :class="['px-3 py-1.5 font-medium border-l border-slate-200 transition-colors', viewBy === 'grade' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            By Year Level
          </button>
        </div>
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <select v-if="!isSelf && viewBy === 'section'" v-model="filters.section_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All Sections</option>
          <option v-for="sec in sections" :key="sec.id" :value="sec.id">
            Grade {{ sec.levelid }} — {{ sec.sectionname }}
          </option>
        </select>
        <select v-else-if="!isSelf && viewBy === 'faculty'" v-model="filters.faculty_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All Faculty</option>
          <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
        <select v-else-if="!isSelf" v-model="gradeFilter"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All Grades</option>
          <option v-for="g in GRADE_LEVELS" :key="g" :value="g">Grade {{ g }}</option>
        </select>
      </AppFilterBar>

      <!-- Unplaced subjects tray (mobile/tablet — full-width horizontal bar) -->
      <div v-if="unplacedLoads.length"
        class="lg:hidden bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-4 space-y-2">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
          Unplaced Subjects — drag onto a slot below
        </p>
        <div class="flex flex-wrap gap-2">
          <div v-for="load in unplacedLoads" :key="load.load_assignment_id"
            :draggable="!load.is_locked"
            @dragstart="onDragStartLoad($event, load)"
            @dragend="onDragEnd"
            :class="['inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium select-none',
              load.is_locked
                ? 'opacity-50 cursor-not-allowed bg-slate-50 border-slate-200 text-slate-400'
                : 'bg-amber-50 border-amber-200 text-amber-800 hover:bg-amber-100 cursor-grab active:cursor-grabbing']">
            <LockClosedIcon v-if="load.is_locked" class="h-3 w-3" />
            <span class="font-bold">{{ load.subject?.code }}</span>
            <span>· {{ load.faculty?.name ?? 'TBA' }}</span>
            <span>· G{{ load.grade_level }} {{ load.section_name }}</span>
            <span class="bg-amber-200/60 px-1.5 py-0.5 rounded-full">needs {{ load.still_needed }}</span>
          </div>
        </div>
      </div>

      <!-- Calendars (left) + sticky Unplaced Subjects panel (right, lg+) -->
      <div class="flex gap-5 items-start">
        <div class="flex-1 min-w-0 space-y-6">

          <!-- Empty state -->
          <AppCard v-if="groupsWithSchedules.length === 0">
            <EmptyState title="No schedules found"
              :subtitle="isManage ? 'Assign a schedule or use AI Generate to get started.' : 'No schedule entries for this term yet. Use Add Non-teaching to block time on your calendar.'"
              :icon="CalendarIcon" />
          </AppCard>

          <!-- Calendar cards per section / per faculty -->
          <template v-else>
            <div v-for="groupId in groupsWithSchedules" :key="groupId"
              class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">

              <!-- Group header -->
              <div class="px-4 py-3 bg-gradient-to-r from-indigo-50 to-slate-50 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span v-if="viewBy === 'section'" class="text-xs font-bold text-white bg-indigo-500 px-2.5 py-0.5 rounded-full">
                    Grade {{ groupHeaderInfo(groupId).grade_level }}
                  </span>
                  <h3 v-if="viewBy === 'grade'" class="text-sm font-semibold text-slate-800">
                    Grade {{ groupId }} — Electives
                  </h3>
                  <h3 v-else class="text-sm font-semibold text-slate-800">
                    {{ viewBy === 'faculty' ? groupHeaderInfo(groupId).faculty_name : groupHeaderInfo(groupId).section_name }}
                  </h3>
                  <span class="text-xs text-slate-400">· {{ byGroup[groupId]?.length ?? 0 }} slot(s)</span>
                </div>
                <AppButton v-if="isManage && viewBy === 'section'" variant="secondary" size="sm" @click="openForm({ section_id: groupId })">
                  <PlusIcon class="h-3 w-3" /> Add
                </AppButton>
              </div>

              <!-- Calendar grid -->
              <div class="overflow-x-auto">
                <div style="min-width: 580px">

                  <!-- Day column headers -->
                  <div class="flex border-b border-slate-100">
                    <div class="shrink-0 border-r border-slate-100" :style="{ width: GUTTER + 'px' }" />
                    <div v-for="day in WEEKDAYS" :key="day"
                      class="flex-1 text-center py-2 border-l border-slate-100 first:border-l-0">
                      <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        {{ day.slice(0, 3) }}
                      </span>
                      <span v-if="dayConfigs[day]" class="block text-xs text-slate-400 leading-tight">
                        {{ fmtConfigTime(dayConfigs[day].start) }}–{{ fmtConfigTime(dayConfigs[day].end) }}
                      </span>
                    </div>
                  </div>

                  <!-- Time axis + columns -->
                  <div class="flex" :style="{ height: CAL_H + 'px' }">

                    <!-- Time gutter -->
                    <div class="shrink-0 relative border-r border-slate-100" :style="{ width: GUTTER + 'px' }">
                      <div v-for="h in HOURS" :key="h"
                        :style="{ top: hourTop(h) + 'px' }"
                        class="absolute right-2 -translate-y-2.5 select-none">
                        <span class="text-xs text-slate-400 font-medium">
                          {{ h === 12 ? '12PM' : h < 12 ? h + 'AM' : (h - 12) + 'PM' }}
                        </span>
                      </div>
                    </div>

                    <!-- Grid body: gridlines + day columns -->
                    <div class="flex-1 relative flex">

                      <!-- Horizontal hour lines (drawn over all columns) -->
                      <div v-for="h in HOURS" :key="'hl-' + h"
                        :style="{ top: hourTop(h) + 'px' }"
                        class="absolute inset-x-0 border-t border-slate-100 pointer-events-none z-0" />

                      <!-- Half-hour dashed lines -->
                      <div v-for="h in HOURS" :key="'hl30-' + h"
                        :style="{ top: (hourTop(h) + SCALE * 30) + 'px' }"
                        class="absolute inset-x-0 border-t border-dashed border-slate-50 pointer-events-none z-0" />

                      <!-- Day columns -->
                      <div v-for="day in WEEKDAYS" :key="day"
                        v-memo="[byGroupDay[groupId]?.[day], dropPreviewKey(groupId, day), dragDimKey(groupId, day), createGhostKey(groupId, day), dayConfigs[day]]"
                        :class="['flex-1 relative border-l border-slate-100 overflow-hidden',
                          canQuickCreate(groupId) ? 'cursor-crosshair' : '']"
                        @mousedown="onColumnMouseDown($event, groupId, day)"
                        @dragover.prevent="onDragOverColumn($event, groupId, day)"
                        @drop.prevent="onDropColumn($event, groupId, day)">

                        <!-- Click/drag-to-create ghost (Google Calendar-style) -->
                        <div v-if="createDraft && createDraft.groupId === groupId && createDraft.day === day"
                          :style="createGhostStyle()"
                          class="absolute inset-x-0.5 rounded-md border-2 border-indigo-400 bg-indigo-100/80 z-20 pointer-events-none flex items-start justify-center px-1 overflow-hidden">
                          <span class="text-xs font-semibold text-indigo-700 mt-0.5 select-none tabular-nums">
                            {{ fmtTime(minToTime(createDraft.startMin)) }} – {{ fmtTime(minToTime(createDraft.endMin)) }}
                          </span>
                        </div>

                        <!-- Drag-and-drop preview -->
                        <div v-if="dropTarget && dropTarget.groupId === groupId && dropTarget.day === day"
                          :style="dropPreviewStyle()"
                          :class="['absolute rounded border-2 z-30 pointer-events-none flex items-center justify-center px-1 text-center',
                            dropTarget.hasConflict ? 'bg-red-100/85 border-red-400' : 'bg-emerald-100/85 border-emerald-400']">
                          <span :class="['text-xs font-semibold truncate', dropTarget.hasConflict ? 'text-red-700' : 'text-emerald-700']">
                            {{ dropTarget.hasConflict ? (dropTarget.message ?? 'Conflict') : 'Drop here' }}
                          </span>
                        </div>

                        <!-- Blocked period overlays -->
                        <div v-for="bp in (dayConfigs[day]?.blocked ?? [])" :key="bp.label"
                          :style="blockedStyle(bp)"
                          class="absolute inset-x-0 pointer-events-none z-[1] flex items-center justify-center">
                          <div class="absolute inset-0 bg-slate-100/70" />
                          <span class="relative text-xs text-slate-400 font-medium px-1 text-center leading-tight select-none">
                            {{ bp.label }}
                          </span>
                        </div>

                        <!-- No-class afternoon overlay (Wed & Fri end at 12:00) -->
                        <div v-if="dayConfigs[day] && timeToMin(dayConfigs[day].end) <= 12 * 60"
                          :style="{ position: 'absolute', top: ((12 * 60 - CAL_START) * SCALE) + 'px', bottom: 0, left: 0, right: 0 }"
                          class="pointer-events-none z-[1]">
                          <div class="absolute inset-0 bg-slate-50/80 border-t border-slate-200/50" />
                          <span class="relative block text-center text-xs text-slate-300 mt-2 select-none font-medium">
                            No Classes
                          </span>
                        </div>

                        <!-- Schedule event blocks -->
                        <div v-for="s in (byGroupDay[groupId]?.[day] ?? [])" :key="s.id"
                          data-evt
                          :style="[eventStyle(s), eventColorStyle(s)]"
                          :draggable="canDrag(s)"
                          :class="['absolute rounded border z-10 overflow-hidden transition-all hover:shadow-md hover:z-20 hover:scale-[1.01]',
                            s.entry_type === 'non_teaching' ? 'border-dashed' : '',
                            canDrag(s) ? 'cursor-grab active:cursor-grabbing' : (s.can_edit ? 'cursor-pointer' : 'cursor-default'),
                            dragPayload?.kind === 'move' && dragPayload.schedule.id === s.id ? 'opacity-30' : '']"
                          @dragstart="onDragStartEvent($event, s)"
                          @dragend="onDragEnd"
                          @click="onEventClick(s)">
                          <div class="px-1.5 py-0.5 h-full flex flex-col gap-px overflow-hidden">
                            <div class="text-xs font-bold leading-tight truncate">
                              {{ s.entry_type === 'non_teaching' ? s.title : s.subject?.code }}
                            </div>
                            <div class="text-xs leading-tight truncate opacity-75">
                              {{ secondaryLabel(s) }}
                            </div>
                            <div class="text-xs leading-tight opacity-55 tabular-nums">
                              {{ fmtTime(s.start_time) }}–{{ fmtTime(s.end_time) }}
                            </div>
                          </div>
                          <!-- Status indicator bar -->
                          <div v-if="s.status === 'tentative'"
                            class="absolute top-0 right-0 bottom-0 w-0.5 bg-amber-400" />
                          <LockClosedIcon v-if="s.is_locked"
                            class="absolute top-0.5 right-0.5 h-3 w-3 text-slate-400" title="Locked — drag disabled" />
                          <div v-if="s.status === 'cancelled'"
                            class="absolute inset-0 bg-white/60 flex items-center justify-center">
                            <span class="text-xs text-slate-400 font-medium">Cancelled</span>
                          </div>
                        </div>

                      </div>
                    </div>
                  </div>

                </div>
              </div>

              <!-- Legend: subjects for this group -->
              <div class="px-4 py-2.5 border-t border-slate-100 flex flex-wrap gap-1.5">
                <div v-for="sub in subjectsInGroup(groupId)" :key="sub.id"
                  :style="subjectColorStyle(sub.id)"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border">
                  {{ sub.code }}
                </div>
              </div>

            </div>
          </template>

        </div>

        <!-- Unplaced subjects panel (desktop — sticky right rail) -->
        <div v-if="unplacedLoads.length"
          class="hidden lg:block w-80 shrink-0 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 sticky top-4">
          <div class="px-4 py-3 border-b border-slate-100">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              Unplaced Subjects ({{ unplacedLoads.length }})
            </p>
            <p class="text-xs text-slate-400 mt-0.5">Drag onto a slot in the calendar</p>
          </div>
          <div class="p-3 space-y-2 max-h-[calc(100vh-8rem)] overflow-y-auto">
            <div v-for="load in unplacedLoads" :key="load.load_assignment_id"
              :draggable="!load.is_locked"
              @dragstart="onDragStartLoad($event, load)"
              @dragend="onDragEnd"
              :class="['rounded-lg border px-3 py-2 text-xs font-medium select-none',
                load.is_locked
                  ? 'opacity-50 cursor-not-allowed bg-slate-50 border-slate-200 text-slate-400'
                  : 'bg-amber-50 border-amber-200 text-amber-800 hover:bg-amber-100 cursor-grab active:cursor-grabbing']">
              <div class="flex items-center justify-between gap-1">
                <span class="font-bold">{{ load.subject?.code }}</span>
                <LockClosedIcon v-if="load.is_locked" class="h-3 w-3 shrink-0" />
                <span v-else class="bg-amber-200/60 px-1.5 py-0.5 rounded-full shrink-0">needs {{ load.still_needed }}</span>
              </div>
              <div class="mt-0.5 text-slate-500">{{ load.faculty?.name ?? 'TBA' }}</div>
              <div class="text-slate-500">G{{ load.grade_level }} {{ load.section_name }}</div>
            </div>
          </div>
        </div>

      </div>

    </div>

    <!-- Quick-create popover (Google Calendar-style) -->
    <div v-if="quickCreate" ref="qcEl"
      class="fixed z-50 w-[344px] bg-white rounded-xl shadow-2xl border border-slate-200"
      :style="{ left: quickCreate.x + 'px', top: quickCreate.y + 'px' }">

      <div class="flex items-center justify-between px-4 pt-3 pb-2">
        <span class="text-sm font-semibold text-slate-800">
          {{ quickCreate.day }} · {{ fmtTime(qc.start_time) }} – {{ fmtTime(qc.end_time) }}
        </span>
        <button type="button" class="text-slate-400 hover:text-slate-600 p-0.5" @click="closeQuickCreate">
          <XMarkIcon class="h-4 w-4" />
        </button>
      </div>

      <!-- Entry-type toggle (manage only — faculty always add blocks) -->
      <div v-if="quickCreate.allowClass" class="px-4 pb-2.5">
        <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-xs">
          <button type="button" @click="setQcMode('class')"
            :class="['px-3 py-1.5 font-medium transition-colors', qc.mode === 'class' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            Class
          </button>
          <button type="button" @click="setQcMode('block')"
            :class="['px-3 py-1.5 font-medium border-l border-slate-200 transition-colors', qc.mode === 'block' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            {{ qcBlockLabel }}
          </button>
        </div>
      </div>

      <div class="px-4 space-y-2.5">

        <!-- Pinned context line -->
        <p v-if="quickCreate.sectionPinned" class="text-xs text-slate-500">
          Section: <span class="font-medium text-slate-700">{{ sectionLabel(quickCreate.sectionPinned) }}</span>
        </p>

        <!-- Block (non-teaching / section activity) fields -->
        <template v-if="qc.mode === 'block'">
          <input v-model="qc.title" type="text" maxlength="120"
            :placeholder="viewBy === 'section' ? 'Title — e.g. Quarterly Exam, Assembly' : 'Title — e.g. Consultation Hours'"
            class="w-full text-sm border border-slate-200 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          <select v-model="qc.category" @change="runQuickValidate"
            class="w-full text-sm border border-slate-200 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">Category…</option>
            <option v-for="c in NON_TEACHING_CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
          <select v-if="isManage && viewBy === 'section'" v-model="qc.faculty_id" @change="runQuickValidate"
            class="w-full text-sm border border-slate-200 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Section-wide (no faculty)</option>
            <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </template>

        <!-- Class fields -->
        <template v-else>
          <select v-if="!quickCreate.sectionPinned" v-model="qc.section_id" @change="runQuickValidate"
            class="w-full text-sm border border-slate-200 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Section…</option>
            <option v-for="sec in sections" :key="sec.id" :value="sec.id">Grade {{ sec.levelid }} — {{ sec.sectionname }}</option>
          </select>
          <select v-model="qc.subject_id" @change="runQuickValidate"
            class="w-full text-sm border border-slate-200 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Subject…</option>
            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.code }} — {{ s.name }}</option>
          </select>
          <select v-model="qc.faculty_id" @change="runQuickValidate"
            class="w-full text-sm border border-slate-200 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Faculty…</option>
            <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
          <select v-model="qc.classroom_id" @change="runQuickValidate"
            class="w-full text-sm border border-slate-200 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Classroom…</option>
            <option v-for="c in classrooms" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
          </select>
        </template>

        <!-- Time range -->
        <div class="flex items-center gap-2">
          <input v-model="qc.start_time" type="time"
            class="flex-1 text-sm border border-slate-200 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          <span class="text-slate-400 text-sm">–</span>
          <input v-model="qc.end_time" type="time"
            class="flex-1 text-sm border border-slate-200 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
        </div>

        <!-- Live conflict feedback -->
        <div v-if="qcValidation?.errors?.length || qcValidation?.warnings?.length" class="space-y-1">
          <p v-for="err in qcValidation.errors ?? []" :key="err"
            class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-2.5 py-1.5 text-xs flex items-start gap-1.5">
            <ExclamationCircleIcon class="h-3.5 w-3.5 shrink-0 mt-px" /> {{ err }}
          </p>
          <p v-for="w in qcValidation.warnings ?? []" :key="w"
            class="bg-warning-50 border border-warning-100 text-warning-700 rounded-lg px-2.5 py-1.5 text-xs flex items-start gap-1.5">
            <ExclamationTriangleIcon class="h-3.5 w-3.5 shrink-0 mt-px" /> {{ w }}
          </p>
        </div>
      </div>

      <div class="flex items-center justify-between px-4 py-3">
        <button type="button" class="text-sm text-indigo-600 hover:underline font-medium" @click="qcMoreOptions">
          More options
        </button>
        <AppButton size="sm" :loading="qc.saving" :disabled="!qcCanSave" @click="saveQuickCreate">Save</AppButton>
      </div>
    </div>

    <!-- Schedule Form Modal -->
    <AppModal :show="modal" :title="modalTitle" size="lg"
      body-class="px-6 py-4 space-y-4" @close="modal = false">

      <!-- Validation result banner -->
      <div v-if="validationResult" class="space-y-1.5">
        <div v-for="err in validationResult.errors ?? []" :key="err"
          class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-3 py-2 text-xs flex items-start gap-1.5">
          <ExclamationCircleIcon class="h-4 w-4 shrink-0 mt-0.5" /> {{ err }}
        </div>
        <div v-for="w in validationResult.warnings ?? []" :key="w"
          class="bg-warning-50 border border-warning-100 text-warning-700 rounded-lg px-3 py-2 text-xs flex items-start gap-1.5">
          <ExclamationTriangleIcon class="h-4 w-4 shrink-0 mt-0.5" /> {{ w }}
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <template v-if="form.entry_type === 'non_teaching'">
          <div class="col-span-2">
            <AppInput v-model="form.title" label="Title" required placeholder="e.g. Consultation Hours, Research Block" />
          </div>
          <div class="col-span-2">
            <AppSelect v-model="form.category" label="Category" placeholder="Select category...">
              <option v-for="c in NON_TEACHING_CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option>
            </AppSelect>
          </div>
        </template>
        <div class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Faculty {{ form.entry_type === 'non_teaching' ? '' : '*' }}
          </label>
          <select v-model="form.faculty_id" :disabled="isSelf"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-500">
            <option :value="null">{{ form.entry_type === 'non_teaching' ? 'No faculty (section-wide)' : 'Select faculty...' }}</option>
            <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </div>
        <div v-if="form.entry_type !== 'non_teaching'" class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Subject *</label>
          <select v-model="form.subject_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Select subject...</option>
            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.code }} — {{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Section {{ form.entry_type === 'non_teaching' ? '' : '*' }}
          </label>
          <select v-model="form.section_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">{{ form.entry_type === 'non_teaching' ? 'No section' : 'Select section...' }}</option>
            <option v-for="sec in sections" :key="sec.id" :value="sec.id">
              Grade {{ sec.levelid }} — {{ sec.sectionname }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Classroom {{ form.entry_type === 'non_teaching' ? '' : '*' }}
          </label>
          <select v-model="form.classroom_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">{{ form.entry_type === 'non_teaching' ? 'No room' : 'Select classroom...' }}</option>
            <option v-for="c in classrooms" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term *</label>
          <select v-model="form.academic_term_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Select term...</option>
            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
          </select>
        </div>
        <AppSelect v-model="form.day_of_week" label="Day" required placeholder="Select day...">
          <option v-for="d in WEEKDAYS" :key="d" :value="d">{{ d }}</option>
        </AppSelect>
        <AppInput v-model="form.start_time" type="time" label="Start Time" required />
        <AppInput v-model="form.end_time" type="time" label="End Time" required />
        <AppSelect v-model="form.status" label="Status" :show-blank="false">
          <option value="active">Active</option>
          <option value="tentative">Tentative</option>
          <option v-if="form.id && form.entry_type !== 'non_teaching'" value="cancelled">Cancelled</option>
        </AppSelect>
        <div class="col-span-2">
          <AppTextarea v-model="form.remarks" label="Remarks" :rows="2" />
        </div>

        <!-- Override warnings -->
        <div v-if="validationResult && validationResult.warnings?.length && !validationResult.errors?.length"
          class="col-span-2 flex items-center gap-2">
          <input v-model="form.force" type="checkbox" id="force-save" class="rounded text-amber-500" />
          <label for="force-save" class="text-sm text-amber-700">
            I acknowledge the warnings — save anyway
          </label>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between items-center gap-3 w-full">
          <div class="flex gap-2">
            <AppButton variant="secondary" @click="checkConflicts">
              <MagnifyingGlassIcon class="h-4 w-4" /> Check Conflicts
            </AppButton>
            <AppButton v-if="form.id && form.entry_type === 'non_teaching'" variant="danger" @click="removeNonTeaching">
              <TrashIcon class="h-4 w-4" /> Remove
            </AppButton>
          </div>
          <div class="flex gap-2">
            <AppButton variant="ghost" @click="modal = false">Cancel</AppButton>
            <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Save' }}</AppButton>
          </div>
        </div>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppSelect from '@/Components/AppSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  CalendarIcon, CheckCircleIcon, ClockIcon, ExclamationCircleIcon, ExclamationTriangleIcon,
  LockClosedIcon, MagnifyingGlassIcon, PlusIcon, SparklesIcon, TrashIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

// ── Calendar constants ───────────────────────────────────────────────────────

const WEEKDAYS  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
const CAL_START = 7 * 60        // 7:00 AM in minutes
const CAL_END   = 16 * 60 + 30  // 4:30 PM in minutes
const SCALE     = 1.2            // px per minute
const GUTTER    = 44             // width of the time-axis gutter in px
const CAL_H     = (CAL_END - CAL_START) * SCALE  // total calendar height in px

// Hour marks to draw (7 AM through 4 PM inclusive)
const HOURS = Array.from({ length: 10 }, (_, i) => i + 7)

// Subject color palette — 10 distinct colors, cycling by subject_id % 10
const PALETTE = [
  { bg: '#dbeafe', border: '#93c5fd', color: '#1e40af' },
  { bg: '#ede9fe', border: '#c4b5fd', color: '#5b21b6' },
  { bg: '#d1fae5', border: '#6ee7b7', color: '#065f46' },
  { bg: '#fef3c7', border: '#fcd34d', color: '#92400e' },
  { bg: '#fee2e2', border: '#fca5a5', color: '#991b1b' },
  { bg: '#cffafe', border: '#67e8f9', color: '#0e7490' },
  { bg: '#fce7f3', border: '#f9a8d4', color: '#9d174d' },
  { bg: '#ecfdf5', border: '#34d399', color: '#064e3b' },
  { bg: '#fff7ed', border: '#fdba74', color: '#9a3412' },
  { bg: '#f0f9ff', border: '#7dd3fc', color: '#075985' },
]

// ── Props ────────────────────────────────────────────────────────────────────

const props = defineProps({
  schedules:   { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  subjects:    { type: Array,  default: () => [] },
  classrooms:  { type: Array,  default: () => [] },
  sections:    { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
  dayConfigs:  { type: Object, default: () => ({}) },
  unplacedLoads: { type: Array, default: () => [] },
  capability:  { type: Object, default: () => ({ level: 'manage' }) },
  pageMode:    { type: String, default: 'admin' }, // 'admin' | 'my'
})

// ── Capability (manage = CID/admin, unit = AUH, self = own calendar only) ────

const isManage = computed(() => props.capability.level === 'manage')
const isSelf   = computed(() => props.capability.level === 'self')
const isMyPage = computed(() => props.pageMode === 'my')

const pageTitle = computed(() =>
  isMyPage.value ? 'My Faculty Schedule' : (isSelf.value ? 'My Schedule' : 'Class Schedules'))
const pageSubtitle = computed(() =>
  isMyPage.value
    ? 'Your weekly timetable — plotted classes are view-only; click a free slot to add your own blocks'
    : (isSelf.value
        ? 'Your weekly timetable — add non-teaching blocks to your free time'
        : 'Weekly timetable by section — click any empty slot to add'))

const NON_TEACHING_CATEGORIES = [
  { value: 'consultation', label: 'Consultation Hours' },
  { value: 'research',     label: 'Research Block' },
  { value: 'advising',     label: 'Student Advising' },
  { value: 'office_hours', label: 'Office Hours' },
  { value: 'meeting',      label: 'Meeting' },
  // Section-activity vocabulary — non-class entries plotted on a section's
  // calendar (usually section-wide, i.e. no faculty).
  { value: 'exam',         label: 'Exam' },
  { value: 'assembly',     label: 'Assembly' },
  { value: 'homeroom',     label: 'Homeroom' },
  { value: 'club_org',     label: 'Club / Org Time' },
  { value: 'event',        label: 'School Event' },
  { value: 'activity',     label: 'Activity' },
  { value: 'other',        label: 'Other' },
]

// ── Filters ──────────────────────────────────────────────────────────────────

const filters = reactive({
  term_id:    props.filters.term_id    ?? props.currentTerm?.id ?? null,
  section_id: props.filters.section_id ?? null,
  faculty_id: props.filters.faculty_id ?? null,
})

function applyFilters() {
  const target = isMyPage.value ? 'faculty-loading.my-schedule' : 'faculty-loading.schedules.index'
  router.get(route(target), filters, { preserveState: true })
}

// ── View mode (group calendar cards by section or by faculty) ────────────────

const viewBy = ref(
  props.capability.level === 'self' || props.filters.faculty_id ? 'faculty' : 'section'
)

const GRADE_LEVELS = [7, 8, 9, 10, 11, 12]
const gradeFilter = ref(null)

function setViewBy(mode) {
  if (viewBy.value === mode) return
  viewBy.value = mode
  if (mode === 'section') {
    filters.faculty_id = null
  } else if (mode === 'faculty') {
    filters.section_id = null
  } else {
    filters.section_id = null
    filters.faculty_id = null
  }
  applyFilters()
}

// ── Grouping ─────────────────────────────────────────────────────────────────

/** Schedules feeding the grouping logic — in "By Year Level" mode this is
 *  narrowed to elective sessions only (and optionally one grade), since that
 *  view exists to give a comprehensive cross-section elective overview. */
const displaySchedules = computed(() => {
  if (viewBy.value !== 'grade') return props.schedules
  return props.schedules.filter(s =>
    s.subject?.is_elective && (gradeFilter.value == null || s.grade_level === gradeFilter.value)
  )
})

/** Group key for a schedule row, depending on the active view mode. */
function groupKeyOf(s) {
  if (viewBy.value === 'grade') return s.grade_level
  return viewBy.value === 'faculty' ? (s.faculty?.id ?? 'unassigned') : s.section_id
}

/** { groupId: [schedules] } */
const byGroup = computed(() => {
  const map = {}
  for (const s of displaySchedules.value) {
    const k = groupKeyOf(s)
    if (!map[k]) map[k] = []
    map[k].push(s)
  }
  return map
})

/** Group IDs in display order (backend already sorted by grade + name + day + time) */
const groupsWithSchedules = computed(() => {
  const seen = []
  for (const s of displaySchedules.value) {
    const k = groupKeyOf(s)
    if (!seen.includes(k)) seen.push(k)
  }
  // Sections/faculty with unplaced loads but zero schedules yet still need a
  // calendar card to render so there's somewhere to drop the tray chip. Not
  // applicable in "By Year Level" mode — that view is read-only overview, no
  // drop target, and unplaced loads aren't elective-flagged here.
  if (viewBy.value !== 'grade') {
    for (const load of props.unplacedLoads) {
      const k = viewBy.value === 'faculty' ? (load.faculty?.id ?? 'unassigned') : load.section_id
      if (!seen.includes(k)) seen.push(k)
    }
  } else {
    seen.sort((a, b) => a - b)
  }
  return seen
})

/** Header info for a group card — falls back to an unplaced-load entry when
 *  the group has no schedules yet (brand-new section/faculty with no slots). */
function groupHeaderInfo(groupId) {
  const fromSchedule = byGroup.value[groupId]?.[0]
  if (fromSchedule) {
    return {
      grade_level:  fromSchedule.grade_level,
      section_name: fromSchedule.section_name,
      faculty_name: fromSchedule.faculty?.name ?? 'Unassigned / TBA',
    }
  }
  const fromLoad = props.unplacedLoads.find(l =>
    (viewBy.value === 'faculty' ? l.faculty?.id : l.section_id) === groupId
  )
  return {
    grade_level:  fromLoad?.grade_level,
    section_name: fromLoad?.section_name,
    faculty_name: fromLoad?.faculty?.name ?? 'Unassigned / TBA',
  }
}

/** { groupId: { day: [schedules] } } */
const byGroupDay = computed(() => {
  const map = {}
  for (const s of displaySchedules.value) {
    const k = groupKeyOf(s)
    if (!map[k]) map[k] = {}
    if (!map[k][s.day_of_week]) map[k][s.day_of_week] = []
    map[k][s.day_of_week].push(s)
  }
  return map
})

/** { day: [schedules] } — used by the drag-over conflict pre-check so it
 *  only scans the one day under the pointer instead of every schedule. */
const schedulesByDay = computed(() => {
  const map = {}
  for (const s of props.schedules) {
    if (!map[s.day_of_week]) map[s.day_of_week] = []
    map[s.day_of_week].push(s)
  }
  return map
})

/** Unique subjects for the legend of a given group */
function subjectsInGroup(groupId) {
  const seen = new Map()
  for (const s of (byGroup.value[groupId] ?? [])) {
    if (s.subject && !seen.has(s.subject.id)) seen.set(s.subject.id, s.subject)
  }
  return [...seen.values()]
}

/** Text shown inside an event block for the dimension that ISN'T the grouping axis. */
function secondaryLabel(s) {
  if (s.entry_type === 'non_teaching') {
    const cat = NON_TEACHING_CATEGORIES.find(c => c.value === s.category)?.label ?? 'Non-teaching'
    if (viewBy.value === 'faculty') return s.section_name ?? cat
    return s.faculty?.name ? lastNameOf(s.faculty.name) : cat
  }
  if (viewBy.value === 'grade') return `${s.section_name} · ${s.faculty?.name ? lastNameOf(s.faculty.name) : 'TBA'}`
  return viewBy.value === 'faculty'
    ? `G${s.grade_level} ${s.section_name}`
    : (s.faculty?.name ? lastNameOf(s.faculty.name) : 'TBA')
}

// ── Calendar helpers ─────────────────────────────────────────────────────────

function timeToMin(t) {
  if (!t) return 0
  const parts = t.split(':')
  return parseInt(parts[0]) * 60 + parseInt(parts[1])
}

/** Top offset in px for a given hour mark */
function hourTop(h) {
  return (h * 60 - CAL_START) * SCALE
}

/**
 * Assigns each event in a single day-column a lane (and the lane count for
 * its overlap cluster) so concurrent sessions render side-by-side instead of
 * stacked. Standard greedy interval-packing: sort by start time, give each
 * event the lowest-numbered lane whose previous occupant has already ended.
 * Returns Map(scheduleId -> { lane, totalLanes }).
 */
function packOverlaps(events) {
  const items = events
    .map(s => ({ id: s.id, start: timeToMin(s.start_time), end: timeToMin(s.end_time) }))
    .sort((a, b) => a.start - b.start || a.end - b.end)

  const result = new Map()
  let lanesEnd = []
  let cluster = []
  let clusterMaxLane = 0
  let clusterEnd = -Infinity

  const flush = () => {
    for (const id of cluster) result.get(id).totalLanes = clusterMaxLane + 1
    cluster = []
    clusterMaxLane = 0
    lanesEnd = []
  }

  for (const item of items) {
    if (item.start >= clusterEnd) {
      flush()
      clusterEnd = -Infinity
    }
    let lane = 0
    while (lane < lanesEnd.length && lanesEnd[lane] > item.start) lane++
    lanesEnd[lane] = item.end
    result.set(item.id, { lane, totalLanes: 1 })
    cluster.push(item.id)
    clusterMaxLane = Math.max(clusterMaxLane, lane)
    clusterEnd = Math.max(clusterEnd, item.end)
  }
  flush()
  return result
}

/** { groupId: { day: Map(scheduleId -> {lane, totalLanes}) } } — only built
 *  in "By Year Level" mode, where multiple sections' concurrent electives
 *  share the same day column and need side-by-side lanes. */
const lanePacking = computed(() => {
  const map = {}
  if (viewBy.value !== 'grade') return map
  for (const [groupId, days] of Object.entries(byGroupDay.value)) {
    map[groupId] = {}
    for (const [day, events] of Object.entries(days)) {
      map[groupId][day] = packOverlaps(events)
    }
  }
  return map
})

/** Absolute positioning style for a schedule event block. In "By Year Level"
 *  mode, horizontal position/width come from packOverlaps() so concurrent
 *  sections' electives sit in side-by-side lanes instead of overlapping. */
function eventStyle(s) {
  const sm = Math.max(timeToMin(s.start_time), CAL_START)
  const em = Math.min(timeToMin(s.end_time), CAL_END)
  const style = {
    position: 'absolute',
    top:    ((sm - CAL_START) * SCALE) + 'px',
    height: Math.max((em - sm) * SCALE, 24) + 'px',
  }

  if (viewBy.value === 'grade') {
    const pack  = lanePacking.value[groupKeyOf(s)]?.[s.day_of_week]?.get(s.id)
    const lane  = pack?.lane ?? 0
    const total = pack?.totalLanes ?? 1
    const pct   = 100 / total
    style.left  = `calc(${lane * pct}% + 1px)`
    style.width = `calc(${pct}% - 2px)`
  } else {
    style.left  = '2px'
    style.right = '2px'
  }

  return style
}

/** Absolute positioning style for a blocked-period overlay */
function blockedStyle(bp) {
  const sm = Math.max(timeToMin(bp.start), CAL_START)
  const em = Math.min(timeToMin(bp.end), CAL_END)
  return {
    position: 'absolute',
    top:    ((sm - CAL_START) * SCALE) + 'px',
    height: Math.max((em - sm) * SCALE, 4) + 'px',
    left: 0,
    right: 0,
  }
}

/** Inline style for subject-colored event block (cycles palette by subject_id) */
function subjectColorStyle(subjectId) {
  const p = PALETTE[(subjectId ?? 0) % PALETTE.length]
  return {
    backgroundColor: p.bg,
    borderColor:     p.border,
    color:           p.color,
  }
}

/** Event color — non-teaching blocks get a fixed neutral slate look so they
 *  read as "reserved time", visually distinct from subject-colored classes. */
function eventColorStyle(s) {
  if (s.entry_type === 'non_teaching') {
    return { backgroundColor: '#f1f5f9', borderColor: '#94a3b8', color: '#334155' }
  }
  return subjectColorStyle(s.subject?.id)
}

// ── Drag and drop ────────────────────────────────────────────────────────────

const DRAG_SNAP_MIN = 5

/** dragPayload: { kind: 'move', schedule, groupId } | { kind: 'place', load } */
const dragPayload = ref(null)
/** Live drop preview for the column currently under the pointer */
const dropTarget  = ref(null)
const dragBusy     = ref(false)

function canDrag(s) {
  return viewBy.value !== 'grade' && s.status !== 'cancelled' && !!s.can_edit
}

/** Blocks the modal for rows the user can't touch (e.g. faculty viewing own classes). */
function onEventClick(s) {
  if (!s.can_edit) return
  openForm(s)
}

function schoolYearIdForTerm(termId) {
  // filters.term_id can be a string when it comes straight from the URL query
  // string (Request::only() doesn't cast it), while terms[].id is numeric —
  // compare loosely so the lookup doesn't silently miss.
  return props.terms.find(t => String(t.id) === String(termId))?.school_year_id ?? null
}

function minutesFromPointerY(clientY, rect) {
  const y = clientY - rect.top
  let min = CAL_START + Math.round(y / SCALE)
  min = Math.round(min / DRAG_SNAP_MIN) * DRAG_SNAP_MIN
  return Math.max(CAL_START, Math.min(CAL_END, min))
}

function minToTime(min) {
  const h = Math.floor(min / 60)
  const m = min % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function durationOf(s) {
  return timeToMin(s.end_time) - timeToMin(s.start_time)
}

function timesOverlapLocal(aStart, aEnd, bStart, bEnd) {
  return timeToMin(aStart) < timeToMin(bEnd) && timeToMin(aEnd) > timeToMin(bStart)
}

function isTbaName(name) {
  return !!name && name.startsWith('TBA')
}

/**
 * Instant client-side overlap pre-check against the schedules already on the
 * page — mirrors ConflictDetectionService's three axes (faculty/room/section)
 * for live highlighting while dragging. The authoritative check still happens
 * server-side via /schedules/validate before anything is committed.
 */
function findLiveConflicts({ facultyId, facultyName, classroomId, sectionId, day, startTime, endTime, excludeId }) {
  let faculty = null, room = null, section = null
  for (const s of (schedulesByDay.value[day] ?? [])) {
    if (s.id === excludeId || s.status === 'cancelled') continue
    if (!timesOverlapLocal(startTime, endTime, s.start_time, s.end_time)) continue

    if (!faculty && facultyId && s.faculty?.id === facultyId && !isTbaName(facultyName)) faculty = s
    if (!room && classroomId && s.classroom?.id === classroomId) room = s
    if (!section && sectionId && s.section_id === sectionId) section = s
  }
  return { faculty, room, section }
}

function dropTargetsEqual(a, b) {
  if (!a || !b) return a === b
  return a.groupId === b.groupId && a.day === b.day && a.startMin === b.startMin
    && a.endMin === b.endMin && a.hasConflict === b.hasConflict && a.message === b.message
}

/** Per-column memo key for v-memo — only the column actually under the
 *  pointer should re-render when dropTarget changes. */
function dropPreviewKey(groupId, day) {
  const d = dropTarget.value
  if (!d || d.groupId !== groupId || d.day !== day) return 'none'
  return `${d.startMin}-${d.endMin}-${d.hasConflict}-${d.message ?? ''}`
}

/** Per-column memo key for v-memo — only the column holding the dragged-from
 *  event needs to re-render to apply the dimmed/opacity style. */
function dragDimKey(groupId, day) {
  if (dragPayload.value?.kind !== 'move') return null
  const s = dragPayload.value.schedule
  return (groupKeyOf(s) === groupId && s.day_of_week === day) ? s.id : null
}

function onDragStartEvent(e, s) {
  if (!canDrag(s)) { e.preventDefault(); return }
  dragPayload.value = { kind: 'move', schedule: s }
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', 'schedule:' + s.id)
}

function onDragStartLoad(e, load) {
  if (load.is_locked) { e.preventDefault(); return }
  dragPayload.value = { kind: 'place', load }
  e.dataTransfer.effectAllowed = 'copy'
  e.dataTransfer.setData('text/plain', 'load:' + load.load_assignment_id)
}

// rAF-throttled drag-over processing: native `dragover` can fire dozens of
// times per second, so the actual conflict-check work only runs once per
// animation frame, using whatever the latest pointer position was.
let dragFrame = null
let pendingDragOver = null
// Column geometry never moves during a drag — cache each column's rect for
// the duration of the drag instead of forcing a layout read on every event.
const columnRectCache = new Map()

function onDragEnd() {
  dragPayload.value = null
  dropTarget.value  = null
  pendingDragOver = null
  if (dragFrame) {
    cancelAnimationFrame(dragFrame)
    dragFrame = null
  }
  columnRectCache.clear()
}

function onDragOverColumn(e, groupId, day) {
  if (viewBy.value === 'grade') return
  if (!dragPayload.value) return
  pendingDragOver = { clientY: e.clientY, columnEl: e.currentTarget, groupId, day }
  if (dragFrame) return
  dragFrame = requestAnimationFrame(processPendingDragOver)
}

function processPendingDragOver() {
  dragFrame = null
  const pending = pendingDragOver
  pendingDragOver = null
  if (!pending || !dragPayload.value) return
  const { clientY, columnEl, groupId, day } = pending

  let rect = columnRectCache.get(columnEl)
  if (!rect) {
    rect = columnEl.getBoundingClientRect()
    columnRectCache.set(columnEl, rect)
  }

  const startMin = minutesFromPointerY(clientY, rect)
  const isMove   = dragPayload.value.kind === 'move'
  const item     = isMove ? dragPayload.value.schedule : dragPayload.value.load
  const duration = isMove ? durationOf(item) : 60
  const endMin   = Math.min(CAL_END, startMin + duration)
  const startTime = minToTime(startMin)
  const endTime   = minToTime(endMin)

  const conflicts = findLiveConflicts({
    facultyId:   item.faculty?.id ?? null,
    facultyName: item.faculty?.name,
    classroomId: isMove ? (item.classroom?.id ?? null) : null,
    sectionId:   item.section_id,
    day,
    startTime,
    endTime,
    excludeId: isMove ? item.id : null,
  })
  const hit   = conflicts.faculty || conflicts.section || conflicts.room
  const label = conflicts.faculty ? `Faculty busy: ${conflicts.faculty.subject?.code ?? ''}`
    : conflicts.section ? `Section busy: ${conflicts.section.subject?.code ?? ''}`
    : conflicts.room ? `Room busy: ${conflicts.room.subject?.code ?? ''}`
    : null

  const candidate = { groupId, day, startMin, endMin, hasConflict: !!hit, message: label }
  if (!dropTargetsEqual(candidate, dropTarget.value)) {
    dropTarget.value = candidate
  }
}

function dropPreviewStyle() {
  if (!dropTarget.value) return {}
  const sm = Math.max(dropTarget.value.startMin, CAL_START)
  const em = Math.min(dropTarget.value.endMin, CAL_END)
  return {
    position: 'absolute',
    top:    ((sm - CAL_START) * SCALE) + 'px',
    height: Math.max((em - sm) * SCALE, 16) + 'px',
    left:   '2px',
    right:  '2px',
  }
}

async function onDropColumn(e, groupId, day) {
  if (viewBy.value === 'grade') return
  const payload = dragPayload.value
  const target  = dropTarget.value
  dragPayload.value = null
  dropTarget.value  = null
  if (!payload || !target) return

  const startTime = minToTime(target.startMin)
  const endTime   = minToTime(target.endMin)

  if (payload.kind === 'move') {
    await commitMove(payload.schedule, day, startTime, endTime)
  } else {
    openPlaceForm(payload.load, day, startTime, endTime)
  }
}

async function commitMove(schedule, day, startTime, endTime) {
  if (dragBusy.value) return
  if (schedule.day_of_week === day
      && schedule.start_time?.slice(0, 5) === startTime
      && schedule.end_time?.slice(0, 5) === endTime) {
    return
  }

  dragBusy.value = true
  try {
    const { data: result } = await axios.post(route('faculty-loading.schedules.validate'), {
      entry_type:       schedule.entry_type ?? 'class',
      faculty_id:       schedule.faculty?.id   ?? 0,
      subject_id:       schedule.subject?.id   ?? 0,
      section_id:       schedule.section_id,
      classroom_id:     schedule.classroom?.id ?? 0,
      academic_term_id: filters.term_id,
      day_of_week:      day,
      start_time:       startTime,
      end_time:         endTime,
      exclude_id:       schedule.id,
    })

    if (result.errors?.length) {
      await Swal.fire({
        icon: 'error',
        title: 'Cannot move schedule',
        html: `<ul class="text-left text-sm">${result.errors.map(m => `<li>• ${m}</li>`).join('')}</ul>`,
      })
      return
    }
    if (result.warnings?.length) {
      const confirmed = await Swal.fire({
        icon: 'warning',
        title: 'Move with warnings?',
        html: `<ul class="text-left text-sm">${result.warnings.map(m => `<li>• ${m}</li>`).join('')}</ul>`,
        showCancelButton: true,
        confirmButtonText: 'Move anyway',
      })
      if (!confirmed.isConfirmed) return
    }

    // Inertia's router.put() (not a raw axios.put) — Laravel's back() redirect
    // after a PUT must be auto-upgraded to 303 by Inertia's own middleware
    // (it only does this for requests carrying the X-Inertia header), otherwise
    // browsers re-issue the redirect as PUT against a GET-only route and 405.
    await new Promise((resolve, reject) => {
      router.put(route('faculty-loading.schedules.update', schedule.id), {
        title:            schedule.title ?? '',
        category:         schedule.category ?? '',
        faculty_id:       schedule.faculty?.id   ?? null,
        subject_id:       schedule.subject?.id   ?? null,
        section_id:       schedule.section_id,
        classroom_id:     schedule.classroom?.id ?? null,
        school_year_id:   schedule.school_year_id,
        academic_term_id: filters.term_id,
        day_of_week:      day,
        start_time:       startTime,
        end_time:         endTime,
        status:           schedule.status,
        remarks:          schedule.remarks ?? '',
        force:            true,
      }, {
        preserveScroll: true,
        onSuccess: () => resolve(),
        onError:   (errors) => reject(errors),
      })
    })
  } catch (errors) {
    const message = errors && typeof errors === 'object'
      ? Object.values(errors).flat().join('\n')
      : 'Failed to move schedule.'
    await Swal.fire('Error', message, 'error')
  } finally {
    dragBusy.value = false
  }
}

// ── Click/drag-to-create (Google Calendar-style) ─────────────────────────────
//
// Mousedown on an empty slot anchors a ghost event (15-min snap); dragging
// vertically stretches it; a plain click defaults to one hour. Releasing opens
// a quick-create popover (Save / More options), Esc or click-away cancels.

const CREATE_SNAP_MIN    = 15
const DEFAULT_CREATE_MIN = 60

const createDraft = ref(null) // { groupId, day, rect, anchorMin, startMin, endMin, moved, startClientY }
const quickCreate = ref(null) // { x, y, groupId, day, allowClass, sectionPinned, facultyPinned }
const qcEl        = ref(null)
const qcValidation = ref(null)
const qc = reactive({
  mode: 'class', // 'class' | 'block' (block = non-teaching / section activity)
  title: '', category: '',
  subject_id: null, faculty_id: null, section_id: null, classroom_id: null,
  start_time: '', end_time: '',
  saving: false,
})

// Swallows the column-mousedown that immediately follows a click-away close,
// so dismissing the popover doesn't instantly start a new ghost.
let suppressCreateOnce = false

function canQuickCreate(groupId) {
  if (viewBy.value === 'grade') return false
  if (isManage.value) return true
  // unit/self reach: own-calendar columns only (props.faculty is already
  // server-filtered to the reachable set). Section-wide adds are manage-only.
  return viewBy.value === 'faculty' && props.faculty.some(f => String(f.id) === String(groupId))
}

function snapMin(min) {
  return Math.round(min / CREATE_SNAP_MIN) * CREATE_SNAP_MIN
}

function columnMinAt(clientY, rect) {
  const raw = CAL_START + (clientY - rect.top) / SCALE
  return Math.max(CAL_START, Math.min(CAL_END, snapMin(raw)))
}

function onColumnMouseDown(e, groupId, day) {
  if (e.button !== 0 || suppressCreateOnce) return
  if (quickCreate.value) { closeQuickCreate(); return }
  if (dragPayload.value || dragBusy.value) return
  if (!canQuickCreate(groupId)) return
  if (e.target.closest('[data-evt]')) return // clicking an event edits it instead

  e.preventDefault() // no text selection while stretching the ghost
  const rect   = e.currentTarget.getBoundingClientRect()
  const anchor = Math.min(columnMinAt(e.clientY, rect), CAL_END - CREATE_SNAP_MIN)
  createDraft.value = {
    groupId, day, rect,
    anchorMin: anchor,
    startMin:  anchor,
    endMin:    anchor + CREATE_SNAP_MIN,
    moved: false,
    startClientY: e.clientY,
  }
  window.addEventListener('mousemove', onCreateDragMove)
  window.addEventListener('mouseup', onCreateDragEnd)
}

function onCreateDragMove(e) {
  const d = createDraft.value
  if (!d) return
  if (!d.moved && Math.abs(e.clientY - d.startClientY) > 6) d.moved = true
  if (!d.moved) return
  const cur = columnMinAt(e.clientY, d.rect)
  if (cur >= d.anchorMin) {
    d.startMin = d.anchorMin
    d.endMin   = Math.max(cur, d.anchorMin + CREATE_SNAP_MIN)
  } else {
    d.startMin = Math.min(cur, d.anchorMin - CREATE_SNAP_MIN)
    d.endMin   = d.anchorMin
  }
}

function onCreateDragEnd(e) {
  window.removeEventListener('mousemove', onCreateDragMove)
  window.removeEventListener('mouseup', onCreateDragEnd)
  const d = createDraft.value
  if (!d) return
  if (!d.moved) {
    // Plain click — default one hour, clamped to the calendar bottom.
    d.endMin   = Math.min(d.anchorMin + DEFAULT_CREATE_MIN, CAL_END)
    d.startMin = Math.min(d.anchorMin, d.endMin - CREATE_SNAP_MIN)
  }
  openQuickCreate(e, d)
}

/** Per-column memo key for v-memo — only the column holding the ghost re-renders. */
function createGhostKey(groupId, day) {
  const d = createDraft.value
  if (!d || d.groupId !== groupId || d.day !== day) return 'none'
  return `${d.startMin}-${d.endMin}`
}

function createGhostStyle() {
  const d = createDraft.value
  if (!d) return {}
  return {
    top:    ((d.startMin - CAL_START) * SCALE) + 'px',
    height: (Math.max(d.endMin - d.startMin, CREATE_SNAP_MIN) * SCALE) + 'px',
  }
}

function openQuickCreate(e, d) {
  const allowClass    = isManage.value
  const sectionPinned = viewBy.value === 'section' ? Number(d.groupId) : null
  const facultyPinned = viewBy.value === 'faculty' && String(d.groupId) !== 'tba' ? Number(d.groupId) : null

  Object.assign(qc, {
    mode: allowClass ? 'class' : 'block',
    title: '', category: '',
    subject_id:   null,
    faculty_id:   facultyPinned,
    section_id:   sectionPinned,
    classroom_id: null,
    start_time:   minToTime(d.startMin),
    end_time:     minToTime(d.endMin),
    saving: false,
  })
  qcValidation.value = null

  const W = 344, H = 460
  quickCreate.value = {
    x: Math.min(Math.max(e.clientX + 10, 8), window.innerWidth  - W - 8),
    y: Math.min(Math.max(e.clientY - 60, 8), window.innerHeight - H - 8),
    groupId: d.groupId, day: d.day,
    allowClass, sectionPinned, facultyPinned,
  }
  window.addEventListener('mousedown', onWindowMouseDownQc, true)
  window.addEventListener('keydown', onQcKeydown)
  nextTick(runQuickValidate)
}

const qcBlockLabel = computed(() =>
  viewBy.value === 'section' ? 'Section Activity' : 'Non-teaching')

function setQcMode(mode) {
  qc.mode = mode
  qc.faculty_id = mode === 'block' && viewBy.value === 'section'
    ? null // section-wide by default; assignable in the form below
    : (quickCreate.value?.facultyPinned ?? qc.faculty_id)
  runQuickValidate()
}

function closeQuickCreate() {
  quickCreate.value  = null
  createDraft.value  = null
  qcValidation.value = null
  clearTimeout(qcValidateTimer)
  window.removeEventListener('mousedown', onWindowMouseDownQc, true)
  window.removeEventListener('keydown', onQcKeydown)
}

function onWindowMouseDownQc(e) {
  if (qcEl.value && qcEl.value.contains(e.target)) return
  closeQuickCreate()
  suppressCreateOnce = true
  setTimeout(() => { suppressCreateOnce = false }, 0)
}

function onQcKeydown(e) {
  if (e.key === 'Escape') closeQuickCreate()
}

// Ghost tracks manual time edits in the popover.
watch(() => [qc.start_time, qc.end_time], () => {
  if (!quickCreate.value || !createDraft.value) return
  const s = qc.start_time ? timeToMin(qc.start_time) : null
  const en = qc.end_time  ? timeToMin(qc.end_time)  : null
  if (s !== null && en !== null && en > s) {
    createDraft.value.startMin = s
    createDraft.value.endMin   = en
  }
  runQuickValidate()
})

let qcValidateTimer = null
function runQuickValidate() {
  if (!quickCreate.value) return
  clearTimeout(qcValidateTimer)
  qcValidateTimer = setTimeout(async () => {
    if (!quickCreate.value || !filters.term_id || !qc.start_time || !qc.end_time) return
    const entryType = qc.mode === 'class' ? 'class' : 'non_teaching'
    if (entryType === 'class' && !qc.faculty_id) { qcValidation.value = null; return }
    if (entryType === 'non_teaching' && !qc.faculty_id && !qc.section_id) { qcValidation.value = null; return }
    try {
      const { data } = await axios.post(route('faculty-loading.schedules.validate'), {
        entry_type:       entryType,
        faculty_id:       qc.faculty_id,
        subject_id:       qc.subject_id   ?? 0,
        section_id:       qc.section_id   ?? 0,
        classroom_id:     qc.classroom_id ?? 0,
        academic_term_id: filters.term_id,
        day_of_week:      quickCreate.value.day,
        start_time:       qc.start_time,
        end_time:         qc.end_time,
        exclude_id:       null,
      })
      qcValidation.value = data
    } catch { /* validation display is best-effort; store() re-validates */ }
  }, 250)
}

const qcCanSave = computed(() => {
  if (!quickCreate.value || qc.saving) return false
  if (qcValidation.value?.errors?.length) return false
  if (!qc.start_time || !qc.end_time || qc.end_time <= qc.start_time) return false
  if (qc.mode === 'class') {
    return !!(qc.subject_id && qc.faculty_id && qc.section_id && qc.classroom_id)
  }
  return !!qc.title.trim() && !!(qc.faculty_id || qc.section_id)
})

function saveQuickCreate() {
  if (!qcCanSave.value) return
  qc.saving = true
  const base = {
    faculty_id:       qc.faculty_id,
    section_id:       qc.section_id,
    classroom_id:     qc.classroom_id,
    school_year_id:   schoolYearIdForTerm(filters.term_id),
    academic_term_id: filters.term_id,
    day_of_week:      quickCreate.value.day,
    start_time:       qc.start_time,
    end_time:         qc.end_time,
    status:           'active',
    remarks:          '',
  }
  const payload = qc.mode === 'class'
    ? { ...base, subject_id: qc.subject_id, force: true }
    : { ...base, entry_type: 'non_teaching', title: qc.title.trim(), category: qc.category || null }

  router.post(route('faculty-loading.schedules.store'), payload, {
    preserveScroll: true,
    onSuccess: () => closeQuickCreate(),
    onError:   (errors) => {
      qcValidation.value = { errors: Object.values(errors).flat(), warnings: [] }
    },
    onFinish: () => { qc.saving = false },
  })
}

/** Hand the popover state to the full modal for the long-form fields. */
function qcMoreOptions() {
  const d = quickCreate.value
  validationResult.value = null
  form.reset()
  Object.assign(form, {
    id: null,
    entry_type:         qc.mode === 'class' ? 'class' : 'non_teaching',
    title:              qc.title,
    category:           qc.category,
    load_assignment_id: null,
    faculty_id:         qc.faculty_id,
    subject_id:         qc.subject_id,
    section_id:         qc.section_id,
    classroom_id:       qc.classroom_id,
    school_year_id:     schoolYearIdForTerm(filters.term_id),
    academic_term_id:   filters.term_id,
    day_of_week:        d.day,
    start_time:         qc.start_time,
    end_time:           qc.end_time,
    status:             'active',
    remarks:            '',
    force:              false,
  })
  closeQuickCreate()
  modal.value = true
  checkConflicts()
}

function sectionLabel(id) {
  const s = props.sections.find(x => String(x.id) === String(id))
  return s ? `Grade ${s.levelid} — ${s.sectionname}` : `Section ${id}`
}

onBeforeUnmount(() => {
  window.removeEventListener('mousemove', onCreateDragMove)
  window.removeEventListener('mouseup', onCreateDragEnd)
  window.removeEventListener('mousedown', onWindowMouseDownQc, true)
  window.removeEventListener('keydown', onQcKeydown)
  clearTimeout(qcValidateTimer)
})

/** Open the edit modal prefilled from a dragged "unplaced subjects" tray chip. */
function openPlaceForm(load, day, startTime, endTime) {
  validationResult.value = null
  form.reset()
  Object.assign(form, {
    id:                 null,
    load_assignment_id: load.load_assignment_id,
    faculty_id:         load.faculty?.id ?? null,
    subject_id:         load.subject?.id ?? null,
    section_id:         load.section_id,
    classroom_id:       null,
    school_year_id:     schoolYearIdForTerm(filters.term_id),
    academic_term_id:   filters.term_id,
    day_of_week:        day,
    start_time:         startTime,
    end_time:           endTime,
    status:             'active',
    remarks:            '',
    force:              false,
  })
  modal.value = true
  checkConflicts()
}

// ── Formatters ───────────────────────────────────────────────────────────────

function fmtTime(t) {
  if (!t) return '—'
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  return `${hour % 12 || 12}:${m} ${hour >= 12 ? 'PM' : 'AM'}`
}

/** Format HH:MM:SS → h:MM AM/PM for day config display */
function fmtConfigTime(t) {
  if (!t) return ''
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  return `${hour % 12 || 12}:${m}${hour >= 12 ? 'PM' : 'AM'}`
}

/** Extract surname for compact display in event blocks */
function lastNameOf(name) {
  if (!name) return ''
  const parts = name.trim().split(' ')
  return parts[parts.length - 1]
}

// ── Form & modal ─────────────────────────────────────────────────────────────

const modal            = ref(false)
const validationResult = ref(null)

const form = useForm({
  id: null, entry_type: 'class', title: '', category: '',
  load_assignment_id: null, faculty_id: null, subject_id: null, section_id: null, classroom_id: null,
  school_year_id: null, academic_term_id: null, day_of_week: '',
  start_time: '', end_time: '', status: 'active', remarks: '', force: false,
})

// School year is fully derivable from the term — the old visible dropdown was
// bound to term ids (a different id-space than school_years) and submitted
// wrong values when touched. Now auto-derived, no user-facing field.
watch(() => form.academic_term_id, (termId) => {
  form.school_year_id = schoolYearIdForTerm(termId)
})

const modalTitle = computed(() => {
  const action = form.id ? 'Edit' : (form.entry_type === 'non_teaching' ? 'Add' : 'Assign')
  return `${action} ${form.entry_type === 'non_teaching' ? 'Non-teaching Block' : 'Schedule'}`
})

/**
 * Open the form modal.
 * - Pass a full schedule object (with s.id) to edit.
 * - Pass { section_id } to pre-fill a new-schedule form for that section.
 * - Pass nothing to open a blank new-schedule form.
 */
function openForm(s = null) {
  validationResult.value = null
  if (s && s.id) {
    Object.assign(form, {
      id:                 s.id,
      entry_type:         s.entry_type ?? 'class',
      title:              s.title ?? '',
      category:           s.category ?? '',
      load_assignment_id: s.load_assignment_id ?? null,
      faculty_id:         s.faculty?.id      ?? null,
      subject_id:         s.subject?.id      ?? null,
      section_id:         s.section_id,
      classroom_id:       s.classroom?.id    ?? null,
      school_year_id:     s.school_year_id ?? schoolYearIdForTerm(filters.term_id),
      academic_term_id:   filters.term_id    ?? null,
      day_of_week:        s.day_of_week,
      start_time:         s.start_time?.slice(0, 5) ?? '',
      end_time:           s.end_time?.slice(0, 5)   ?? '',
      status:             s.status,
      remarks:            s.remarks ?? '',
      force:              false,
    })
  } else {
    form.reset()
    form.id               = null
    form.entry_type       = 'class'
    form.status           = 'active'
    form.force            = false
    form.academic_term_id = filters.term_id    ?? null
    form.school_year_id   = schoolYearIdForTerm(filters.term_id)
    form.section_id       = s?.section_id ?? filters.section_id ?? null
  }
  modal.value = true
}

/** Blank form for a new non-teaching block. Self mode pins faculty to the user. */
function openNonTeachingForm() {
  validationResult.value = null
  form.reset()
  form.id               = null
  form.entry_type       = 'non_teaching'
  form.status           = 'active'
  form.force            = false
  form.academic_term_id = filters.term_id ?? null
  form.school_year_id   = schoolYearIdForTerm(filters.term_id)
  form.faculty_id       = isSelf.value || props.faculty.length === 1
    ? (props.faculty[0]?.id ?? null)
    : (filters.faculty_id ?? null)
  form.section_id       = filters.section_id ?? null
  modal.value = true
}

function removeNonTeaching() {
  if (!form.id) return
  Swal.fire({
    icon: 'warning',
    title: 'Remove this block?',
    text: 'The non-teaching block will be removed from the calendar.',
    showCancelButton: true,
    confirmButtonText: 'Remove',
    confirmButtonColor: '#dc2626',
  }).then((res) => {
    if (!res.isConfirmed) return
    router.delete(route('faculty-loading.schedules.destroy', form.id), {
      preserveScroll: true,
      onSuccess: () => { modal.value = false; validationResult.value = null },
    })
  })
}

async function checkConflicts() {
  if (!form.academic_term_id || !form.day_of_week || !form.start_time || !form.end_time) return
  if (form.entry_type !== 'non_teaching' && !form.faculty_id) return
  if (form.entry_type === 'non_teaching' && !form.faculty_id && !form.section_id) return
  const payload = {
    entry_type:       form.entry_type,
    faculty_id:       form.faculty_id,
    subject_id:       form.subject_id   ?? 0,
    section_id:       form.section_id   ?? 0,
    classroom_id:     form.classroom_id ?? 0,
    academic_term_id: form.academic_term_id,
    day_of_week:      form.day_of_week,
    start_time:       form.start_time,
    end_time:         form.end_time,
    exclude_id:       form.id,
  }
  try {
    const { data } = await axios.post(route('faculty-loading.schedules.validate'), payload)
    validationResult.value = data
  } catch (e) {
    console.error(e)
  }
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.schedules.update', form.id), {
      onSuccess: () => { modal.value = false; validationResult.value = null },
    })
  } else {
    form.post(route('faculty-loading.schedules.store'), {
      onSuccess: () => { modal.value = false; validationResult.value = null },
    })
  }
}
</script>
