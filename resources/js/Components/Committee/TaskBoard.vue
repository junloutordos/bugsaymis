<script setup>
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import AppButton from "@/Components/AppButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { useSubmit } from "@/Composables/useSubmit"
import {
  PlusIcon, TrashIcon, PencilSquareIcon, ChatBubbleLeftRightIcon,
  Squares2X2Icon, TableCellsIcon, CalendarDaysIcon, UserCircleIcon,
} from "@heroicons/vue/24/outline"

const props = defineProps({
  committee:        { type: Object, required: true }, // may include sub_committees
  tasks:            { type: Array, default: () => [] },
  boardMembers:     { type: Array, default: () => [] },
  ratingPeriods:    { type: Array, default: () => [] },
  selectedPeriodId: { type: Number, default: null },
  canManageBoard:   { type: Boolean, default: false },
  currentUserId:    { type: Number, required: true },
  plans:            { type: Array, default: () => [] }, // [{id, success_indicator}]
})

const { isSubmitting, submit } = useSubmit()

// ── Status / priority definitions (monday.com defaults) ────────────────────
const STATUSES = [
  { key: "not_started",   label: "Not Started",   badge: "slate" },
  { key: "working_on_it", label: "Working on it", badge: "amber" },
  { key: "stuck",         label: "Stuck",         badge: "red" },
  { key: "done",          label: "Done",          badge: "green" },
]
const PRIORITIES = [
  { key: "low",    label: "Low",    badge: "slate" },
  { key: "medium", label: "Medium", badge: "blue" },
  { key: "high",   label: "High",   badge: "amber" },
  { key: "urgent", label: "Urgent", badge: "red" },
]
const statusDef   = (key) => STATUSES.find(s => s.key === key) ?? STATUSES[0]
const priorityDef = (key) => PRIORITIES.find(p => p.key === key) ?? PRIORITIES[1]
const railColor = {
  not_started: "border-l-slate-300",
  working_on_it: "border-l-amber-400",
  stuck: "border-l-red-400",
  done: "border-l-emerald-400",
}

// ── View & filters ──────────────────────────────────────────────────────────
const view = ref("table") // table | kanban
const filterAssignee = ref("")
const filterStatus = ref("")
const myTasksOnly = ref(false)

const changePeriod = (periodId) => {
  router.get(window.location.pathname, periodId ? { rating_period_id: periodId } : {}, {
    preserveScroll: true, preserveState: true, only: undefined,
  })
}

const filteredTasks = computed(() => props.tasks.filter(t => {
  if (myTasksOnly.value && !t.assignees?.some(a => a.id === props.currentUserId)) return false
  if (filterAssignee.value && !t.assignees?.some(a => a.id === Number(filterAssignee.value))) return false
  if (filterStatus.value && t.status !== filterStatus.value) return false
  return true
}))

const tasksByStatus = computed(() => {
  const groups = {}
  for (const s of STATUSES) groups[s.key] = []
  for (const t of filteredTasks.value) (groups[t.status] ?? groups.not_started).push(t)
  return groups
})

// ── Progress rollups ────────────────────────────────────────────────────────
const progress = computed(() => {
  const total = filteredTasks.value.length
  const done  = filteredTasks.value.filter(t => t.status === "done").length
  return { total, done, pct: total ? Math.round((done / total) * 100) : 0 }
})

const memberProgress = computed(() => {
  const map = {}
  for (const t of filteredTasks.value) {
    for (const a of (t.assignees ?? [])) {
      map[a.id] ??= { name: a.name, total: 0, done: 0 }
      map[a.id].total++
      if (t.status === "done") map[a.id].done++
    }
  }
  return Object.values(map).sort((a, b) => b.total - a.total)
})

const isOverdue = (t) => t.due_date && t.status !== "done" && new Date(t.due_date) < new Date(new Date().toDateString())
const fmtDate = (d) => d ? new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric" }) : null
const initials = (name) => (name ?? "").split(" ").filter(Boolean).slice(0, 2).map(w => w[0]).join("").toUpperCase()

