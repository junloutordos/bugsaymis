<script setup>
import { ref, computed } from "vue";
import { Head, usePage, router } from "@inertiajs/vue3";
import SidebarLink from "@/Components/SidebarLink.vue";
import {
  HomeIcon,
  UsersIcon,
  DocumentTextIcon,
  Bars3Icon,
  ChevronDownIcon,
  ClipboardDocumentListIcon,
  UserGroupIcon,
  ChartBarIcon,
  ServerStackIcon,
  QueueListIcon,
  ComputerDesktopIcon,
  BookOpenIcon,
  ArchiveBoxIcon,
  WrenchScrewdriverIcon,
  ShoppingCartIcon,
  CreditCardIcon,
  BanknotesIcon,
  CurrencyDollarIcon,
  HeartIcon,
  ChatBubbleLeftRightIcon,
  HomeModernIcon,
  UserIcon,
  CursorArrowRippleIcon,
  ClockIcon,
} from "@heroicons/vue/24/outline";

// Props
defineProps({
  title: { type: String, default: "Dashboard" },
});

// --- State ---
const collapsed = ref(false);
const showDropdown = ref(false);
const expanded = ref({});

// --- Page + Auth ---
const page = usePage();
const user = page.props.auth?.user || { role: { name: "Guest" }, name: "Guest" };
const roleName = user.role?.name || "Guest";

// --- Helpers ---
const toggleDropdown = () => (showDropdown.value = !showDropdown.value);
const logout = () => router.post(route("logout"));
const isActive = (name) => name && route().current(name); // ✅ check via routeName

// --- Menu Items ---
const menuItems = [
  {
    type: "section",
    label: "Administrator",
    roles: ["Administrator"],
  },
  {
    label: "Dashboard",
    routeName: "dashboard",
    href: route("dashboard"),
    icon: HomeIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "DivisionChief", "OCD"],
  },
  {
    label: "User Management",
    icon: UsersIcon,
    roles: ["Administrator"],
    children: [
      {
        label: "All Users",
        routeName: "users.index",
        href: route("users.index"),
        icon: UserGroupIcon,
        roles: ["Administrator"],
      },
      {
        label: "User Roles",
        routeName: "roles.index",
        href: route("roles.index"),
        icon: CursorArrowRippleIcon,
        roles: ["Administrator"],
      },
      {
        label: "Division",
        routeName: "roles.divisions",
        href: route("roles.divisions"),
        icon: CursorArrowRippleIcon,
        roles: ["Administrator"],
      },
      
    ],
  },
  {
    label: "MIS",
    icon: ServerStackIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "DivisionChief", "OCD"],
    children: [
      {
        label: "IT Job Requests",
        routeName: "jobrequests.index",
        href: route("jobrequests.index"),
        icon: ComputerDesktopIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
      {
        label: "For Approval ITJR",
        routeName: "job-requests.for-approval",
        href: route("job-requests.for-approval"),
        icon: BookOpenIcon,
        roles: ["Administrator","DivisionChief"],
      },
      {
        label: "OCD Approval ITJR",
        routeName: "job-requests.ocd-approval",
        href: route("job-requests.ocd-approval"),
        icon: BookOpenIcon,
        roles: ["Administrator","OCD"],
      },
      {
        label: "Equipment Inventory",
        routeName: "ict-equipments.index",
        href: route("ict-equipments.index"),
        icon: QueueListIcon,
        roles: ["Administrator", "OCD"],
      },
      {
        label: "PMS",
        routeName: "ict-pms.index",
        href: route("ict-pms.index"),
        icon: ClockIcon,
        roles: ["Administrator", "OCD"],
      },
    ],
  },
  {
    type: "section",
    label: "Finance & Administration",
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  },
  {
    label: "Performance Mngmt",
    icon: UserGroupIcon,
    roles: ["Administrator", "Faculty", "Staff", "HR", "DivisionChief"],
    children: [
      {
        label: "Agency Org Outcome",
        routeName: "outcome.index",
        href: route("outcome.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator","HR"],
      },
      {
        label: "Performance Indicators",
        routeName: "performanceindicator.index",
        href: route("performanceindicator.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator","HR"],
      },
      {
        label: "Work Distribution Plan",
        routeName: "workdistribution.index",
        href: route("workdistribution.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator","HR"],
      },
      {
        label: "IPCR",
        routeName: "employee-ipcr.index",
        href: route("employee-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff","HR"],
      },
      
      {
        label: "My Division",
        routeName: "division-chief-ipcr.index",
        href: route("division-chief-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "DivisionChief"],
      },
      
    ],
  },
  {
    label: "Records Management",
    icon: ArchiveBoxIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "General Services",
    icon: WrenchScrewdriverIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Supply & Property",
    icon: ShoppingCartIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Accounting",
    icon: CreditCardIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Budget",
    icon: BanknotesIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Cashier",
    icon: CurrencyDollarIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    type: "section",
    label: "Student Services",
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  },
  {
    label: "Health Services",
    icon: HeartIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Guidance Services",
    icon: ChatBubbleLeftRightIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Library Services",
    icon: BookOpenIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Residence Hall",
    icon: HomeModernIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    type: "section",
    label: "Reports",
    roles: ["Administrator", "Faculty", "Staff"],
  },
  {
    label: "Reports",
    icon: DocumentTextIcon,
    roles: ["Administrator", "Faculty", "Staff"],
    children: [
      {
        label: "Monthly Reports",
        routeName: "reports.index",
        href: route("reports.index"),
        icon: ChartBarIcon,
        roles: ["Administrator", "Faculty", "Staff"],
      },
    ],
  },
];

