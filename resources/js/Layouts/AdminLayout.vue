<script>
// In-memory fallback for browsers where sessionStorage is unavailable.
const savedSidebarScrollByUser = new Map();
</script>

<script setup>
import { ref, computed, markRaw, nextTick, onMounted, onUnmounted, onBeforeUnmount, watch } from "vue";
import { sessionExpired } from "@/Composables/useSession.js";
const props = defineProps({ title: { type: String, default: '' } });
const title = props.title;
import { Head, usePage, router } from "@inertiajs/vue3";
import SidebarLink from "@/Components/SidebarLink.vue";
import SidebarSearch from '@/Components/Layout/SidebarSearch.vue';
import ProfileEditModal from '@/Components/ProfileEditModal.vue';
import AdminTopbar from '@/Components/Layout/AdminTopbar.vue';
import ActingAsBanner from '@/Components/Layout/ActingAsBanner.vue';
import ReportDateRangeModal from '@/Components/Layout/ReportDateRangeModal.vue';
import SessionExpiredOverlay from '@/Components/Layout/SessionExpiredOverlay.vue';
import VersionHistoryModal from '@/Components/Layout/VersionHistoryModal.vue';
import SignatureSetupModal from '@/Components/Layout/SignatureSetupModal.vue';
import { ChevronDownIcon, MagnifyingGlassIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import ErrorReportModal from '@/Components/ErrorReportModal.vue'
import AppLoadingOverlay from '@/Components/AppLoadingOverlay.vue'
import SosFloatingButton from '@/Components/Sos/SosFloatingButton.vue'
import PageSkeleton from '@/Components/PageSkeleton.vue';
import { menuItems } from './navigation.js';

// --- State ---
const collapsed = ref(false);
const mobileOpen = ref(false);
const sidebarNav = ref(null);
const sidebarSearch = ref(null);
const sidebarSearchQuery = ref('');

const expanded = ref({});
const showVersionModal = ref(false);
const showErrorReportModal = ref(false);
const showSignatureSetupModal = ref(false);

// ─── Chat unread badge (Phase 8) ──────────────────────────────────────────
const chatUnreadCount = ref(0);

async function fetchChatUnread() {
  try {
    const res = await window.axios.get('/api/chat/unread-count');
    chatUnreadCount.value = res.data.unread_count ?? 0;
  } catch {
    // silently ignore — badge just won't show
  }
}

let chatEchoChannel = null;

function setupChatNotifications() {
  if (!window.Echo) return;

  const userId = user?.id;
  if (!userId) return;

  chatEchoChannel = window.Echo.private(`user.${userId}`)
    .listen('.new.message', (e) => {
      // Increment badge if not currently on the Chat page
      if (!route().current('chat.index')) {
        chatUnreadCount.value += 1;
      }

      // Browser notification when tab is not focused
      if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
        const senderName = e.message?.sender_name ?? 'Someone';
        const body = e.message?.body || '📎 Attachment';
        new Notification(`New message from ${senderName}`, {
          body,
          icon: '/favicon.ico',
        });
      }
    });
}

// Reset badge when navigating to Chat
watch(() => route().current('chat.index'), (onChat) => {
  if (onChat) chatUnreadCount.value = 0;
});

// ─── Skeleton loading ─────────────────────────────────────────────────────────
const isNavigating = ref(false);
let navTimer = null;

// Close mobile sidebar on Inertia navigation
let removeNavListener;
let removeStartListener;
let removeFinishListener;
const handleSidebarSearchShortcut = (event) => {
  if ((event.ctrlKey || event.metaKey) && event.key.toLocaleLowerCase() === 'k') {
    event.preventDefault();
    if (window.matchMedia('(max-width: 767px)').matches) {
      mobileOpen.value = true;
    }
    sidebarSearch.value?.focus();
  }
};

