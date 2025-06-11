<script setup>
import AppNav from '@/components/AppNav.vue'
</script>

<template>
	<div class="min-h-screen bg-white">
		<AppNav />

		<main class="mx-auto px-6">
			<div class="flex flex-row gap-6">
				<!-- Left column (fixed width) -->
				<div class="w-1/3">
					<slot name="left-column" />
				</div>

				<!-- Right column (flexible width) -->
				<div class="w-2/3">
					<slot name="right-column" />
				</div>
			</div>
		</main>
	</div>
</template>
