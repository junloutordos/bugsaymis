<script setup>
import { computed } from "vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppButton from "@/Components/AppButton.vue"
import { useDostChainWizard } from "@/Composables/useDostChainWizard.js"

const props = defineProps({
  pillars: { type: Array, default: () => [] },
  agencyOutcomes: { type: Array, default: () => [] },
  submitLabel: { type: String, default: "Create" },
})

const emit = defineEmits(["created", "cancel"])

const { state, submitting, availableStrategies, availableSubStrategies, submit } = useDostChainWizard(props)

const modeButtonClass = (section, mode) => [
  "px-2.5 py-1 text-xs rounded-md border transition-colors",
  state.value[section].mode === mode
    ? "bg-indigo-600 border-indigo-600 text-white"
    : "bg-white border-slate-200 text-slate-600 hover:border-slate-300",
]

const setMode = (section, mode) => {
  state.value[section].mode = mode
  if (mode === "skip") {
    state.value[section].id = ""
  }
}

// Selecting a new Pillar mode, or switching pillar away from "existing",
// invalidates any previously-chosen existing Strategy — same cascade for
// Strategy -> Sub-Strategy.
const onPillarChanged = () => {
  state.value.strategy = { mode: "skip", id: "", name: "" }
  state.value.sub_strategy = { mode: "skip", id: "", description: "" }
}

const onStrategyChanged = () => {
  state.value.sub_strategy = { mode: "skip", id: "", description: "" }
}

const allStrategiesFlat = computed(() => props.pillars.flatMap((p) => p.strategies ?? []))

const handleSubmit = async () => {
  const result = await submit()
  if (result) emit("created", result)
}
</script>

<template>
  <div class="space-y-5">
    <!-- Pillar -->
    <div class="rounded-lg border border-slate-200 p-3">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">1. Pillar</span>
        <div class="flex gap-1">
          <button type="button" :class="modeButtonClass('pillar', 'existing')" @click="setMode('pillar', 'existing'); onPillarChanged()">Use existing</button>
          <button type="button" :class="modeButtonClass('pillar', 'new')" @click="setMode('pillar', 'new'); onPillarChanged()">Create new</button>
          <button type="button" :class="modeButtonClass('pillar', 'skip')" @click="setMode('pillar', 'skip'); onPillarChanged()">Skip</button>
        </div>
      </div>

      <AppSelect v-if="state.pillar.mode === 'existing'" v-model="state.pillar.id" placeholder="-- Select Pillar --" @change="onPillarChanged">
        <option v-for="p in pillars" :key="p.id" :value="p.id">{{ p.name }}</option>
      </AppSelect>

      <div v-else-if="state.pillar.mode === 'new'" class="space-y-2">
        <AppInput v-model="state.pillar.name" type="text" placeholder="Pillar name" required />
        <AppTextarea v-model="state.pillar.outcome_statement" placeholder="Outcome statement (optional)" :rows="2" />
      </div>

      <p v-else class="text-xs text-slate-400">No Pillar will be tagged.</p>
    </div>

    <!-- Strategy -->
    <div class="rounded-lg border border-slate-200 p-3">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">2. Strategy</span>
        <div class="flex gap-1">
          <button type="button" :class="modeButtonClass('strategy', 'existing')" @click="setMode('strategy', 'existing'); onStrategyChanged()">Use existing</button>
          <button type="button" :class="modeButtonClass('strategy', 'new')" @click="setMode('strategy', 'new'); onStrategyChanged()" :disabled="state.pillar.mode === 'skip'">Create new</button>
          <button type="button" :class="modeButtonClass('strategy', 'skip')" @click="setMode('strategy', 'skip'); onStrategyChanged()">Skip</button>
        </div>
      </div>

      <AppSelect v-if="state.strategy.mode === 'existing'" v-model="state.strategy.id" placeholder="-- Select Strategy --" @change="onStrategyChanged">
        <option v-for="s in (state.pillar.mode === 'existing' ? availableStrategies : allStrategiesFlat)" :key="s.id" :value="s.id">{{ s.name }}</option>
      </AppSelect>

      <div v-else-if="state.strategy.mode === 'new'">
        <p v-if="state.pillar.mode === 'skip'" class="text-xs text-amber-600 mb-1">Select or create a Pillar first.</p>
        <AppInput v-model="state.strategy.name" type="text" placeholder="Strategy name" required />
      </div>

      <p v-else class="text-xs text-slate-400">No Strategy will be tagged.</p>
    </div>

    <!-- Sub-Strategy -->
    <div class="rounded-lg border border-slate-200 p-3">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">3. Sub-Strategy</span>
        <div class="flex gap-1">
          <button type="button" :class="modeButtonClass('sub_strategy', 'existing')" @click="setMode('sub_strategy', 'existing')" :disabled="state.strategy.mode !== 'existing'">Use existing</button>
          <button type="button" :class="modeButtonClass('sub_strategy', 'new')" @click="setMode('sub_strategy', 'new')" :disabled="state.strategy.mode === 'skip'">Create new</button>
          <button type="button" :class="modeButtonClass('sub_strategy', 'skip')" @click="setMode('sub_strategy', 'skip')">Skip</button>
        </div>
      </div>

      <AppSelect v-if="state.sub_strategy.mode === 'existing'" v-model="state.sub_strategy.id" placeholder="-- Select Sub-Strategy --">
        <option v-for="sub in availableSubStrategies" :key="sub.id" :value="sub.id">{{ sub.description }}</option>
      </AppSelect>

      <div v-else-if="state.sub_strategy.mode === 'new'">
        <p v-if="state.strategy.mode === 'skip'" class="text-xs text-amber-600 mb-1">Select or create a Strategy first.</p>
        <AppTextarea v-model="state.sub_strategy.description" placeholder="Sub-Strategy description" :rows="2" required />
      </div>

      <p v-else class="text-xs text-slate-400">No Sub-Strategy will be tagged.</p>
    </div>

    <!-- PSHS Program -->
    <div class="rounded-lg border border-slate-200 p-3">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">4. PSHS Program</span>
        <div class="flex gap-1">
          <button type="button" :class="modeButtonClass('program', 'existing')" @click="setMode('program', 'existing')">Use existing</button>
          <button type="button" :class="modeButtonClass('program', 'new')" @click="setMode('program', 'new')">Create new</button>
          <button type="button" :class="modeButtonClass('program', 'skip')" @click="setMode('program', 'skip')">Skip</button>
        </div>
      </div>

      <AppSelect v-if="state.program.mode === 'existing'" v-model="state.program.id" placeholder="-- Select Program --">
        <option v-for="o in agencyOutcomes" :key="o.id" :value="o.id">{{ o.outcome }}</option>
      </AppSelect>

      <div v-else-if="state.program.mode === 'new'" class="space-y-2">
        <AppInput v-model="state.program.outcome" type="text" placeholder="Program name, e.g. E. New Program" required />
        <AppSelect v-model="state.program.function_type" placeholder="Function type">
          <option value="Strategic Functions">Strategic Functions</option>
          <option value="Core Functions">Core Functions</option>
          <option value="Support Functions">Support Functions</option>
        </AppSelect>
      </div>

      <p v-else class="text-xs text-slate-400">No Program will be tagged.</p>
    </div>

    <div class="flex justify-end gap-2">
      <AppButton type="button" variant="secondary" @click="emit('cancel')">Cancel</AppButton>
      <AppButton type="button" :loading="submitting" @click="handleSubmit">{{ submitLabel }}</AppButton>
    </div>
  </div>
</template>