onMounted(() => {
  // Only show skeleton for GET navigations (page changes), not POST/PUT/PATCH/DELETE (saves)
  removeStartListener = router.on('start', (event) => {
    rememberSidebarScroll();

    if (event.detail.visit.method === 'get') {
      navTimer = setTimeout(() => { isNavigating.value = true; }, 150);
    }
  });

  // 'finish' fires for EVERY request (success, error, cancel) — definitive reset
  removeFinishListener = router.on('finish', () => {
    clearTimeout(navTimer);
    isNavigating.value = false;
  });

  removeNavListener = router.on('navigate', () => {
    mobileOpen.value = false;
    sidebarSearchQuery.value = '';
    // Reset badge when navigating to Chat page
    if (route().current('chat.index')) chatUnreadCount.value = 0;
  });

  window.addEventListener('keydown', handleSidebarSearchShortcut);
  fetchChatUnread();
  setupChatNotifications();

  // Request browser notification permission (non-blocking)
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }

  // Digital signature setup prompt — one-shot flag set at login (consumed
  // server-side), shown only while signature image or PIN is still missing.
  const u = page.props.auth?.user;
  if (
    page.props.promptSignatureSetup &&
    u && (!u.electronic_signature || !u.has_signature_pin) &&
    !route().current('profile.signature')
  ) {
    showSignatureSetupModal.value = true;
  }

  restoreSidebarScroll();
});
onBeforeUnmount(() => {
  if (scrollSaveFrame !== null) {
    window.cancelAnimationFrame(scrollSaveFrame);
    scrollSaveFrame = null;
  }
  rememberSidebarScroll();
});
onUnmounted(() => {
  if (removeStartListener) removeStartListener();
  if (removeFinishListener) removeFinishListener();
  if (removeNavListener) removeNavListener();
  window.removeEventListener('keydown', handleSidebarSearchShortcut);
  clearTimeout(navTimer);
  if (chatEchoChannel) {
    window.Echo?.leave(`user.${user?.id}`);
    chatEchoChannel = null;
  }
});

// --- Page + Auth ---
const page = usePage();
const appVersion = computed(() => page.props.appVersion ?? { current: '1.0.0', history: [] });
const user = page.props.auth?.user || { role: { name: "Guest" }, name: "Guest" };
const roleName = user.role?.name || "Guest";
// Support multiple roles: array of role name strings
const baseRoleNames = user.roleNames?.length ? user.roleNames : (roleName !== "Guest" ? [roleName] : []);
// Inject synthetic 'PMRater' role when user is a committee head or SA coordinator
const roleNames = [
  ...baseRoleNames,
  ...(page.props.isPMRater ? ['PMRater'] : []),
  ...(page.props.isAUH    ? ['AUH']     : []),
  ...(page.props.hasAdvisoryScheduleScope ? ['ScheduleAdvisory'] : []),
];

// Permission set — populated by HandleInertiaRequests via shared Inertia props
// Using a Set for O(1) lookups on every sidebar render
const userPermissions = new Set(user.permissions ?? []);
const hasPerm = (...perms) => perms.some(p => userPermissions.has(p));

// Also expose hasPerm for use in template (e.g. version modal button)
const isAdmin = hasPerm('roles.assign');

// ─── Sidebar position ────────────────────────────────────────────────────────
const sidebarUserKey = user.id ?? 'guest';
const sidebarStorageKey = `atlas.sidebar.scroll.${sidebarUserKey}`;
let scrollSaveFrame = null;

const rememberSidebarScroll = (force = false) => {
  if (!sidebarNav.value) return;
  if (isSidebarSearching.value && !force) return;

  const position = sidebarNav.value.scrollTop;
  savedSidebarScrollByUser.set(sidebarUserKey, position);
  try {
    window.sessionStorage.setItem(sidebarStorageKey, String(position));
  } catch {
    // The module-level value still preserves position during this page session.
  }
};

const scheduleSidebarScrollSave = () => {
  if (scrollSaveFrame !== null) return;

  scrollSaveFrame = window.requestAnimationFrame(() => {
    scrollSaveFrame = null;
    rememberSidebarScroll();
  });
};

const ensureActiveSidebarLinkVisible = () => {
  const nav = sidebarNav.value;
  const activeLink = nav?.querySelector('[data-sidebar-active="true"]');
  if (!nav || !activeLink) return;

  const navRect = nav.getBoundingClientRect();
  const linkRect = activeLink.getBoundingClientRect();
  const edgePadding = 12;

  if (linkRect.top < navRect.top + edgePadding) {
    nav.scrollTop -= navRect.top + edgePadding - linkRect.top;
  } else if (linkRect.bottom > navRect.bottom - edgePadding) {
    nav.scrollTop += linkRect.bottom - (navRect.bottom - edgePadding);
  }

  rememberSidebarScroll();
};

