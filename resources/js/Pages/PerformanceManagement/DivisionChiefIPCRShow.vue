<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { ArrowLeftIcon } from "@heroicons/vue/24/outline";
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
  ipcr: Object,
  plans: Array
});

const divisionComments = ref(props.ipcr.remarks ?? "");
const isEditing = ref(!divisionComments.value); // editable only if empty

const saveDivisionComments = () => {
  router.post(
    route("division-chief-employee-ipcr.savecomments", props.ipcr.id),
    {
      division_comments: divisionComments.value, // send to controller
    },
    {
      onSuccess: () => {
        Swal.fire({
          icon: "success",
          title: "Saved",
          text: "Comments saved successfully.",
        });
        isEditing.value = false; // lock textarea again
      },
      onError: () => {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "Failed to save comments.",
        });
      },
    }
  );
};



// Modal state
const isModalOpen = ref(false);
const currentPlan = ref(null);
const form = ref({
  accomplishment: "",
  mov_link: "",
  quality: null,
  efficiency: null,
  timeliness: null,
});

// Only allow editing if IPCR status = Target Approved
const canEdit = computed(() => props.ipcr.status === "Submitted for Rating");

// Compute average only from available ratings
const computeAverage = (q, e, t) => {
  const values = [q, e, t].filter(v => v !== null && v !== "" && !isNaN(v)).map(Number);
  if (!values.length) return "—";
  return (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2);
};

// Live average in modal
const liveAverage = computed(() =>
  computeAverage(form.value.quality, form.value.efficiency, form.value.timeliness)
);

// Group plans by Outcome -> Sub Outcome
const groupedPlans = computed(() => {
  const groups = {};
  props.plans.forEach(plan => {
    const outcome = plan.performance_indicator?.agency_outcome?.outcome || "Uncategorized";
    const subOutcome = plan.performance_indicator?.agency_outcome?.sub_outcome || "—";
    const subAbbrev = subOutcome !== "—" ? subOutcome.slice(0, 4) : subOutcome;

    if (!groups[outcome]) groups[outcome] = {};
    if (!groups[outcome][subAbbrev]) groups[outcome][subAbbrev] = [];
    groups[outcome][subAbbrev].push(plan);
  });

  const sortedGroups = {};
  Object.keys(groups).sort().forEach(outcome => {
    sortedGroups[outcome] = {};
    Object.keys(groups[outcome]).sort().forEach(sub => {
      sortedGroups[outcome][sub] = groups[outcome][sub];
    });
  });

  return sortedGroups;
});

// Open modal only if editing is allowed
const openModal = (plan) => {
  if (!canEdit.value) return; // BLOCK CLICK
  currentPlan.value = plan;
  form.value = {
    accomplishment: plan.pivot?.accomplishment || "",
    mov_link: plan.pivot?.mov_link || "",
    quality: plan.pivot?.sup_quality ?? plan.pivot?.self_quality ?? null,
    efficiency: plan.pivot?.sup_efficiency ?? plan.pivot?.self_efficiency ?? null,
    timeliness: plan.pivot?.sup_timeliness ?? plan.pivot?.self_timeliness ?? null,
  };
  isModalOpen.value = true;
};

// Save modal data to supervisor columns
const saveModal = async () => {
  if (!currentPlan.value) return;

  if (!form.value.accomplishment?.trim() || !form.value.mov_link?.trim()) {
    Swal.fire({
      icon: "warning",
      title: "Missing Required Fields",
      text: "Please fill in BOTH the Accomplishment and MOV Link before saving.",
      confirmButtonColor: "#2563eb"
    });
    return;
  }

  const payload = {
    accomplishment: form.value.accomplishment,
    mov_link: form.value.mov_link,
    sup_quality: form.value.quality,
    sup_efficiency: form.value.efficiency,
    sup_timeliness: form.value.timeliness,
  };

  router.put(
    route("division-chief-employee-ipcr-plan.rateIPCRPlan", [props.ipcr.id, currentPlan.value.id]),
    payload,
    {
      onSuccess: () => {
        const avg = computeAverage(
          form.value.quality,
          form.value.efficiency,
          form.value.timeliness
        );

        currentPlan.value.pivot.accomplishment  = form.value.accomplishment;
        currentPlan.value.pivot.mov_link        = form.value.mov_link;
        currentPlan.value.pivot.sup_quality     = form.value.quality;
        currentPlan.value.pivot.sup_efficiency  = form.value.efficiency;
        currentPlan.value.pivot.sup_timeliness  = form.value.timeliness;
        currentPlan.value.pivot.sup_average     = avg;

        isModalOpen.value = false;

        Swal.fire({
          icon: "success",
          title: "Saved!",
          text: "Accomplishment and supervisor ratings saved successfully.",
          timer: 2000,
          showConfirmButton: false,
        });
      },
      onError: () => {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "Failed to save. Please check your input.",
        });
      }
    }
  );
};

