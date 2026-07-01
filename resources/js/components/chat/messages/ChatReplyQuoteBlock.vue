<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    isMine: {
        type: Boolean,
        default: false,
    },
    interactive: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['click']);
</script>

<template>
    <component
        :is="interactive ? 'button' : 'div'"
        :type="interactive ? 'button' : undefined"
        class="relative block w-full overflow-hidden rounded-md border-l-[3px] text-left transition-colors duration-150"
        :class="[
            compact ? 'mb-0' : 'mb-1.5',
            interactive ? 'cursor-pointer' : '',
            isMine
                ? [
                    'border-l-blue-700 bg-blue-200/55 dark:border-l-blue-400 dark:bg-blue-950/45',
                    interactive ? 'hover:bg-blue-200/85 dark:hover:bg-blue-950/65' : '',
                ]
                : [
                    'border-l-blue-600 bg-gray-100 dark:border-l-blue-400 dark:bg-gray-900/55',
                    interactive ? 'hover:bg-gray-200 dark:hover:bg-gray-800/85' : '',
                ],
        ]"
        @click="interactive && $emit('click')"
    >
        <div class="px-2.5 py-1.5 pl-3">
            <div
                class="truncate text-xs font-semibold leading-5"
                :class="isMine
                    ? 'text-blue-700 dark:text-blue-400'
                    : 'text-blue-600 dark:text-blue-400'"
            >
                {{ title }}
            </div>
            <div
                v-if="subtitle"
                class="line-clamp-2 text-xs leading-[1.125rem]"
                :class="isMine
                    ? 'text-gray-600 dark:text-blue-200/85'
                    : 'text-gray-600 dark:text-gray-400'"
            >
                {{ subtitle }}
            </div>
        </div>
    </component>
</template>
