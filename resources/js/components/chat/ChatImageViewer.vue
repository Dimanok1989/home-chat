<script setup>
import { computed } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    images: {
        type: Array,
        default: () => [],
    },
    index: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['close', 'goNewer', 'goOlder', 'showContextMenu']);

const currentImage = computed(() => props.images[props.index] ?? null);
</script>

<template>
    <div
        v-if="open && currentImage"
        class="fixed inset-0 z-50 flex flex-col bg-black/90"
        @click.self="emit('close')"
    >
        <div class="flex items-center justify-between px-4 py-3 text-white">
            <p class="text-sm text-white/80">
                {{ index + 1 }} / {{ images.length }}
            </p>
            <button
                type="button"
                class="rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                title="Закрыть"
                @click="emit('close')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div
            class="relative flex flex-1 items-center justify-center px-4 pb-4"
            @click.self="emit('close')"
        >
            <button
                v-if="index > 0"
                type="button"
                class="absolute left-4 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
                title="Новее"
                @click="emit('goNewer')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>

            <img
                :src="currentImage.url"
                :alt="currentImage.original_name"
                class="max-h-full max-w-full object-contain"
                @contextmenu.prevent="emit('showContextMenu', $event, currentImage.url)"
            />

            <button
                v-if="index < images.length - 1"
                type="button"
                class="absolute right-4 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
                title="Старше"
                @click="emit('goOlder')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18l6-6-6-6" />
                </svg>
            </button>
        </div>
    </div>
</template>