// Status badge class
const statusBadgeClass = (status) => {
  switch (status) {
      case 'New Target': return 'bg-blue-100 text-blue-700'
      case 'For Review': return 'bg-yellow-100 text-yellow-700'
      case 'Targets Approved': return 'bg-green-100 text-green-700'
      case 'Submitted for Rating': return 'bg-orange-100 text-orange-700'
      case 'Rated & For PMT Review': return 'bg-violet-100 text-violet-700'
      case 'Approved by PMT': return 'bg-red-100 text-red-700'
      default: return 'bg-gray-100 text-gray-700'
  }
};

// Submit functions (use props.ipcr.id!)
const saveRatings = () => {
  Swal.fire({
    title: "Save Ratings?",
    text: "Are you sure you want to save these ratings?",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Yes, save it!",
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route("division-chief-employee-ipcr.saveratings", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Saved!", "Ratings for the rating period successfully saved!", "success"),
        onError: () => Swal.fire("Error", "Failed to save ratings.", "error")
      });
    }
  });
};

const submitForRating = () => {
  Swal.fire({
    title: "Submit Accomplishment?",
    text: "This will submit all your accomplishment entries for rating.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Submit",
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route("employee-ipcr.submitRating", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Submitted!", "Accomplishments submitted for rating.", "success"),
        onError: () => Swal.fire("Error", "Failed to submit accomplishments.", "error")
      });
    }
  });
};
const approveTargets = () => {
  Swal.fire({
    title: "Approve Targets?",
    text: "Are you sure you want to approve these targets?",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Yes, approve it!",
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route("division-chief-employee-ipcr.targetsapproval", props.ipcr.id), {}, {
        onSuccess: () => Swal.fire("Approved!", "Targets for the rating period successfully approved!", "success"),
        onError: () => Swal.fire("Error", "Failed to approved targets.", "error")
      });
    }
  });
};

</script>

