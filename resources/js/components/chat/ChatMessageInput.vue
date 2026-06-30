<script setup>
import { ref } from 'vue';

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

function handleAttachClick() {
    fileInputRef.value?.click();
}

function handleFileSelect(event) {
    emit('fileSelect', event);
}

function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        emit('send');
    }
}

defineExpose({
    fileInputRef,
});
</script>

<template>
    <div class="border-t border-gray-200 bg-white px-6 py-4">
        <p v-if="error" class="mb-2 text-sm text-red-600">{{ error }}</p>

        <form class="flex gap-3" @submit.prevent="emit('send')">
            <input
                ref="fileInputRef"
                type="file"
                accept="image/*"
                class="hidden"
                @change="handleFileSelect"
            />

            <button
                type="button"
                class="flex shrink-0 items-center justify-center rounded-xl border border-gray-300 px-3 py-3 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                title="Прикрепить изображение"
                @click="handleAttachClick"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                </svg>
            </button>

            <input
                :value="modelValue"
                type="text"
                maxlength="1000"
                placeholder="Введите сообщение..."
                class="flex-1 rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                @input="emit('update:modelValue', $event.target.value)"
                @keydown="handleKeydown"
            />

            <button
                type="submit"
                :disabled="sending || !modelValue.trim()"
                class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
            >
                {{ sending ? '...' : 'Отправить' }}
            </button>
        </form>
    </div>
</template>
