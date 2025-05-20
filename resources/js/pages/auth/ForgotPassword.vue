<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import auth from '@/services/auth';

const router = useRouter();
const email = ref('');
const error = ref('');
const success = ref(false);
const loading = ref(false);

const requestReset = async () => {
  loading.value = true;
  error.value = '';
  
  try {
    const response = await auth.forgotPassword(email.value);
    success.value = true;
    
    // For development, log the reset URL
    if (response.debug) {
      console.log('Reset URL:', response.debug.reset_url);
      console.log('Token:', response.debug.token);
    }
  } catch (err) {
    error.value = err.response?.data?.email || 'An error occurred. Please try again.';
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
    <div class="w-full max-w-md rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-neutral-900">Reset Password</h1>
      
      <div v-if="success" class="rounded-md bg-green-50 p-4 text-green-700 mb-4">
        If this is a valid account email, you will receive a password reset email.
      </div>
      
      <form v-if="!success" @submit.prevent="requestReset" class="space-y-4">
        <div v-if="error" class="rounded-md bg-red-50 p-4 text-sm text-red-500">
          {{ error }}
        </div>
        
        <div>
          <label for="email" class="mb-1 block text-sm font-medium text-neutral-700">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            required
            class="w-full rounded-md border border-neutral-300 px-3 py-2 text-neutral-900 focus:border-neutral-500 focus:outline-none"
          />
        </div>
        
        <div>
          <button
            type="submit"
            :disabled="loading"
            class="w-full rounded-md bg-neutral-900 px-4 py-2 text-white hover:bg-neutral-800 focus:outline-none disabled:opacity-70"
          >
            {{ loading ? 'Sending...' : 'Send Reset Link' }}
          </button>
        </div>
        
        <div class="text-center text-sm text-neutral-600">
          <router-link to="/login" class="font-medium text-neutral-900 hover:underline">
            Back to Login
          </router-link>
        </div>
      </form>
      
      <div v-if="success" class="text-sm">
        <button
          @click="router.push('/login')"
          class="font-medium text-neutral-900 hover:underline"
        >
          Back to Login
        </button>
      </div>
    </div>
  </div>
</template>
