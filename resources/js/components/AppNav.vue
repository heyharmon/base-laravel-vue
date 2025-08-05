<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import auth from '@/services/auth';

const router = useRouter();
const isAuthenticated = computed(() => auth.isAuthenticated());
const user = computed(() => auth.getUser());

const logout = async () => {
  await auth.logout();
  router.push('/login');
};
</script>

<template>
  <nav class="bg-neutral-900 text-white">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <router-link to="/" class="text-xl font-bold">Paraloom</router-link>
        <div v-if="isAuthenticated" class="flex items-center space-x-4 ml-6">
          <router-link to="/" class="text-sm hover:text-neutral-300">Dashboard</router-link>
          <router-link to="/organizations" class="text-sm hover:text-neutral-300">Organizations</router-link>
          <router-link to="/teams" class="text-sm hover:text-neutral-300">Teams</router-link>
        </div>
      </div>
      
      <div class="flex items-center space-x-4">
        <template v-if="isAuthenticated">
          <span class="text-sm">{{ user?.name }}</span>
          <button 
            @click="logout" 
            class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-sm cursor-pointer"
          >
            Logout
          </button>
        </template>
        <template v-else>
          <router-link 
            to="/login" 
            class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-sm"
          >
            Login
          </router-link>
          <router-link 
            to="/register" 
            class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-sm"
          >
            Register
          </router-link>
        </template>
      </div>
    </div>
  </nav>
</template>
