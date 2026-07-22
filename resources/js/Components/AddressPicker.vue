<template>
  <div class="space-y-4">
    <!-- Freetext detail fields -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">House No. / Purok</label>
        <input
          :value="modelValue.house"
          @input="emit('update:modelValue', { ...modelValue, house: $event.target.value })"
          :disabled="readonly"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full disabled:bg-slate-50 disabled:text-slate-500"
          placeholder="House No., Purok, Door No."
        />
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Street</label>
        <input
          :value="modelValue.street"
          @input="emit('update:modelValue', { ...modelValue, street: $event.target.value })"
          :disabled="readonly"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full disabled:bg-slate-50 disabled:text-slate-500"
          placeholder="Street name"
        />
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Village / Subdivision</label>
        <input
          :value="modelValue.subdivision"
          @input="emit('update:modelValue', { ...modelValue, subdivision: $event.target.value })"
          :disabled="readonly"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full disabled:bg-slate-50 disabled:text-slate-500"
          placeholder="Village or subdivision"
        />
      </div>
    </div>

    <!-- PSGC cascade -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <!-- Region -->
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">
          Region <span v-if="!readonly" class="text-red-500">*</span>
        </label>
        <select
          v-model="selectedRegionCode"
          :disabled="readonly || loadingRegions"
          @change="onRegionChange"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full disabled:bg-slate-50 disabled:text-slate-500"
        >
          <option value="">{{ loadingRegions ? 'Loading...' : '— Select Region —' }}</option>
          <option v-for="r in regions" :key="r.code" :value="r.code">{{ r.name }}</option>
        </select>
      </div>

      <!-- Province / HUC -->
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Province / City</label>
        <select
          v-model="selectedProvinceCode"
          :disabled="readonly || !selectedRegionCode || loadingProvinces"
          @change="onProvinceChange"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full disabled:bg-slate-50 disabled:text-slate-500"
        >
          <option value="">
            {{ loadingProvinces ? 'Loading...' : (selectedRegionCode ? '— Select Province/City —' : '— Select Region first —') }}
          </option>
          <option v-for="p in provinces" :key="p.code" :value="p.code">
            {{ p.name }}{{ p.type === 'city' ? ' ★' : '' }}
          </option>
        </select>
        <p v-if="provinces.some(p => p.type === 'city')" class="text-xs text-slate-400 mt-0.5">
          ★ = Highly Urbanized / Independent City
        </p>
      </div>

      <!-- City/Municipality (hidden when HUC selected at province level) -->
      <div v-if="!selectedProvinceIsHUC">
        <label class="block text-xs font-medium text-slate-600 mb-1">
          City / Municipality <span v-if="!readonly" class="text-red-500">*</span>
        </label>
        <select
          v-model="selectedCityCode"
          :disabled="readonly || !selectedProvinceCode || loadingCities"
          @change="onCityChange"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full disabled:bg-slate-50 disabled:text-slate-500"
        >
          <option value="">
            {{ loadingCities ? 'Loading...' : (selectedProvinceCode ? '— Select City/Municipality —' : '— Select Province first —') }}
          </option>
          <option v-for="c in cities" :key="c.code" :value="c.code">{{ c.name }}</option>
        </select>
      </div>
      <div v-else>
        <label class="block text-xs font-medium text-slate-600 mb-1">City / Municipality</label>
        <input
          :value="modelValue.city"
          disabled
          class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm w-full text-slate-500"
        />
        <p class="text-xs text-slate-400 mt-0.5">Auto-set from selected city above</p>
      </div>

      <!-- Barangay -->
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">
          Barangay <span v-if="!readonly" class="text-red-500">*</span>
        </label>
        <select
          v-model="selectedBarangayName"
          :disabled="readonly || !activeCityCode || loadingBarangays"
          @change="onBarangayChange"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full disabled:bg-slate-50 disabled:text-slate-500"
        >
          <option value="">
            {{ loadingBarangays ? 'Loading...' : (activeCityCode ? '— Select Barangay —' : '— Select City first —') }}
          </option>
          <option v-for="b in barangays" :key="b.code" :value="b.name">{{ b.name }}</option>
        </select>
      </div>
    </div>

    <div
      v-if="showSavedLocationFallback"
      class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
    >
      <span class="font-medium">Saved location:</span> {{ savedLocationSummary }}
      <span v-if="!readonly" class="block text-xs text-amber-700">
        Select the region again to refresh this address using the PSGC list.
      </span>
    </div>

    <!-- Zip code -->
    <div class="w-40">
      <label class="block text-xs font-medium text-slate-600 mb-1">Zip Code</label>
      <input
        :value="modelValue.zip"
        @input="emit('update:modelValue', { ...modelValue, zip: $event.target.value })"
        :disabled="readonly"
        maxlength="10"
        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full disabled:bg-slate-50 disabled:text-slate-500"
        placeholder="e.g. 8600"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({
      house: '', street: '', subdivision: '',
      region: '', province: '', city: '', barangay: '', zip: '',
    }),
  },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const regions   = ref([])
const provinces = ref([])
const cities    = ref([])
const barangays = ref([])

const loadingRegions   = ref(false)
const loadingProvinces = ref(false)
const loadingCities    = ref(false)
const loadingBarangays = ref(false)
const initializing = ref(true)