const restoreSidebarScroll = async () => {
  await nextTick();
  if (!sidebarNav.value) return;

  let position = savedSidebarScrollByUser.get(sidebarUserKey) ?? 0;
  try {
    const stored = window.sessionStorage.getItem(sidebarStorageKey);
    if (stored !== null && Number.isFinite(Number(stored))) {
      position = Number(stored);
    }
  } catch {
    // Use the module-level fallback.
  }

  sidebarNav.value.scrollTop = Math.max(0, position);
  window.requestAnimationFrame(ensureActiveSidebarLinkVisible);
};

// --- Helpers ---
const isActive = (name) => name && route().current(name); // ✅ check via routeName

// Safely coerce a raw Inertia prop value to a non-negative integer
const toBadgeInt = (val) => {
  const n = parseInt(val, 10);
  return isNaN(n) || n < 0 ? 0 : n;
};

// Return numeric badge from shared Inertia props based on child routeName
const getBadge = (child) => {
  const rn = child?.routeName || null;
  // Chat badge is available to all roles — check it first
  if (rn === 'chat.index') return toBadgeInt(chatUnreadCount.value);
  if (!page || !page.props) return 0;
  switch (rn) {
    case 'consultations.index':
      return toBadgeInt(page.props.consultationsNotificationCount);
    case 'jobrequests.index':
      return toBadgeInt(page.props.itJobRequestsNotificationCount);
    case 'vehicle-requests.index':
      return toBadgeInt(page.props.vehicleRequestsNotificationCount);
    case 'gatepass.index':
      return toBadgeInt(page.props.gatepassNotificationCount);
    case 'messengerial.index':
      return toBadgeInt(page.props.messengerialRequestsNotificationCount);
    case 'facility-requests.index':
      return toBadgeInt(page.props.facilityRequestsNotificationCount);
    case 'service-requests.index':
      return toBadgeInt(page.props.serviceRequestsNotificationCount);
    case 'work-requests.index':
      return toBadgeInt(page.props.workRequestsNotificationCount);
    case 'library.borrowings.index':
      return toBadgeInt(page.props.borrowingsOverdueCount);
    case 'document-tracking.index':
      return toBadgeInt(page.props.documentTrackingNotificationCount);
    case 'approvals.inbox':
      return toBadgeInt(page.props.approvalInboxCount);
    default:
      return 0;
  }
};

// Return aggregate badge count for a group (sum of all children badges), capped at 99
const getGroupBadge = (item) => {
  if (!item.children?.length) return 0;
  const total = item.children.reduce((sum, child) => sum + getBadge(child), 0);
  return Math.min(total, 99);
};

const showProfileModal = ref(false);

// Consultation Log modal state
const showConsultationLogModal = ref(false);
const consultationLogRouteName = ref(null);
const consultationLogType = ref('student');
const openConsultationLogModal = (routeName = null) => {
  consultationLogRouteName.value = routeName;
  // set default type based on incoming routeName, allow user to change in modal
  consultationLogType.value = (routeName && String(routeName).includes('employee')) ? 'employee' : 'student';
  showConsultationLogModal.value = true;
};
const closeConsultationLogModal = () => {
  showConsultationLogModal.value = false;
};
const generateConsultationLog = ({ start, end, type }) => {
  const base = type === 'employee' ? 'consultations.employee.log.print' : 'consultations.log.print';
  const url = route(base) + `?start=${start}&end=${end}&type=${type}`;
  window.open(url, "_blank");
  closeConsultationLogModal();
};

// --- Attendance Logs Modal State ---
const showAttendanceModal = ref(false);
const openAttendanceModal = () => {
  showAttendanceModal.value = true;
};
const closeAttendanceModal = () => {
  showAttendanceModal.value = false;
};
const generateAttendanceReport = ({ start, end }) => {
  // Navigate to attendance index with query params
  router.get(route('hr.attendance.index'), { start, end });
  closeAttendanceModal();
};

