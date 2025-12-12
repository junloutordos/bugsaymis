import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

export function useOutcomes(props) {
  const outcomesList = ref(props.outcomes || []);
  const showModal = ref(false);
  const modalMode = ref("create");
  const selectedOutcome = ref(null);

  const searchQuery = ref("");
  const currentPage = ref(1);
  const perPage = 10;

  const form = ref({
    id: null,
    outcome: "",
    sub_outcome: "",
    function_type: "",
  });

  const filteredOutcomes = computed(() => {
    const results = outcomesList.value.filter((o) =>
      o?.outcome?.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
    const start = (currentPage.value - 1) * perPage;
    return results.slice(start, start + perPage);
  });

  const totalPages = computed(() => Math.ceil(outcomesList.value.length / perPage));

  const openModal = (mode, outcome = null) => {
  modalMode.value = mode;
  showModal.value = true;

  if ((mode === "edit" || mode === "view") && outcome) {
    Object.assign(form.value, {
      id: outcome.id,
      outcome: outcome.outcome,
      sub_outcome: outcome.sub_outcome ?? "",
      function_type: outcome.function_type ?? "",
    });
  } else {
    Object.assign(form.value, {
      id: null,
      outcome: "",
      sub_outcome: "",
      function_type: "",
    });
  }

  selectedOutcome.value = outcome;
};


  const closeModal = () => {
    showModal.value = false;
    selectedOutcome.value = null;
  };

  const submitOutcome = async () => {
    const url = "/agency-outcomes";
    try {
      if (modalMode.value === "create") {
        router.post(url, form.value, {
          onSuccess: async (page) => {
            closeModal();
            outcomesList.value.unshift(
              page.props.outcome ?? { ...form.value, id: Date.now(), created_at: new Date() }
            );
            await Swal.fire("Success", "Outcome has been added successfully", "success");
            window.location.reload();
          },
          onError: async (errors) => {
            await Swal.fire("Error", Object.values(errors).flat().join(", "), "error");
          },
        });
      } else if (modalMode.value === "edit") {
        router.put(`${url}/${form.value.id}`, form.value, {
          onSuccess: async (page) => {
            closeModal();
            const index = outcomesList.value.findIndex((o) => o.id === form.value.id);
            if (index !== -1) outcomesList.value[index] = page.props.outcome ?? { ...form.value };
            await Swal.fire("Updated", "Outcome has been updated successfully", "success");
            window.location.reload();
          },
          onError: async (errors) => {
            await Swal.fire("Error", Object.values(errors).flat().join(", "), "error");
          },
        });
      }
    } catch (err) {
      console.error(err);
      await Swal.fire("Error", "Something went wrong", "error");
    }
  };

  const deleteOutcome = async (outcome) => {
    const result = await Swal.fire({
      title: `Delete outcome "${outcome?.outcome ?? ""}"?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
      cancelButtonText: "Cancel",
    });

    if (result.isConfirmed) {
      router.delete(`/agency-outcomes/${outcome.id}`, {
        onSuccess: async () => {
          outcomesList.value = outcomesList.value.filter((o) => o.id !== outcome.id);
          await Swal.fire("Deleted", "Outcome has been deleted", "success");
          window.location.reload();
        },
        onError: async (errors) => {
          await Swal.fire("Error", Object.values(errors).flat().join(", "), "error");
        },
      });
    }
  };

  return {
    outcomesList,
    showModal,
    modalMode,
    selectedOutcome,
    searchQuery,
    currentPage,
    totalPages,
    filteredOutcomes,
    form,
    openModal,
    closeModal,
    submitOutcome,
    deleteOutcome,
  };
}
