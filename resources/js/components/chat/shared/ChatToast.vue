<script setup>
defineProps({
    message: {
        type: String,
        default: '',
    },
    visible: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'error', // 'error' | 'info' | 'success'
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div
                v-if="visible && message"
                class="pointer-events-none fixed inset-x-0 top-0 z-[100] flex justify-center px-4 pt-[max(0.75rem,env(safe-area-inset-top))]"
            >
                <div
                    class="pointer-events-auto flex max-w-lg items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg"
                    :class="{
                        'border-red-200 bg-red-50 text-red-800 dark:border-red-900/60 dark:bg-red-950/90 dark:text-red-100': type === 'error',
                        'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900/60 dark:bg-blue-950/90 dark:text-blue-100': type === 'info',
                        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/90 dark:text-emerald-100': type === 'success',
                    }"
                    role="alert"
                >
                    <!-- Error icon -->
                    <svg
                        v-if="type === 'error'"
                        xmlns="http://www.w3.org/2000/svg"
                        class="mt-0.5 h-5 w-5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 8v4" />
                        <path d="M12 16h.01" />
                    </svg>
                    <!-- Info icon -->
                    <svg
                        v-else-if="type === 'info'"
                        xmlns="http://www.w3.org/2000/svg"
                        class="mt-0.5 h-5 w-5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 16v-4" />
                        <path d="M12 8h.01" />
                    </svg>
                    <!-- Success icon -->
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="mt-0.5 h-5 w-5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <path d="m9 11 3 3L22 4" />
                    </svg>
                    <p class="min-w-0 flex-1 leading-snug">{{ message }}</p>
                    <button
                        type="button"
                        class="shrink-0 rounded p-1 transition"
                        :class="{
                            'text-red-600 hover:bg-red-100 dark:text-red-200 dark:hover:bg-red-900/60': type === 'error',
                            'text-blue-600 hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/60': type === 'info',
                            'text-emerald-600 hover:bg-emerald-100 dark:text-emerald-200 dark:hover:bg-emerald-900/60': type === 'success',
                        }"
                        aria-label="Закрыть"
                        @click="emit('close')"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
