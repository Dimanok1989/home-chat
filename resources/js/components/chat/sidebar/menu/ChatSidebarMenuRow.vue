<script setup>
defineEmits(['click']);
</script>

<template>
    <div class="px-1">
        <button
            type="button"
            class="flex w-full items-center gap-2 rounded-2xl px-3 py-2 text-sm font-bold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
            @click="$emit('click')"
        >
            <slot name="icon" />
            <slot />
        </button>
    </div>
</template>
