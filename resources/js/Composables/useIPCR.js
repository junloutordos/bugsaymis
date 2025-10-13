import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useIPCR(props) {
  const plans = ref(props.plans || [])
  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  const showModal = ref(false)
  const modalMode = ref("view")
  const selectedPlan = ref(null)

  const form = ref({
    target: "",
    accomplishment: "",
    self_quality: 1,
    self_efficiency: 1,
    self_timeliness: 1,
    });


  // --- Safer & more flexible search ---
  const filteredPlans = computed(() => {
    const query = searchQuery.value.toLowerCase()

    const results = plans.value.filter((p) => {
      const fieldsToSearch = [
        p?.title ?? "",
        p?.ipcrs?.[0]?.target ?? "",
        p?.ipcrs?.[0]?.accomplishment ?? "",
        p?.ipcrs?.[0]?.self_rating?.toString() ?? "",
      ]
      return fieldsToSearch.some((field) =>
        field.toLowerCase().includes(query)
      )
    })

    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.ceil(plans.value.length / perPage)
  )

  const openModal = (mode, plan) => {
    modalMode.value = mode
    selectedPlan.value = plan
    showModal.value = true
    form.value = { target: "", accomplishment: "", self_rating: "" }
  }

  const closeModal = () => {
    showModal.value = false
    selectedPlan.value = null
  }

  const submitTarget = async () => {
    router.post(`/ipcrs/${selectedPlan.value.id}/target`,
      { target: form.value.target },
      {
        onSuccess: () => {
          closeModal()
          Swal.fire("Success", "Target submitted", "success").then(() =>
            window.location.reload()
          )
        },
      }
    )
  }

  const submitAccomplishment = async () => {
  router.put(
    `/ipcrs/${selectedPlan.value.ipcrs[0].id}/accomplishment`,
    {
      accomplishment: form.value.accomplishment,
      self_quality: form.value.self_quality,
      self_efficiency: form.value.self_efficiency,
      self_timeliness: form.value.self_timeliness,
    },
    {
      onSuccess: () => {
        closeModal();
        Swal.fire("Success", "Accomplishment submitted", "success").then(() =>
          window.location.reload()
        );
      },
    }
  );
};


  const approveTarget = async (ipcr) => {
    router.put(`/ipcrs/${ipcr.id}/approve`, {}, {
      onSuccess: () =>
        Swal.fire("Approved", "Target approved", "success").then(() =>
          window.location.reload()
        ),
    })
  }

const reviewAccomplishment = async (ipcr) => {
  const { value: values } = await Swal.fire({
    title: "Supervisor Rating (Q/E/T)",
    html:
      '<input id="q" type="number" min="1" max="5" placeholder="Quality" class="swal2-input">' +
      '<input id="e" type="number" min="1" max="5" placeholder="Efficiency" class="swal2-input">' +
      '<input id="t" type="number" min="1" max="5" placeholder="Timeliness" class="swal2-input">',
    focusConfirm: false,
    showCancelButton: true,
    preConfirm: () => {
      return {
        sup_quality: Number(document.getElementById('q').value),
        sup_efficiency: Number(document.getElementById('e').value),
        sup_timeliness: Number(document.getElementById('t').value),
      };
    },
  });

  if (values) {
    router.put(`/ipcrs/${ipcr.id}/review`, values, {
      onSuccess: () =>
        Swal.fire("Reviewed", "Supervisor rating saved", "success").then(() =>
          window.location.reload()
        ),
    });
  }
};


  return {
    searchQuery,
    currentPage,
    totalPages,
    filteredPlans,
    showModal,
    modalMode,
    selectedPlan,
    form,
    openModal,
    closeModal,
    submitTarget,
    submitAccomplishment,
    approveTarget,
    reviewAccomplishment,
  }
}
