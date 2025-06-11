<script setup>
import { Primitive } from 'reka-ui'

const props = defineProps({
	variant: {
		type: String,
		default: 'default'
	},
	size: {
		type: String,
		default: 'default'
	},
	as: {
		type: String,
		default: 'button'
	},
	asChild: Boolean,
	loading: Boolean
})

const variants = {
	default: 'bg-black text-white shadow-xs hover:bg-black/80',
	destructive: 'bg-red-600 text-white shadow-xs hover:bg-red-600/80 focus-visible:ring-red/20',
	outline: 'border border-neutral-400 bg-white text-neutral-700 shadow-xs hover:bg-neutral-100',
	secondary: 'bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/80',
	ghost: 'hover:bg-accent hover:text-accent-foreground',
	link: 'text-black underline-offset-4 hover:underline',
	neutral: 'bg-neutral-200 text-neutral-800 hover:bg-neutral-100',
	dark: 'bg-neutral-800 text-white hover:bg-neutral-700',
	primary: 'bg-blue-600 text-white hover:bg-blue-700',
	success: 'bg-emerald-600 text-white hover:bg-emerald-700',
	muted: 'bg-neutral-600 text-white hover:bg-neutral-700'
}

const sizes = {
	default: 'h-9 px-4 py-2 has-[>svg]:px-3',
	xs: 'h-7 rounded-md gap-1 px-2 text-xs',
	sm: 'h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5',
	lg: 'h-10 rounded-md px-6 has-[>svg]:px-4',
	icon: 'size-9'
}
</script>

<template>
	<Primitive
		data-slot="button"
		:as="as"
		:as-child="asChild"
		:class="`${variants[variant]} ${sizes[size]}`"
		class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all cursor-pointer disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 aria-invalid:border-destructive'"
	>
		<div v-if="loading" class="animate-spin rounded-full h-3.5 w-3.5 border border-b-transparent border-neutral-800"></div>
		<slot />
	</Primitive>
</template>