const selectedRegionCode   = ref('')
const selectedProvinceCode = ref('')
const selectedCityCode     = ref('')
const selectedBarangayName = ref('')

const selectedProvinceIsHUC = computed(() => {
  const p = provinces.value.find(x => x.code === selectedProvinceCode.value)
  return p?.type === 'city'
})

const activeCityCode = computed(() =>
  selectedProvinceIsHUC.value ? selectedProvinceCode.value : selectedCityCode.value
)

const savedLocationSummary = computed(() => [
  props.modelValue.barangay ? `Brgy. ${props.modelValue.barangay}` : '',
  props.modelValue.city,
  props.modelValue.province,
  props.modelValue.region,
].filter(Boolean).join(', '))

const showSavedLocationFallback = computed(() =>
  !initializing.value && !!savedLocationSummary.value && !selectedRegionCode.value
)

onMounted(async () => {
  loadingRegions.value = true
  try {
    const { data } = await axios.get('/api/psgc/regions')
    regions.value = data
    await tryInitFromValue()
  } finally {
    loadingRegions.value = false
    initializing.value = false
  }
})

async function tryInitFromValue() {
  const v = props.modelValue
  if (!v?.region) return

  const matchRegion = regions.value.find(r =>
    r.name.toLowerCase() === v.region.toLowerCase()
  )
  if (!matchRegion) return
  selectedRegionCode.value = matchRegion.code

  await loadProvinces(matchRegion.code)
  if (!v.province && !v.city) return

  const matchProvince = provinces.value.find(p =>
    p.name.toLowerCase() === (v.province || '').toLowerCase()
  ) || provinces.value.find(p =>
    p.type === 'city' && p.name.toLowerCase() === (v.city || '').toLowerCase()
  )
  if (!matchProvince) return
  selectedProvinceCode.value = matchProvince.code

  if (matchProvince.type === 'city') {
    await loadBarangays(matchProvince.code)
    selectedBarangayName.value = v.barangay || ''
    return
  }

  await loadCities(matchProvince.code)
  if (!v.city) return

  const matchCity = cities.value.find(c =>
    c.name.toLowerCase() === v.city.toLowerCase()
  )
  if (!matchCity) return
  selectedCityCode.value = matchCity.code

  await loadBarangays(matchCity.code)
  selectedBarangayName.value = v.barangay || ''
}

async function loadProvinces(regionCode) {
  provinces.value = []
  cities.value = []
  barangays.value = []
  selectedProvinceCode.value = ''
  selectedCityCode.value = ''
  selectedBarangayName.value = ''
  if (!regionCode) return
  loadingProvinces.value = true
  try {
    const { data } = await axios.get('/api/psgc/provinces', { params: { region: regionCode } })
    provinces.value = data
  } finally {
    loadingProvinces.value = false
  }
}

async function loadCities(provinceCode) {
  cities.value = []
  barangays.value = []
  selectedCityCode.value = ''
  selectedBarangayName.value = ''
  if (!provinceCode) return
  loadingCities.value = true
  try {
    const { data } = await axios.get('/api/psgc/cities', { params: { province: provinceCode } })
    cities.value = data
  } finally {
    loadingCities.value = false
  }
}

async function loadBarangays(cityCode) {
  barangays.value = []
  selectedBarangayName.value = ''
  if (!cityCode) return
  loadingBarangays.value = true
  try {
    const { data } = await axios.get('/api/psgc/barangays', { params: { city: cityCode } })
    barangays.value = data
  } finally {
    loadingBarangays.value = false
  }
}

async function onRegionChange() {
  const region = regions.value.find(r => r.code === selectedRegionCode.value)
  emit('update:modelValue', {
    ...props.modelValue,
    region: region?.name ?? '',
    province: '', city: '', barangay: '',
  })
  await loadProvinces(selectedRegionCode.value)
}

async function onProvinceChange() {
  const province = provinces.value.find(p => p.code === selectedProvinceCode.value)
  if (!province) return

  if (province.type === 'city') {
    emit('update:modelValue', {
      ...props.modelValue,
      province: '',
      city: province.name,
      barangay: '',
    })
    await loadBarangays(selectedProvinceCode.value)
  } else {
    emit('update:modelValue', {
      ...props.modelValue,
      province: province.name,
      city: '',
      barangay: '',
    })
    await loadCities(selectedProvinceCode.value)
  }
}

async function onCityChange() {
  const city = cities.value.find(c => c.code === selectedCityCode.value)
  emit('update:modelValue', {
    ...props.modelValue,
    city: city?.name ?? '',
    barangay: '',
  })
  if (selectedCityCode.value) {
    await loadBarangays(selectedCityCode.value)
  }
}

function onBarangayChange() {
  emit('update:modelValue', {
    ...props.modelValue,
    barangay: selectedBarangayName.value,
  })
}

watch(() => props.modelValue?.region, (newRegion, oldRegion) => {
  if (newRegion === oldRegion) return
  if (newRegion && newRegion !== (regions.value.find(r => r.code === selectedRegionCode.value)?.name ?? '')) {
    selectedRegionCode.value = ''
    selectedProvinceCode.value = ''
    selectedCityCode.value = ''
    selectedBarangayName.value = ''
    tryInitFromValue()
  }
})
</script>