// --- Library Statistics Modal State ---
const showLibraryStatsModal = ref(false);
const openLibraryStatsModal = () => {
  showLibraryStatsModal.value = true;
};
const closeLibraryStatsModal = () => {
  showLibraryStatsModal.value = false;
};
const generateLibraryStats = ({ start, end }) => {
  const url = route('library.statistics.report') + `?start=${start}&end=${end}`;
  window.open(url, "_blank");
  closeLibraryStatsModal();
};

// --- Health Statistics Modal ---
const showHealthStatsModal = ref(false);
const openHealthStatsModal = () => {
  showHealthStatsModal.value = true;
};
const closeHealthStatsModal = () => {
  showHealthStatsModal.value = false;
};
const generateHealthStats = ({ start, end }) => {
  // Open a report route if available (may be added later).
  try {
    const url = route('health.statistics.report') + `?start=${start}&end=${end}&autoprint=1`;
    window.open(url, "_blank");
  } catch (e) {
    // If route helper is not available for this route yet, just close the modal.
    console.warn('health.statistics.report route not defined yet');
  }
  closeHealthStatsModal();
};

// --- Filter Menu by Role ---

// Children render alphabetically, with explicitly pinned self-service entries
// first, followed by dashboard entries (any label containing "Dashboard",
// e.g. "MIS Dashboard"). Items within the same tier sort alphabetically.
// Applies only to children — the TOP-LEVEL array keeps its curated order
// (section headers interleave it; sorting would destroy the grouping).
const childSortTier = (item) => {
  if (item.pinToTop) return 0;
  if (item.label.includes("Dashboard")) return 1;
  return 2;
};

const sortChildren = (items) =>
  items.sort((a, b) =>
    childSortTier(a) - childSortTier(b)
    || a.label.localeCompare(b.label)
  );

const filterMenuByRole = (items, userRoleNames) =>
  items
    .filter((item) => {
      // Hide when the user holds any excluded permission (e.g. show "My
      // Schedule" only to users who DON'T have the manage-level "Schedules").
      if (item.excludePermissions?.length && hasPerm(...item.excludePermissions)) {
        return false;
      }
      // Opt-in role override: show even without the item's permissions when
      // the user holds any of these roles (e.g. AUH sees "Schedules" without
      // faculty_loading.manage — the page itself scopes them to their unit).
      if (item.orRoles?.some((r) => userRoleNames.includes(r))) {
        return true;
      }
      if (item.permissions?.length) {
        return hasPerm(...item.permissions);
      }
      return item.roles?.some((r) => userRoleNames.includes(r)) ?? true;
    })
    .map((item) =>
      item.children
        ? { ...item, icon: item.icon ? markRaw(item.icon) : item.icon, children: sortChildren(filterMenuByRole(item.children, userRoleNames)) }
        : item
    );

const filteredMenu = computed(() => filterMenuByRole(menuItems, roleNames));

const normalizeSidebarSearch = (value) =>
  String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase()
    .replace(/\s+/g, ' ')
    .trim();

const normalizedSidebarSearch = computed(() => normalizeSidebarSearch(sidebarSearchQuery.value));
const isSidebarSearching = computed(() => normalizedSidebarSearch.value.length > 0);

const sidebarItemMatches = (item, query, context = []) => {
  const keywords = Array.isArray(item.keywords) ? item.keywords : [item.keywords];
  const searchableText = [item.label, ...keywords, ...context]
    .filter(Boolean)
    .map(normalizeSidebarSearch)
    .join(' ');

  return query.split(' ').every((term) => searchableText.includes(term));
};

// Search only the already-authorized menu. This preserves the existing
// permission boundary and prevents hidden module labels from being exposed.
const searchedMenu = computed(() => {
  const query = normalizedSidebarSearch.value;
  if (!query) return filteredMenu.value;

  return filteredMenu.value.flatMap((item) => {
    // Section dividers add noise to a compact result list.
    if (item.type === 'section') return [];

    if (!item.children) {
      return sidebarItemMatches(item, query) ? [item] : [];
    }

    const children = sidebarItemMatches(item, query)
      ? item.children
      : item.children.filter((child) => sidebarItemMatches(child, query, [item.label]));

    return children.length ? [{ ...item, children }] : [];
  });
});

