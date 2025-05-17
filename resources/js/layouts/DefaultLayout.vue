<script setup>
import AppNav from '@/components/AppNav.vue';
</script>

<template>
  <div class="min-h-screen bg-neutral-100">
    <AppNav />
    
    <main class="max-w-7xl mx-auto px-4">
      <slot />
    </main>
  </div>
</template>