<template>
  <Head :title="`IPCR #${ipcr.id} Plans`" />

  <AdminLayout :title="`IPCR: ${ipcr.title}`">
    <div class="p-6">

      <!-- Back Button -->
      <button
        @click="$inertia.get(route('employee-ipcr.index'))"
        class="mb-4 flex items-center gap-2 text-blue-600 hover:text-blue-800"
      >
        <ArrowLeftIcon class="w-5 h-5" /> Back to IPCR List
      </button>

      <!-- IPCR Details -->
      <div class="bg-white p-4 rounded-lg shadow mb-4">
        <h2 class="text-2xl font-semibold">{{ ipcr.title }}</h2>
        <p class="text-gray-600">Rating Period: {{ ipcr.rating_period }}</p>

        <!-- Status Badge -->
        <div class="flex items-center gap-2 mt-1">
          <span class="text-gray-600">Status:</span>
          <span
            :class="statusBadgeClass(ipcr.status)"
            class="px-2 py-1 text-xs font-semibold rounded-full"
          >
            {{ ipcr.status }}
          </span>
        </div>

        

        <div class="mt-4">
          <button
            v-if="ipcr.status === 'Submitted for Rating'"
            @click="saveRatings"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow"
          >
            Save Ratings
          </button>

          <button
            v-if="ipcr.status === 'Target Approved'"
            @click="submitForRating"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow"
          >
            Submit for Rating of the Accomplishment
          </button>
          <button
            v-if="ipcr.status === 'For Review'"
            @click="approveTargets"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow"
          >
            Approved Targets
          </button>
        </div>
      </div>

      <!-- Plans Table -->
      <div class="bg-white p-4 rounded-lg shadow">
        <table class="min-w-full border border-gray-300 text-sm border-collapse">
          <thead class="bg-gray-100 text-gray-700 text-sm uppercase">
            <tr>
              <th rowspan="2" colspan="2" class="border px-4 py-2 text-center">Output</th>
              <th rowspan="2" class="border px-4 py-2">Success Indicators</th>
              <th rowspan="2" class="border px-4 py-2">Actual Accomplishment</th>
              <th rowspan="2" class="border px-4 py-2">Means of Verification</th>
              <th colspan="4" class="border px-4 py-2 text-center">Rating</th>
              <th rowspan="2" class="border px-4 py-2 text-center">Remarks</th>
            </tr>
            <tr>
              <th class="border px-4 py-2 text-center">Q</th>
              <th class="border px-4 py-2 text-center">E</th>
              <th class="border px-4 py-2 text-center">T</th>
              <th class="border px-4 py-2 text-center">A</th>
            </tr>
          </thead>

          <tbody>
            <template v-for="(subGroups, outcome) in groupedPlans" :key="outcome">
              <tr class="bg-gray-200">
                <td colspan="10" class="px-4 py-2 font-bold text-gray-700 border border-gray-300">
                  {{ outcome }}
                </td>
              </tr>

              <template v-for="(plans, subAbbrev) in subGroups" :key="subAbbrev">
                <tr
                  v-for="(plan, index) in plans"
                  :key="plan.id"
                  class="hover:bg-gray-50"
                >
                  <td
                    v-if="index === 0"
                    :rowspan="plans.length"
                    class="px-4 py-2 font-medium text-gray-700 border border-gray-300"
                  >
                    {{ subAbbrev }}
                  </td>

                  <td class="px-4 py-2 border border-gray-300">
                    {{ plan.performance_indicator?.description }}
                  </td>

                  <td class="px-4 py-2 border border-gray-300">
                    {{ plan.success_indicator }}
                  </td>

                  <!-- Accomplishment Cell -->
                  <td
                    class="px-4 py-2 border border-gray-300"
                    :class="canEdit
                      ? 'text-blue-600 cursor-pointer hover:underline'
                      : 'text-gray-400 cursor-default'"
                    @click="canEdit ? openModal(plan) : null"
                  >
                    {{ plan.pivot?.accomplishment || '—' }}
                  </td>

                  <!-- MOV Link Cell -->
                  <td class="px-4 py-2 border border-gray-300">
                    <template v-if="plan.pivot?.mov_link">
                      <a
                        :href="plan.pivot.mov_link"
                        target="_blank"
                        class="text-blue-600 hover:underline break-all"
                      >
                        {{ plan.pivot.mov_link }}
                      </a>
                    </template>
                    <span v-else>—</span>
                  </td>

                  <!-- Display self ratings first, then sup ratings if available -->
                  <td class="px-4 py-2 text-center border border-gray-300">
                    {{ plan.pivot?.sup_quality ?? plan.pivot?.self_quality ?? "—" }}
                  </td>
                  <td class="px-4 py-2 text-center border border-gray-300">
                    {{ plan.pivot?.sup_efficiency ?? plan.pivot?.self_efficiency ?? "—" }}
                  </td>
                  <td class="px-4 py-2 text-center border border-gray-300">
                    {{ plan.pivot?.sup_timeliness ?? plan.pivot?.self_timeliness ?? "—" }}
                  </td>
                  <td class="px-4 py-2 text-center font-medium border border-gray-300">
                    {{ plan.pivot?.sup_average ?? plan.pivot?.self_average ?? "—" }}
                  </td>

                  <td class="px-4 py-2 border border-gray-300">
                    {{ plan.pivot?.remarks ?? "—" }}
                  </td>
                </tr>
              </template>
            </template>

            <tr v-if="plans.length === 0">
              <td colspan="10" class="text-center py-4 text-gray-500 border border-gray-300">
                No plans assigned to this IPCR.
              </td>
            </tr>
            
          </tbody>
        </table>
        

        <!-- Action Button Based on Status -->
        <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Comments and Reccommendations for Development Purposes:
        </label>

        <textarea
          v-model="divisionComments"
          :readonly="!isEditing && divisionComments"
          rows="2"
          class="w-full border rounded px-3 py-2 mb-2 bg-white"
          placeholder="Add comments and suggestions for improvement..."
        ></textarea>

        <div class="flex gap-2">
          <!-- EDIT BUTTON -->
          <button
            v-if="divisionComments && !isEditing && ipcr.status === 'Submitted for Rating'"
            @click="isEditing = true"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg shadow"
          >
            Edit
          </button>

          <!-- SAVE BUTTON -->
          <button
            v-if="isEditing"
            @click="saveDivisionComments"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
          >
            Save Comments
          </button>
        </div>
      </div>


      </div>

      <!-- Rate Accomplishment Modal -->
      <div
        v-if="isModalOpen"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      >
        <div class="bg-white rounded-lg p-6 w-96">
          <h3 class="text-lg font-semibold mb-4">Rate Accomplishment</h3>

          <div class="flex flex-col gap-3">
            <label>
              Accomplishment:
              <textarea
                v-model="form.accomplishment"
                class="border rounded w-full px-2 py-1"
                rows="2"
              ></textarea>
            </label>

            <label>
              MOV Link:
              <input
                type="text"
                v-model="form.mov_link"
                class="border rounded w-full px-2 py-1"
              />
            </label>

            <label>
              Quality:
              <input
                type="number"
                min="0"
                max="5"
                v-model="form.quality"
                class="border rounded w-full px-2 py-1"
              />
            </label>

            <label>
              Efficiency:
              <input
                type="number"
                min="0"
                max="5"
                v-model="form.efficiency"
                class="border rounded w-full px-2 py-1"
              />
            </label>

            <label>
              Timeliness:
              <input
                type="number"
                min="0"
                max="5"
                v-model="form.timeliness"
                class="border rounded w-full px-2 py-1"
              />
            </label>

            <div class="text-right font-medium mt-1">
              Average: {{ liveAverage }}
            </div>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button
              @click="isModalOpen = false"
              class="px-4 py-2 border rounded bg-gray-200 hover:bg-gray-300"
            >
              Cancel
            </button>

            <button
              @click="saveModal"
              class="px-4 py-2 border rounded bg-blue-600 text-white hover:bg-blue-700"
            >
              Save
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