// --- Filter Menu by Role ---
const filterMenuByRole = (items, role) =>
  items
    .filter((item) => item.roles.includes(role))
    .map((item) =>
      item.children
        ? { ...item, children: filterMenuByRole(item.children, role) }
        : item
    );

const filteredMenu = computed(() => filterMenuByRole(menuItems, roleName));

// --- Expand logic ---
const toggleExpand = (label) => (expanded.value[label] = !expanded.value[label]);

filteredMenu.value.forEach((item) => {
  if (item.children?.some((c) => isActive(c.routeName))) {
    expanded.value[item.label] = true;
  }
});
</script>

<template>
  <Head :title="title" />

  <div class="min-h-screen flex bg-gray-100">
    <!-- Sidebar -->
    <aside
      :class="[collapsed ? 'w-20' : 'w-64', 'bg-white shadow-lg transition-all duration-300']"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center justify-center border-b px-4">
        <img src="/images/pshslogo.png" alt="PSHS-CRC Logo" class="h-10" />
        <span v-if="!collapsed" class="ml-3 text-xl font-bold text-gray-800">
          BugsayMIS
        </span>
      </div>

      <!-- Navigation -->
      <nav class="mt-6 px-2 space-y-1">
        <template v-for="item in filteredMenu" :key="item.label">
          <!-- Section label -->
          <div
            v-if="item.type === 'section' && !collapsed"
            class="px-3 pt-4 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider"
          >
            {{ item.label }}
          </div>

          <!-- Single link -->
          <SidebarLink
            v-else-if="!item.children"
            :href="item.href"
            :icon="item.icon"
            :label="item.label"
            :collapsed="collapsed"
            :active="isActive(item.routeName)"
          />

          <!-- With children -->
          <div v-else>
            <button
              @click="toggleExpand(item.label)"
              class="flex items-center w-full px-3 py-2 rounded-md transition
                text-gray-700 hover:bg-gray-100"
              :class="{ 'bg-gray-200 font-semibold': expanded[item.label] }"
            >
              <component v-if="item.icon" :is="item.icon" class="h-5 w-5 mr-2" />
              <span v-if="!collapsed">{{ item.label }}</span>
              <ChevronDownIcon
                class="h-4 w-4 ml-auto transform transition-transform"
                :class="{ 'rotate-180': expanded[item.label] }"
              />
            </button>

            <div v-show="expanded[item.label]" class="ml-6 mt-1 space-y-1">
              <SidebarLink
                v-for="child in item.children"
                :key="child.label"
                :href="child.href"
                :label="child.label"
                :icon="child.icon"
                :collapsed="collapsed"
                :active="isActive(child.routeName)"
              />
            </div>
          </div>
        </template>
      </nav>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col">
      <!-- Navbar -->
      <header class="h-16 bg-white shadow flex items-center justify-between px-6">
        <button
          @click="collapsed = !collapsed"
          class="p-2 rounded-md hover:bg-gray-100"
        >
          <Bars3Icon class="h-6 w-6 text-gray-600" />
        </button>

        <!-- Profile Dropdown -->
        <div class="relative">
          <button
            @click="toggleDropdown"
            class="flex items-center space-x-2 p-2 rounded-md hover:bg-gray-100"
          >
            <img
              src="https://i.pravatar.cc/40"
              alt="User Avatar"
              class="w-10 h-10 rounded-full border"
            />
            <span class="hidden md:inline text-gray-700">{{ user.name }}</span>
            <ChevronDownIcon class="h-5 w-5 text-gray-600" />
          </button>

          <div
            v-if="showDropdown"
            class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border z-50"
          >
            <a
              :href="route('profile.edit')"
              class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
              Profile
            </a>
            <button
              @click="logout"
              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
              Logout
            </button>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="p-6 flex-1">
        <slot />
      </main>
    </div>
  </div>
</template>