const canWorkTask = (t) => props.canManageBoard || t.assignees?.some(a => a.id === props.currentUserId)

// ── Status change (pill dropdown + kanban drop share the endpoint) ─────────
const openStatusPicker = ref(null) // task id
const setStatus = (task, status) => {
  openStatusPicker.value = null
  if (task.status === status || !canWorkTask(task)) return
  submit.put(route("committee-tasks.status", task.id), { status }, { preserveScroll: true, resetOnSuccess: true })
}

// ── Kanban drag (native HTML5, same pattern as FL Schedules) ────────────────
const dragTaskId = ref(null)
const dragOverCol = ref(null)
const onDragStart = (task) => { if (canWorkTask(task)) dragTaskId.value = task.id }
const onDropColumn = (statusKey) => {
  const task = props.tasks.find(t => t.id === dragTaskId.value)
  dragOverCol.value = null
  if (task) setStatus(task, statusKey)
  dragTaskId.value = null
}

// ── Task modal (create / edit) ──────────────────────────────────────────────
const showTaskModal = ref(false)
const editingTask = ref(null)
const taskForm = ref({})
const emptyTaskForm = (status = "not_started") => ({
  title: "", description: "", status, priority: "medium", due_date: "",
  rating_period_id: props.selectedPeriodId, work_distribution_plan_id: "",
  assignee_ids: props.canManageBoard ? [] : [props.currentUserId],
})
const openCreate = (status = "not_started") => {
  editingTask.value = null
  taskForm.value = emptyTaskForm(status)
  showTaskModal.value = true
}
const openEdit = (task) => {
  editingTask.value = task
  taskForm.value = {
    title: task.title, description: task.description ?? "", status: task.status,
    priority: task.priority, due_date: task.due_date ?? "",
    rating_period_id: task.rating_period_id, work_distribution_plan_id: task.work_distribution_plan_id ?? "",
    assignee_ids: (task.assignees ?? []).map(a => a.id),
  }
  showTaskModal.value = true
}
const toggleAssignee = (id) => {
  const ix = taskForm.value.assignee_ids.indexOf(id)
  ix >= 0 ? taskForm.value.assignee_ids.splice(ix, 1) : taskForm.value.assignee_ids.push(id)
}
const saveTask = () => {
  const payload = {
    ...taskForm.value,
    due_date: taskForm.value.due_date || null,
    work_distribution_plan_id: taskForm.value.work_distribution_plan_id || null,
    rating_period_id: taskForm.value.rating_period_id || null,
  }
  const opts = { preserveScroll: true, resetOnSuccess: true, onSuccess: () => { showTaskModal.value = false } }
  editingTask.value
    ? submit.put(route("committee-tasks.update", editingTask.value.id), payload, opts)
    : submit.post(route("committee-tasks.store", props.committee.id), payload, opts)
}
const deleteTask = (task) => {
  if (!confirm(`Remove "${task.title}" from the board?`)) return
  submit.delete(route("committee-tasks.destroy", task.id), { preserveScroll: true, resetOnSuccess: true, onSuccess: () => { showDrawer.value = false } })
}

// ── Task drawer (details + updates feed) ────────────────────────────────────
const showDrawer = ref(false)
const drawerTask = ref(null)
const updateBody = ref("")
const openDrawer = (task) => { drawerTask.value = task; showDrawer.value = true }
const postUpdate = () => {
  if (!updateBody.value.trim()) return
  submit.post(route("committee-tasks.updates.store", drawerTask.value.id), { body: updateBody.value }, {
    preserveScroll: true, resetOnSuccess: true,
    onSuccess: () => { updateBody.value = "" },
  })
}
</script>

