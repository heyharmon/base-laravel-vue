<script setup>
// Default layout component
</script>

<template>
  <div class="min-h-screen bg-white">
    <header class="border-b border-neutral-200">
      <div class="max-w-7xl p-4 mx-auto flex justify-between items-center">
        <h1 class="text-xl font-semibold">Agent Mentions</h1>
        <nav>
          <!-- <router-link to="/" class="px-4 py-2">Home</router-link> -->
          <!-- <router-link to="/dashboard" class="px-4 py-2">Dashboard</router-link> -->
        </nav>
      </div>
    </header>
    
    <main class="max-w-7xl mx-auto px-4">
      <slot />
    </main>
  </div>
</template>