const sidebarSearchResultCount = computed(() =>
  searchedMenu.value.reduce(
    (total, item) => total + (item.children?.length ?? (item.type === 'section' ? 0 : 1)),
    0,
  )
);

watch(sidebarSearchQuery, async (query, previousQuery) => {
  const wasSearching = normalizeSidebarSearch(previousQuery).length > 0;
  const nowSearching = normalizeSidebarSearch(query).length > 0;

  if (!wasSearching && nowSearching) {
    // Capture the normal menu position before the filtered DOM is rendered.
    rememberSidebarScroll(true);
  }

  await nextTick();

  if (nowSearching) {
    if (sidebarNav.value) sidebarNav.value.scrollTop = 0;
  } else if (wasSearching) {
    await restoreSidebarScroll();
  }
});

// --- Expand logic ---
const toggleExpand = (label) => {
  if (isSidebarSearching.value) return;
  expanded.value[label] = !expanded.value[label];
};
const isSidebarGroupOpen = (item) => isSidebarSearching.value || expanded.value[item.label];

// Display-only: does this group contain the currently active route?
const groupHasActive = (item) => item.children?.some((c) => isActive(c.routeName)) ?? false;

const expandActiveSidebarGroup = () => {
  filteredMenu.value.forEach((item) => {
    if (item.children?.some((c) => isActive(c.routeName))) {
      expanded.value[item.label] = true;
    }
  });
};

expandActiveSidebarGroup();

watch(() => page.url, async () => {
  expandActiveSidebarGroup();
  await nextTick();
  window.requestAnimationFrame(ensureActiveSidebarLinkVisible);
});
</script>

