<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
      <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="$emit('close')">✕</button>
      <h2 class="text-xl font-semibold mb-4">Edit Profile</h2>
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Name</label>
          <input v-model="form.name" type="text" class="mt-1 block w-full rounded border-gray-300" required />
          <p v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input v-model="form.email" type="email" class="mt-1 block w-full rounded border-gray-300 bg-gray-100" disabled />
          <p v-if="form.errors.email" class="text-red-600 text-sm mt-1">{{ form.errors.email }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Profile Picture (JPEG/JPG)</label>
          <input ref="profileFile" @change="onProfileFileChange" type="file" accept=".jpeg,.jpg,image/jpeg" class="mt-1 block w-full" />
          <p v-if="form.errors.profile_picture" class="text-red-600 text-sm mt-1">{{ form.errors.profile_picture }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Electronic Signature (PNG)</label>
          <input ref="signatureFile" @change="onSignatureFileChange" type="file" accept=".png,image/png" class="mt-1 block w-full" />
          <p v-if="form.errors.electronic_signature" class="text-red-600 text-sm mt-1">{{ form.errors.electronic_signature }}</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="$emit('close')" class="px-4 py-2 rounded border">Cancel</button>
            <button :disabled="form.processing" type="submit" class="bg-blue-600 text-white px-4 py-2 rounded disabled:opacity-60">Save</button>
        </div>
          <div v-if="statusMessage" class="mt-2 text-sm text-green-600">{{ statusMessage }}</div>
          <div v-if="errorMessage" class="mt-2 text-sm text-red-600">{{ errorMessage }}</div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { watch, toRefs, ref } from 'vue';

const props = defineProps({
  show: Boolean,
  user: Object,
});
const emit = defineEmits(['close']);

const { user } = toRefs(props);
const page = usePage();
const pageUser = page.props.auth?.user || {};
console.log('ProfileEditModal props on init', { show: props.show, propUser: props.user, pageUser });
watch(() => props.show, (v) => console.log('ProfileEditModal show changed:', v));

const form = useForm({
  name: user.value?.name || pageUser.name || '',
  email: user.value?.email || pageUser.email || '',
});

watch(
  () => props.user,
  (val) => {
    console.log('ProfileEditModal prop user changed:', val);
    form.name = val?.name || pageUser.name || '';
    form.email = val?.email || pageUser.email || '';
  },
  { immediate: true }
);

const statusMessage = ref('');
const errorMessage = ref('');
const profileFile = ref(null);
const signatureFile = ref(null);

const onProfileFileChange = (e) => {
  profileFile.value = e.target.files && e.target.files[0] ? e.target.files[0] : null;
};

const onSignatureFileChange = (e) => {
  signatureFile.value = e.target.files && e.target.files[0] ? e.target.files[0] : null;
};

const submit = () => {
  console.log('ProfileEditModal submit', { name: form.name, email: form.email });
  statusMessage.value = '';
  errorMessage.value = '';
  form.processing = true;

  const payload = new FormData();
  payload.append('name', form.name);
  payload.append('email', form.email);
  if (profileFile.value) payload.append('profile_picture', profileFile.value);
  if (signatureFile.value) payload.append('electronic_signature', signatureFile.value);

  payload.append('_method', 'PATCH');

  axios.post(route('profile.update'), payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
    .then(() => {
      console.log('Profile update success');
      statusMessage.value = 'Profile updated successfully.';
      emit('close');
      setTimeout(() => window.location.reload(), 300);
    })
    .catch((err) => {
      console.log('Profile update error', err);
      if (err.response && err.response.status === 422) {
        const errors = err.response.data.errors || {};
        form.errors = errors;
        errorMessage.value = Object.values(errors).flat().join(' ');
      } else {
        errorMessage.value = 'An unexpected error occurred.';
      }
    })
    .finally(() => {
      form.processing = false;
    });
};

</script>
