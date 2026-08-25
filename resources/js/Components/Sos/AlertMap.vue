<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({ lat: { type: Number, default: null }, lng: { type: Number, default: null }, label: { type: String, default: null } })

const mapEl = ref(null)
let map = null
let marker = null

function render() {
  if (!mapEl.value || props.lat === null || props.lng === null) return

  if (!map) {
    map = L.map(mapEl.value).setView([props.lat, props.lng], 17)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)
  } else {
    map.setView([props.lat, props.lng], 17)
  }

  if (marker) marker.remove()
  marker = L.marker([props.lat, props.lng]).addTo(map)
  if (props.label) marker.bindPopup(props.label).openPopup()
}

onMounted(render)
watch(() => [props.lat, props.lng, props.label], render)
onUnmounted(() => { if (map) map.remove() })
</script>

<template>
  <div v-if="lat !== null && lng !== null" ref="mapEl" class="h-48 w-full rounded-lg border border-slate-200"></div>
  <div v-else class="flex h-24 items-center justify-center rounded-lg border border-dashed border-slate-200 text-xs text-slate-400">
    No GPS data reported
  </div>
</template>