<template>
  <Head :title="title" />

  <div class="min-h-screen flex flex-col bg-slate-50">
    <ActingAsBanner />
    <div class="min-h-screen flex flex-1">
    <!-- Mobile backdrop -->
    <div
      v-if="mobileOpen"
      @click="mobileOpen = false"
      class="fixed inset-0 bg-black/50 z-30 md:hidden backdrop-blur-sm"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        'transition-all duration-300 z-40 flex-shrink-0 flex flex-col bg-white shadow-xl ring-1 ring-slate-200/70 md:border-r md:border-slate-200 md:shadow-sm md:ring-0',
        'fixed inset-y-0 left-0 md:sticky md:top-0 md:h-screen md:inset-auto',
        mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        collapsed ? 'w-72 md:w-[68px]' : 'w-72 md:w-60',
      ]"
    >
      <!-- Logo -->
      <div class="min-h-16 flex items-center gap-3 border-b border-slate-200/70 px-4 py-3.5 shrink-0">
        <img v-if="collapsed" src="/images/atlas-mark.png" alt="Atlas" class="h-8 w-8 shrink-0 object-contain mx-auto" />
        <div v-else class="flex-1 flex flex-col items-center text-center min-w-0">
          <img src="/images/atlas-logo-full.png" alt="Atlas" class="h-7 w-auto object-contain" />
          <p class="text-[10px] text-slate-400 leading-tight mt-1 tracking-wide">Centralized Management Information System</p>
        </div>
        <!-- Close button (mobile only) -->
        <button
          @click="mobileOpen = false"
          class="ml-auto p-1 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 md:hidden shrink-0"
          aria-label="Close sidebar"
        >
          <XMarkIcon class="h-4 w-4" />
        </button>
      </div>

      <SidebarSearch
        ref="sidebarSearch"
        v-model="sidebarSearchQuery"
        :collapsed="collapsed"
        :searching="isSidebarSearching"
        :result-count="sidebarSearchResultCount"
        @expand="collapsed = false"
      />

      <!-- Navigation -->
      <nav
        ref="sidebarNav"
        class="sidebar-nav flex-1 overflow-y-auto px-2 py-3 space-y-0.5"
        @scroll.passive="scheduleSidebarScrollSave"
      >
        <template v-for="item in searchedMenu" :key="item.label">

          <!-- Section label -->
          <div
            v-if="item.type === 'section' && !collapsed"
            class="flex items-center gap-2 px-3 pt-5 pb-1.5"
          >
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em] shrink-0">{{ item.label }}</span>
            <span class="h-px flex-1 bg-slate-100"></span>
          </div>
          <div v-else-if="item.type === 'section' && collapsed" class="my-2 mx-3 h-px bg-slate-200" />

          <!-- Single link -->
          <SidebarLink
            v-else-if="!item.children"
            :href="item.href"
            :target="item.target"
            :icon="item.icon"
            :label="item.label"
            :collapsed="collapsed"
            :active="isActive(item.routeName)"
            :badge="getBadge(item)"
          />

          <!-- Group with children -->
          <div v-else>
            <button
              @click="toggleExpand(item.label)"
              :aria-expanded="isSidebarGroupOpen(item)"
              :title="collapsed ? item.label : null"
              class="group relative flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150"
              :class="isSidebarGroupOpen(item) || groupHasActive(item)
                ? 'bg-indigo-50 text-indigo-700'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
              <component
                v-if="item.icon"
                :is="item.icon"
                class="h-4 w-4 shrink-0 transition-colors"
                :class="[
                  collapsed ? 'mx-auto' : 'mr-2.5',
                  isSidebarGroupOpen(item) || groupHasActive(item) ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-600'
                ]"
              />
              <span v-if="!collapsed" class="flex-1 truncate text-left">{{ item.label }}</span>
              <span
                v-if="!collapsed && !isSidebarGroupOpen(item) && getGroupBadge(item) > 0"
                class="ml-1 shrink-0 inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none bg-amber-400 text-slate-900"
              >{{ getGroupBadge(item) }}</span>
              <span
                v-else-if="collapsed && getGroupBadge(item) > 0"
                class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-amber-400"
              />
              <ChevronDownIcon
                v-if="!collapsed"
                class="h-3.5 w-3.5 ml-1 shrink-0 text-slate-400 transition-transform duration-200"
                :class="{ 'rotate-180 text-indigo-600': isSidebarGroupOpen(item) }"
              />
            </button>

            <div class="sidebar-group" :class="{ 'sidebar-group-open': isSidebarGroupOpen(item) }">
            <div
              class="sidebar-group-inner mt-0.5 ml-4 pl-3 border-l space-y-0.5"
              :class="groupHasActive(item) ? 'border-indigo-100' : 'border-slate-200'"
            >
              <template v-for="child in item.children" :key="child.label">
                <SidebarLink
                  v-if="!['consultations.log.print','consultations.employee.log.print','library.statistics.report','health.statistics.report','hr.attendance.index'].includes(child.routeName)"
                  :href="child.href"
                  :target="child.target"
                  :label="child.label"
                  :icon="child.icon"
                  :collapsed="collapsed"
                  :active="isActive(child.routeName)"
                  :badge="getBadge(child)"
                />
                <!-- Modal-trigger child buttons — styled to match SidebarLink -->
                <button
                  v-else-if="['consultations.log.print','consultations.employee.log.print'].includes(child.routeName)"
                  @click="openConsultationLogModal(child.routeName)"
                  class="group flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-400 group-hover:text-slate-600" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'library.statistics.report'"
                  @click="openLibraryStatsModal"
                  class="group flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-400 group-hover:text-slate-600" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'health.statistics.report'"
                  @click="openHealthStatsModal"
                  class="group flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-400 group-hover:text-slate-600" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'hr.attendance.index'"
                  @click="openAttendanceModal"
                  class="group flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-400 group-hover:text-slate-600" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
              </template>
            </div>
            </div>
          </div>
        </template>

        <div
          v-if="isSidebarSearching && sidebarSearchResultCount === 0"
          class="flex flex-col items-center px-4 py-10 text-center">
          <MagnifyingGlassIcon class="h-8 w-8 text-slate-300" />
          <p class="mt-3 text-sm font-medium text-slate-600">No modules found</p>
          <p class="mt-1 text-xs text-slate-400">Try a different module or sidebar entry.</p>
          <button
            type="button"
            class="mt-3 text-xs font-medium text-indigo-600 hover:text-indigo-700 focus:outline-none focus:underline"
            @click="sidebarSearchQuery = ''">
            Clear search
          </button>
        </div>
      </nav>

      <!-- Version footer -->
      <div class="shrink-0 border-t border-slate-200/70 px-3 py-3">
        <button
          @click="showVersionModal = true"
          class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-500 ring-1 ring-transparent hover:bg-slate-50 hover:text-slate-700 hover:ring-slate-200 transition-all duration-150"
          :class="collapsed ? 'justify-center' : 'justify-between'"
        >
          <span class="font-mono" :class="collapsed ? 'font-bold text-slate-500' : 'text-slate-500'">
            v{{ appVersion.current }}
          </span>
          <span v-if="!collapsed" class="text-slate-400">Changelog →</span>
        </button>
      </div>
    </aside>

    <VersionHistoryModal
      :show="showVersionModal"
      :app-version="appVersion"
      :is-admin="isAdmin"
      @close="showVersionModal = false"
    />

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <AdminTopbar
        :title="title"
        :user="user"
        :role-name="roleName"
        :chat-unread-count="chatUnreadCount"
        @toggle-mobile="mobileOpen = !mobileOpen"
        @toggle-collapse="collapsed = !collapsed"
        @report-error="showErrorReportModal = true"
        @open-profile="showProfileModal = true"
      />

      <!-- Page Content -->
      <main class="p-4 md:p-6 flex-1 min-w-0">
        <Transition name="page-fade" mode="out-in">
          <PageSkeleton v-if="isNavigating" key="skeleton" />
          <div v-else key="content"><slot /></div>
        </Transition>
      </main>
    </div>
  <ProfileEditModal :show="showProfileModal" @close="showProfileModal = false" />
  <ErrorReportModal :open="showErrorReportModal" @close="showErrorReportModal = false" />
  <SignatureSetupModal :show="showSignatureSetupModal" @close="showSignatureSetupModal = false" />
  <ReportDateRangeModal
    :show="showConsultationLogModal"
    title="Consultation Log Generation"
    action="Generate Graph"
    :with-type="true"
    :type-value="consultationLogType"
    @close="closeConsultationLogModal"
    @submit="generateConsultationLog"
  />
  <ReportDateRangeModal
    :show="showLibraryStatsModal"
    title="Library Statistic Report"
    @close="closeLibraryStatsModal"
    @submit="generateLibraryStats"
  />
  <ReportDateRangeModal
    :show="showHealthStatsModal"
    title="Statistics Report"
    @close="closeHealthStatsModal"
    @submit="generateHealthStats"
  />
  <ReportDateRangeModal
    :show="showAttendanceModal"
    title="Attendance Logs"
    action="View"
    @close="closeAttendanceModal"
    @submit="generateAttendanceReport"
  />