<template>
  <div class="space-y-4">

    <!-- Toolbar: progress + filters + view toggle -->
    <div class="flex flex-wrap items-center gap-3">
      <!-- Committee progress battery -->
      <div class="flex items-center gap-2 min-w-44">
        <div class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: progress.pct + '%' }" />
        </div>
        <span class="text-xs font-semibold text-slate-600 whitespace-nowrap">{{ progress.done }}/{{ progress.total }} done</span>
      </div>

      <div class="flex-1" />

      <AppSelect :model-value="selectedPeriodId ? String(selectedPeriodId) : ''" class="w-52"
        @update:model-value="changePeriod($event || null)">
        <option value="">All rating periods</option>
        <option v-for="p in ratingPeriods" :key="p.id" :value="String(p.id)">{{ p.label }}{{ p.is_current ? ' (current)' : '' }}</option>
      </AppSelect>

      <AppSelect v-model="filterAssignee" class="w-44">
        <option value="">All assignees</option>
        <option v-for="m in boardMembers" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
      </AppSelect>

      <AppSelect v-model="filterStatus" class="w-40">
        <option value="">All statuses</option>
        <option v-for="s in STATUSES" :key="s.key" :value="s.key">{{ s.label }}</option>
      </AppSelect>

      <button type="button"
        :class="['px-3 py-2 rounded-lg text-sm font-medium border', myTasksOnly ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50']"
        @click="myTasksOnly = !myTasksOnly">
        My tasks
      </button>

      <div class="flex rounded-lg border border-slate-200 overflow-hidden">
        <button type="button" :class="['px-3 py-2 text-sm flex items-center gap-1.5', view === 'table' ? 'bg-indigo-50 text-indigo-700' : 'bg-white text-slate-500 hover:bg-slate-50']" @click="view = 'table'">
          <TableCellsIcon class="w-4 h-4" /> Table
        </button>
        <button type="button" :class="['px-3 py-2 text-sm flex items-center gap-1.5 border-l border-slate-200', view === 'kanban' ? 'bg-indigo-50 text-indigo-700' : 'bg-white text-slate-500 hover:bg-slate-50']" @click="view = 'kanban'">
          <Squares2X2Icon class="w-4 h-4" /> Kanban
        </button>
      </div>

      <AppButton @click="openCreate()">
        <PlusIcon class="w-4 h-4" /> New Task
      </AppButton>
    </div>

    <!-- Per-member progress -->
    <div v-if="memberProgress.length" class="flex flex-wrap gap-x-6 gap-y-2">
      <div v-for="m in memberProgress" :key="m.name" class="flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold flex items-center justify-center">{{ initials(m.name) }}</span>
        <span class="text-xs text-slate-600">{{ m.name }}</span>
        <div class="w-16 h-1.5 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full rounded-full bg-emerald-500" :style="{ width: (m.total ? (m.done / m.total) * 100 : 0) + '%' }" />
        </div>
        <span class="text-[10px] text-slate-400">{{ m.done }}/{{ m.total }}</span>
      </div>
    </div>

    <EmptyState v-if="!filteredTasks.length" title="No tasks on this board yet"
      description="Add tasks to plan and monitor the committee's work for this period." />

    <!-- ── TABLE VIEW (status groups, monday-style) ─────────────────────── -->
    <div v-else-if="view === 'table'" class="space-y-5">
      <div v-for="s in STATUSES" :key="s.key">
        <template v-if="tasksByStatus[s.key].length || s.key === 'not_started'">
          <div class="flex items-center gap-2 mb-1.5">
            <AppBadge :color="s.badge">{{ s.label }}</AppBadge>
            <span class="text-xs text-slate-400">{{ tasksByStatus[s.key].length }}</span>
          </div>
          <div class="rounded-lg border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <tbody>
                  <tr v-for="t in tasksByStatus[s.key]" :key="t.id"
                      :class="['border-b border-slate-100 last:border-b-0 border-l-4 hover:bg-slate-50/70', railColor[t.status]]">
                    <td class="px-3 py-2.5 min-w-56">
                      <button type="button" class="text-left font-medium text-slate-700 hover:text-indigo-600 hover:underline" @click="openDrawer(t)">
                        {{ t.title }}
                      </button>
                      <div v-if="t.committee && t.committee.parent_committee_id" class="text-[11px] text-slate-400">└ {{ t.committee.name }}</div>
                    </td>
                    <td class="px-3 py-2.5">
                      <div class="flex -space-x-1.5">
                        <span v-for="a in (t.assignees ?? []).slice(0, 4)" :key="a.id" :title="a.name"
                          class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold flex items-center justify-center ring-2 ring-white">
                          {{ initials(a.name) }}
                        </span>
                        <span v-if="(t.assignees ?? []).length > 4" class="w-6 h-6 rounded-full bg-slate-100 text-slate-500 text-[10px] font-bold flex items-center justify-center ring-2 ring-white">+{{ t.assignees.length - 4 }}</span>
                        <UserCircleIcon v-if="!(t.assignees ?? []).length" class="w-6 h-6 text-slate-300" />
                      </div>
                    </td>
                    <td class="px-3 py-2.5 text-xs text-slate-500 max-w-44 truncate" :title="t.plan?.success_indicator">{{ t.plan?.success_indicator ?? '—' }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap">
                      <span :class="['text-xs flex items-center gap-1', isOverdue(t) ? 'text-red-600 font-semibold' : 'text-slate-500']">
                        <CalendarDaysIcon class="w-3.5 h-3.5" /> {{ fmtDate(t.due_date) ?? '—' }}
                      </span>
                    </td>
                    <td class="px-3 py-2.5"><AppBadge :color="priorityDef(t.priority).badge">{{ priorityDef(t.priority).label }}</AppBadge></td>
                    <td class="px-3 py-2.5 relative">
                      <button type="button" :disabled="!canWorkTask(t)"
                        :class="canWorkTask(t) ? 'cursor-pointer' : 'cursor-default opacity-70'"
                        @click="openStatusPicker = openStatusPicker === t.id ? null : t.id">
                        <AppBadge :color="statusDef(t.status).badge">{{ statusDef(t.status).label }}</AppBadge>
                      </button>
                      <div v-if="openStatusPicker === t.id" class="absolute z-20 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg py-1 w-40">
                        <button v-for="opt in STATUSES" :key="opt.key" type="button"
                          class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-50 flex items-center gap-2"
                          @click="setStatus(t, opt.key)">
                          <AppBadge :color="opt.badge">{{ opt.label }}</AppBadge>
                        </button>
                      </div>
                    </td>
                    <td class="px-3 py-2.5">
                      <button type="button" class="flex items-center gap-1 text-xs text-slate-400 hover:text-indigo-600" @click="openDrawer(t)">
                        <ChatBubbleLeftRightIcon class="w-4 h-4" /> {{ t.updates_count ?? 0 }}
                      </button>
                    </td>
                    <td class="px-3 py-2.5 whitespace-nowrap text-right">
                      <button v-if="canManageBoard" type="button" class="text-slate-400 hover:text-indigo-600 mr-2" @click="openEdit(t)"><PencilSquareIcon class="w-4 h-4" /></button>
                      <button v-if="canManageBoard" type="button" class="text-slate-400 hover:text-red-600" @click="deleteTask(t)"><TrashIcon class="w-4 h-4" /></button>
                    </td>
                  </tr>
                  <!-- inline add row -->
                  <tr v-if="s.key === 'not_started'">
                    <td colspan="8" class="px-3 py-2">
                      <button type="button" class="text-xs text-slate-400 hover:text-indigo-600 flex items-center gap-1" @click="openCreate(s.key)">
                        <PlusIcon class="w-3.5 h-3.5" /> Add task
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- ── KANBAN VIEW ──────────────────────────────────────────────────── -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <div v-for="s in STATUSES" :key="s.key"
        :class="['rounded-xl border bg-slate-50/60 p-2.5 min-h-40', dragOverCol === s.key ? 'border-indigo-400 bg-indigo-50/50' : 'border-slate-200']"
        @dragover.prevent="dragOverCol = s.key"
        @dragleave="dragOverCol === s.key && (dragOverCol = null)"
        @drop.prevent="onDropColumn(s.key)">
        <div class="flex items-center justify-between mb-2 px-1">
          <AppBadge :color="s.badge">{{ s.label }}</AppBadge>
          <span class="text-xs text-slate-400">{{ tasksByStatus[s.key].length }}</span>
        </div>
        <div class="space-y-2">
          <div v-for="t in tasksByStatus[s.key]" :key="t.id"
            :draggable="canWorkTask(t)"
            :class="['bg-white rounded-lg border border-slate-200 p-2.5 shadow-sm border-l-4', railColor[t.status], canWorkTask(t) ? 'cursor-grab active:cursor-grabbing' : '']"
            @dragstart="onDragStart(t)"
            @click="openDrawer(t)">
            <p class="text-sm font-medium text-slate-700 leading-snug">{{ t.title }}</p>
            <div v-if="t.committee && t.committee.parent_committee_id" class="text-[11px] text-slate-400 mt-0.5">└ {{ t.committee.name }}</div>
            <div class="flex items-center justify-between mt-2">
              <div class="flex -space-x-1.5">
                <span v-for="a in (t.assignees ?? []).slice(0, 3)" :key="a.id" :title="a.name"
                  class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-[9px] font-bold flex items-center justify-center ring-2 ring-white">
                  {{ initials(a.name) }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span :class="['w-2 h-2 rounded-full', { low: 'bg-slate-300', medium: 'bg-blue-400', high: 'bg-amber-400', urgent: 'bg-red-500' }[t.priority]]" :title="priorityDef(t.priority).label" />
                <span v-if="t.due_date" :class="['text-[10px]', isOverdue(t) ? 'text-red-600 font-semibold' : 'text-slate-400']">{{ fmtDate(t.due_date) }}</span>
              </div>
            </div>
          </div>
          <button type="button" class="w-full text-xs text-slate-400 hover:text-indigo-600 flex items-center justify-center gap-1 py-1.5" @click="openCreate(s.key)">
            <PlusIcon class="w-3.5 h-3.5" /> Add
          </button>
        </div>
      </div>
    </div>

    <!-- ── Create / edit task modal ─────────────────────────────────────── -->
    <AppModal :show="showTaskModal" :title="editingTask ? 'Edit Task' : 'New Task'" @close="showTaskModal = false">
      <form class="space-y-4" @submit.prevent="saveTask">
        <AppInput v-model="taskForm.title" label="Task" placeholder="What needs to get done?" required />
        <AppTextarea v-model="taskForm.description" label="Details" :rows="3" />

        <div class="grid grid-cols-2 gap-3">
          <AppSelect v-model="taskForm.priority" label="Priority">
            <option v-for="p in PRIORITIES" :key="p.key" :value="p.key">{{ p.label }}</option>
          </AppSelect>
          <AppInput v-model="taskForm.due_date" type="date" label="Due date" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <AppSelect v-model="taskForm.rating_period_id" label="Rating period">
            <option :value="null">All periods</option>
            <option v-for="p in ratingPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
          </AppSelect>
          <AppSelect v-model="taskForm.work_distribution_plan_id" label="Linked plan (optional)">
            <option value="">— None —</option>
            <option v-for="pl in plans" :key="pl.id" :value="pl.id">{{ pl.success_indicator }}</option>
          </AppSelect>
        </div>

        <div v-if="canManageBoard">
          <label class="block text-xs font-medium text-slate-600 mb-1">Assignees</label>
          <div class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
            <label v-for="m in boardMembers" :key="m.id" class="flex items-center gap-2 px-3 py-1.5 text-sm cursor-pointer hover:bg-slate-50">
              <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                :checked="taskForm.assignee_ids.includes(m.id)" @change="toggleAssignee(m.id)" />
              {{ m.name }}
            </label>
          </div>
        </div>
        <p v-else class="text-xs text-slate-500">This task will be assigned to you.</p>

        <div class="flex justify-end gap-2">
          <AppButton variant="secondary" type="button" @click="showTaskModal = false">Cancel</AppButton>
          <AppButton type="submit" :loading="isSubmitting" :disabled="isSubmitting">{{ editingTask ? 'Save' : 'Add Task' }}</AppButton>
        </div>
      </form>
    </AppModal>

    <!-- ── Task drawer: details + updates feed ──────────────────────────── -->
    <AppModal :show="showDrawer" :title="drawerTask?.title ?? 'Task'" @close="showDrawer = false">
      <div v-if="drawerTask" class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
          <AppBadge :color="statusDef(drawerTask.status).badge">{{ statusDef(drawerTask.status).label }}</AppBadge>
          <AppBadge :color="priorityDef(drawerTask.priority).badge">{{ priorityDef(drawerTask.priority).label }}</AppBadge>
          <span v-if="drawerTask.due_date" :class="['text-xs', isOverdue(drawerTask) ? 'text-red-600 font-semibold' : 'text-slate-500']">
            Due {{ fmtDate(drawerTask.due_date) }}
          </span>
          <span v-if="drawerTask.period" class="text-xs text-slate-400">{{ drawerTask.period.label }}</span>
        </div>

        <p v-if="drawerTask.description" class="text-sm text-slate-600 whitespace-pre-line">{{ drawerTask.description }}</p>
        <p v-if="drawerTask.plan" class="text-xs text-slate-500">Linked plan: {{ drawerTask.plan.success_indicator }}</p>

        <div class="flex flex-wrap gap-1.5">
          <span v-for="a in (drawerTask.assignees ?? [])" :key="a.id" class="inline-flex items-center gap-1.5 bg-slate-100 rounded-full pl-1 pr-2.5 py-0.5 text-xs text-slate-600">
            <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-[9px] font-bold flex items-center justify-center">{{ initials(a.name) }}</span>
            {{ a.name }}
          </span>
        </div>

        <!-- Quick status change -->
        <div v-if="canWorkTask(drawerTask)" class="flex flex-wrap gap-1.5">
          <button v-for="opt in STATUSES" :key="opt.key" type="button"
            :class="['rounded-full transition-transform', drawerTask.status === opt.key ? 'ring-2 ring-indigo-400 ring-offset-1' : 'hover:scale-105']"
            @click="setStatus(drawerTask, opt.key)">
            <AppBadge :color="opt.badge">{{ opt.label }}</AppBadge>
          </button>
        </div>

        <!-- Updates feed -->
        <div class="border-t border-slate-100 pt-3">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Updates</p>
          <div v-if="canWorkTask(drawerTask)" class="flex gap-2 mb-3">
            <AppInput v-model="updateBody" placeholder="Write an update…" class="flex-1" @keydown.enter.prevent="postUpdate" />
            <AppButton :disabled="isSubmitting || !updateBody.trim()" @click="postUpdate">Post</AppButton>
          </div>
          <div v-if="(drawerTask.updates ?? []).length" class="space-y-2 max-h-56 overflow-y-auto">
            <div v-for="u in drawerTask.updates" :key="u.id" class="bg-slate-50 rounded-lg px-3 py-2">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-600">{{ u.user?.name }}</span>
                <span class="text-[10px] text-slate-400">{{ new Date(u.created_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) }}</span>
              </div>
              <p class="text-sm text-slate-600 mt-0.5 whitespace-pre-line">{{ u.body }}</p>
            </div>
          </div>
          <p v-else class="text-xs text-slate-400">No updates yet — post progress notes here.</p>
        </div>
      </div>
    </AppModal>
  </div>
</template>
