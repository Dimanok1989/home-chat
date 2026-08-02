<script setup>
defineProps({
    preview: {
        type: Object,
        required: true,
    },
    isMine: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <a
        :href="preview.url"
        target="_blank"
        rel="noopener noreferrer"
        class="mt-2 block overflow-hidden rounded-md border-l-[3px] no-underline transition-colors duration-150"
        :class="isMine
            ? 'border-l-blue-700 bg-blue-200/55 hover:bg-blue-200/85 dark:border-l-blue-400 dark:bg-blue-950/45 dark:hover:bg-blue-950/65'
            : 'border-l-blue-600 bg-gray-100 hover:bg-gray-200 dark:border-l-blue-400 dark:bg-gray-900/55 dark:hover:bg-gray-800/85'"
    >
        <img
            v-if="preview.image_url"
            :src="preview.image_url"
            alt=""
            class="max-h-40 w-full object-cover"
            loading="lazy"
            @error="$event.target.remove()"
        />
        <div class="px-2.5 py-1.5 pl-3">
            <div
                v-if="preview.title"
                class="line-clamp-2 text-xs font-semibold leading-5"
                :class="isMine
                    ? 'text-blue-700 dark:text-blue-400'
                    : 'text-blue-600 dark:text-blue-400'"
            >
                {{ preview.title }}
            </div>
            <div
                v-if="preview.description"
                class="line-clamp-3 text-xs leading-[1.125rem]"
                :class="isMine
                    ? 'text-gray-600 dark:text-blue-200/85'
                    : 'text-gray-600 dark:text-gray-400'"
            >
                {{ preview.description }}
            </div>
            <div
                v-if="!preview.title && !preview.description"
                class="truncate text-xs"
                :class="isMine
                    ? 'text-gray-600 dark:text-blue-200/85'
                    : 'text-gray-600 dark:text-gray-400'"
            >
                {{ preview.url }}
            </div>
        </div>
    </a>
</template>
