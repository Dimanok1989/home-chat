<script setup>
import { computed } from 'vue';

const props = defineProps({
    avatarUrl: {
        type: String,
        default: null,
    },
    name: {
        type: String,
        default: '',
    },
    initial: {
        type: String,
        default: null,
    },
    online: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['xs', 'sm', 'md', 'lg'].includes(value),
    },
});

const sizeClasses = {
    xs: 'h-6 w-6 text-xs',
    sm: 'h-[38px] w-[38px] text-sm',
    md: 'h-10 w-10 text-sm',
    lg: 'h-24 w-24 text-2xl',
};

const onlineDotClasses = {
    xs: 'h-2 w-2 border',
    sm: 'h-2.5 w-2.5 border-2',
    md: 'h-2.5 w-2.5 border-2',
};

const displayInitial = computed(() => {
    if (props.initial) {
        return props.initial;
    }

    const trimmed = props.name.trim();

    return trimmed ? trimmed.charAt(0).toUpperCase() : '?';
});
</script>

<template>
    <span class="relative inline-flex shrink-0">
        <span
            class="flex items-center justify-center overflow-hidden rounded-full bg-blue-100 font-semibold text-blue-700 dark:bg-blue-900/50 dark:text-blue-200"
            :class="sizeClasses[size]"
        >
            <img
                v-if="avatarUrl"
                :src="avatarUrl"
                :alt="name"
                class="h-full w-full object-cover"
            />
            <span v-else>{{ displayInitial }}</span>
        </span>
        <span
            v-if="online"
            class="absolute bottom-0 right-0 rounded-full border-white bg-green-500 dark:border-gray-900"
            :class="onlineDotClasses[size]"
            title="В сети"
        ></span>
    </span>
</template>
