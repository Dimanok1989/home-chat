<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    sending: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'send', 'fileSelect']);

const fileInputRef = ref(null);
const textareaRef = ref(null);

const MAX_TEXTAREA_HEIGHT = 160;

function adjustTextareaHeight() {
    const el = textareaRef.value;
    if (!el) {
        return;
    }

    el.style.height = 'auto';
    const nextHeight = Math.min(el.scrollHeight, MAX_TEXTAREA_HEIGHT);
    el.style.height = `${nextHeight}px`;
    el.style.overflowY = el.scrollHeight > MAX_TEXTAREA_HEIGHT ? 'auto' : 'hidden';
}

function handleAttachClick() {
    fileInputRef.value?.click();
}

function handleFileSelect(event) {
    emit('fileSelect', event);
}

function handleInput(event) {
    emit('update:modelValue', event.target.value);
    adjustTextareaHeight();
}

function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        emit('send');
    }
}

watch(
    () => props.modelValue,
    () => nextTick(adjustTextareaHeight),
);

onMounted(adjustTextareaHeight);

defineExpose({
    fileInputRef,
    textareaRef,
});
</script>

<template>
    <div class="m-0 px-1 md:px-0.5 pt-1 pb-1 md:py-3">
        <p v-if="error" class="mb-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>

        <form @submit.prevent="emit('send')">
            <input
                ref="fileInputRef"
                type="file"
                accept="image/*"
                class="hidden"
                @change="handleFileSelect"
            />

            <div class="flex min-h-[50px] w-full items-end rounded-3xl border border-gray-100 bg-white px-1 dark:border-gray-800 dark:bg-gray-900">
                <button
                    type="button"
                    class="mb-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 cursor-pointer"
                    title="Прикрепить изображение"
                    @click="handleAttachClick"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                    </svg>
                </button>

                <textarea
                    ref="textareaRef"
                    :value="modelValue"
                    rows="1"
                    maxlength="1000"
                    placeholder="Введите сообщение..."
                    class="min-h-[48px] w-full resize-none self-stretch border-0 bg-transparent py-3.5 text-sm leading-5 outline-none placeholder:text-gray-400 dark:placeholder:text-gray-500"
                    @input="handleInput"
                    @keydown="handleKeydown"
                />

                <button
                    type="submit"
                    :disabled="sending || !modelValue.trim()"
                    class="mb-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white transition hover:bg-blue-600 cursor-pointer disabled:cursor-not-allowed disabled:bg-blue-300"
                    title="Отправить"
                >
                    <svg
                        v-if="!sending"
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 translate-x-px -translate-y-px"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                    </svg>
                    <span v-else class="text-sm">...</span>
                </button>
            </div>
        </form>
    </div>
</template>
