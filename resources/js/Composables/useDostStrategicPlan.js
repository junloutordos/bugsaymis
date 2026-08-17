import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

export function useDostStrategicPlan() {
  const expandedPillars = ref(new Set());
  const expandedStrategies = ref(new Set());

  function togglePillar(id) {
    if (expandedPillars.value.has(id)) expandedPillars.value.delete(id);
    else expandedPillars.value.add(id);
  }

  function toggleStrategy(id) {
    if (expandedStrategies.value.has(id)) expandedStrategies.value.delete(id);
    else expandedStrategies.value.add(id);
  }

  function flashErrors(errors) {
    return Swal.fire("Error", Object.values(errors).flat().join(", "), "error");
  }

  // ── Pillar ─────────────────────────────────────────────────────────
  const showPillarModal = ref(false);
  const pillarModalMode = ref("create");
  const pillarForm = ref({ id: null, name: "", outcome_statement: "" });

  function openPillarModal(mode, pillar = null) {
    pillarModalMode.value = mode;
    pillarForm.value = pillar
      ? { id: pillar.id, name: pillar.name, outcome_statement: pillar.outcome_statement ?? "" }
      : { id: null, name: "", outcome_statement: "" };
    showPillarModal.value = true;
  }

  function closePillarModal() {
    showPillarModal.value = false;
  }

  function submitPillar() {
    const isCreate = pillarModalMode.value === "create";
    const url = isCreate ? "/dost-pillars" : `/dost-pillars/${pillarForm.value.id}`;
    router[isCreate ? "post" : "put"](url, pillarForm.value, {
      preserveScroll: true,
      onSuccess: () => {
        closePillarModal();
        router.reload({ only: ["pillars"] });
      },
      onError: flashErrors,
    });
  }

  async function deletePillar(pillar) {
    const result = await Swal.fire({
      title: `Delete pillar "${pillar.name}"?`,
      text: `This will also delete ${pillar.strategies.length} strategies and everything under them. This cannot be undone.`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    });
    if (!result.isConfirmed) return;

    router.delete(`/dost-pillars/${pillar.id}`, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["pillars"] }),
    });
  }

  // ── Strategy ───────────────────────────────────────────────────────
  const showStrategyModal = ref(false);
  const strategyModalMode = ref("create");
  const strategyForm = ref({ id: null, dost_pillar_id: null, agency_outcome_id: null, name: "" });

  function openStrategyModal(mode, strategy = null, pillar = null) {
    strategyModalMode.value = mode;
    strategyForm.value = strategy
      ? {
          id: strategy.id,
          dost_pillar_id: strategy.dost_pillar_id,
          agency_outcome_id: strategy.agency_outcome_id,
          name: strategy.name,
        }
      : { id: null, dost_pillar_id: pillar?.id ?? null, agency_outcome_id: null, name: "" };
    showStrategyModal.value = true;
  }

  function closeStrategyModal() {
    showStrategyModal.value = false;
  }

  function submitStrategy() {
    const isCreate = strategyModalMode.value === "create";
    const url = isCreate ? "/dost-strategies" : `/dost-strategies/${strategyForm.value.id}`;
    router[isCreate ? "post" : "put"](url, strategyForm.value, {
      preserveScroll: true,
      onSuccess: () => {
        closeStrategyModal();
        router.reload({ only: ["pillars"] });
      },
      onError: flashErrors,
    });
  }

  async function deleteStrategy(strategy) {
    const result = await Swal.fire({
      title: `Delete strategy "${strategy.name}"?`,
      text: `This will also delete ${strategy.sub_strategies.length} sub-strategies under it. This cannot be undone.`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    });
    if (!result.isConfirmed) return;

    router.delete(`/dost-strategies/${strategy.id}`, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["pillars"] }),
    });
  }

  // ── Sub-Strategy ───────────────────────────────────────────────────
  const showSubStrategyModal = ref(false);
  const subStrategyModalMode = ref("create");
  const subStrategyForm = ref({ id: null, dost_strategy_id: null, description: "" });

  function openSubStrategyModal(mode, sub = null, strategy = null) {
    subStrategyModalMode.value = mode;
    subStrategyForm.value = sub
      ? { id: sub.id, dost_strategy_id: sub.dost_strategy_id, description: sub.description }
      : { id: null, dost_strategy_id: strategy?.id ?? null, description: "" };
    showSubStrategyModal.value = true;
  }

  function closeSubStrategyModal() {
    showSubStrategyModal.value = false;
  }

  function submitSubStrategy() {
    const isCreate = subStrategyModalMode.value === "create";
    const url = isCreate ? "/dost-sub-strategies" : `/dost-sub-strategies/${subStrategyForm.value.id}`;
    router[isCreate ? "post" : "put"](url, subStrategyForm.value, {
      preserveScroll: true,
      onSuccess: () => {
        closeSubStrategyModal();
        router.reload({ only: ["pillars"] });
      },
      onError: flashErrors,
    });
  }

  async function deleteSubStrategy(sub) {
    const result = await Swal.fire({
      title: "Delete this sub-strategy?",
      text: "This cannot be undone.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    });
    if (!result.isConfirmed) return;

    router.delete(`/dost-sub-strategies/${sub.id}`, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["pillars"] }),
    });
  }

  return {
    expandedPillars,
    expandedStrategies,
    togglePillar,
    toggleStrategy,
    showPillarModal,
    pillarModalMode,
    pillarForm,
    openPillarModal,
    closePillarModal,
    submitPillar,
    deletePillar,
    showStrategyModal,
    strategyModalMode,
    strategyForm,
    openStrategyModal,
    closeStrategyModal,
    submitStrategy,
    deleteStrategy,
    showSubStrategyModal,
    subStrategyModalMode,
    subStrategyForm,
    openSubStrategyModal,
    closeSubStrategyModal,
    submitSubStrategy,
    deleteSubStrategy,
  };
}