</div>
</div>

  <SessionExpiredOverlay :show="sessionExpired" />

  <AppLoadingOverlay />

  <SosFloatingButton trigger-route="sos.trigger" />

</template>

<style scoped>
.page-fade-enter-active {
  transition: opacity 160ms ease, transform 160ms ease;
}
.page-fade-leave-active {
  transition: opacity 120ms ease;
}
.page-fade-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.page-fade-leave-to {
  opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
  .page-fade-enter-from {
    transform: none;
  }
}

/* Smooth expand/collapse for sidebar groups (grid-rows 0fr -> 1fr) */
.sidebar-group {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 200ms ease;
}
.sidebar-group-open {
  grid-template-rows: 1fr;
}
.sidebar-group-inner {
  overflow: hidden;
  min-height: 0;
}

/* Thin scrollbar — transparent until the nav is hovered */
.sidebar-nav {
  scrollbar-width: thin;
  scrollbar-color: transparent transparent;
}
.sidebar-nav:hover {
  scrollbar-color: #cbd5e1 transparent; /* slate-300 */
}
.sidebar-nav::-webkit-scrollbar {
  width: 6px;
}
.sidebar-nav::-webkit-scrollbar-thumb {
  background: transparent;
  border-radius: 9999px;
}
.sidebar-nav:hover::-webkit-scrollbar-thumb {
  background: #cbd5e1;
}
@media (prefers-reduced-motion: reduce) {
  .sidebar-group {
    transition: none;
  }
}
</style>
