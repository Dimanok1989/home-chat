<script setup>
import { computed, ref } from 'vue';
import { formatDateTime, formatTime, isSystemMessage, isCallMessage, parseCallMessage, parseRegistrationMessage } from '../../../utils/chatFormat';
import ChatMessageReplyQuote from './ChatMessageReplyQuote.vue';
import ChatUserAvatar from '../shared/ChatUserAvatar.vue';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    highlighted: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['openViewer', 'showContextMenu', 'scrollToMessage', 'startDirectFromAvatar']);

const avatarMenuOpen = ref(false);
const avatarMenuX = ref(0);
const avatarMenuY = ref(0);

const regUserMenuOpen = ref(false);
const regUserMenuX = ref(0);
const regUserMenuY = ref(0);

const callInfo = computed(() => parseCallMessage(props.message));

const regUser = computed(() => {
    if (!isSystemMessage(props.message)) return null;
    return parseRegistrationMessage(props.message.body);
});

function messageCanDelete(msg) {
    return msg.is_mine && !isSystemMessage(msg) && !isCallMessage(msg);
}

function messageCanShowContextMenu(msg) {
    return !isSystemMessage(msg) && !isCallMessage(msg);
}

function handleImageClick(attachment) {
    emit('openViewer', attachment);
}

function handleImageContextMenu(event, attachment) {
    event.stopPropagation();
    emit('showContextMenu', event, {
        message: props.message,
        imageUrl: attachment.url,
    });
}

function handleMessageContextMenu(event) {
    if (!messageCanShowContextMenu(props.message)) {
        return;
    }

    emit('showContextMenu', event, {
        message: props.message,
        imageUrl: null,
    });
}

function handleAvatarClick(event) {
    event.stopPropagation();
    avatarMenuX.value = event.clientX;
    avatarMenuY.value = event.clientY;
    avatarMenuOpen.value = true;
}

function closeAvatarMenu() {
    avatarMenuOpen.value = false;
}

function handleStartDirect() {
    avatarMenuOpen.value = false;
    emit('startDirectFromAvatar', {
        id: props.message.user_id,
        display_name: props.message.user_name,
        avatar_url: props.message.user_avatar_url,
        initial: props.message.user_initial,
    });
}

function handleRegUserNameClick(event) {
    if (!regUser.value) return;
    event.stopPropagation();
    regUserMenuX.value = event.clientX;
    regUserMenuY.value = event.clientY;
    regUserMenuOpen.value = true;
}

function closeRegUserMenu() {
    regUserMenuOpen.value = false;
}

function handleStartDirectToRegUser() {
    if (!regUser.value) return;
    regUserMenuOpen.value = false;
    emit('startDirectFromAvatar', {
        id: regUser.value.id,
        display_name: regUser.value.name,
        avatar_url: null,
        initial: regUser.value.name ? regUser.value.name.charAt(0).toUpperCase() : '?',
    });
}
</script>

<template>
    <div
        class="flex"
        :class="isSystemMessage(message)
            ? 'justify-center'
            : (message.is_mine ? 'justify-end' : 'justify-start')"
        :data-message-id="message.id"
    >
        <div
            v-if="isSystemMessage(message)"
            :title="formatDateTime(message.created_at)"
            class="max-w-[85%] cursor-default rounded-3xl bg-gray-600/10 px-5 py-3 text-center text-sm text-gray-700 dark:bg-gray-700/30 dark:text-gray-300"
        >
            <div
                v-if="message.attachments?.length"
                class="flex flex-col items-center gap-2"
                :class="{ 'mb-2': message.body }"
            >
                <img
                    v-for="attachment in message.attachments"
                    :key="attachment.id"
                    :src="attachment.url"
                    :alt="attachment.original_name"
                    class="max-h-64 cursor-pointer rounded-2xl object-contain"
                    @click="handleImageClick(attachment)"
                    @contextmenu.prevent="handleImageContextMenu($event, attachment)"
                />
            </div>

            <p
                v-if="message.body && !regUser"
                class="whitespace-pre-wrap break-words"
            >
                {{ message.body }}
            </p>

            <p
                v-if="message.body && regUser"
                class="whitespace-pre-wrap break-words"
            >
                Новый пользователь
                <button
                    type="button"
                    class="cursor-pointer font-semibold text-blue-600 underline underline-offset-2 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    @click="handleRegUserNameClick"
                >
                    {{ regUser.name }}
                </button>
                зарегистрировался.
            </p>

            <Teleport to="body">
                <div
                    v-if="regUserMenuOpen"
                    class="fixed inset-0 z-50"
                    @click="closeRegUserMenu"
                ></div>

                <div
                    v-if="regUserMenuOpen"
                    class="fixed z-50 min-w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                    :style="{ left: regUserMenuX + 'px', top: regUserMenuY + 'px' }"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="handleStartDirectToRegUser"
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
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                        </svg>
                        Написать личное сообщение
                    </button>
                </div>
            </Teleport>
        </div>

        <!-- Call message -->
        <div
            v-else-if="callInfo"
            class="flex max-w-[75%] items-end gap-2"
            :class="message.is_mine ? 'flex-row-reverse' : ''"
        >
            <div
                class="message-bubble flex items-center gap-3 rounded-2xl px-4 py-3"
                :class="message.is_mine
                    ? 'bg-blue-200 text-gray-900 dark:bg-blue-900 dark:text-gray-100'
                    : 'border border-gray-100 bg-white text-gray-900 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-100'"
            >
                <!-- Call icon -->
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                    :class="message.is_mine
                        ? 'bg-blue-300 dark:bg-blue-700'
                        : 'bg-gray-200 dark:bg-gray-600'"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        :class="message.is_mine
                            ? 'text-blue-700 dark:text-blue-200'
                            : 'text-gray-600 dark:text-gray-300'"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path :d="callInfo.icon" />
                    </svg>
                </div>

                <!-- Call info -->
                <div class="min-w-0">
                    <p class="text-sm font-medium leading-tight">
                        {{ callInfo.label }}
                    </p>
                    <p
                        v-if="callInfo.duration"
                        class="mt-0.5 text-xs leading-tight"
                        :class="message.is_mine
                            ? 'text-blue-600 dark:text-blue-300'
                            : 'text-gray-500 dark:text-gray-400'"
                    >
                        {{ callInfo.duration }}
                    </p>
                </div>

                <!-- Timestamp -->
                <p
                    class="ml-auto shrink-0 self-start pt-0.5 text-xs"
                    :class="message.is_mine
                        ? 'text-blue-500 dark:text-blue-300'
                        : 'text-gray-400 dark:text-gray-500'"
                >
                    {{ formatTime(message.created_at) }}
                </p>
            </div>
        </div>

        <div
            v-else-if="!message.is_mine"
            class="flex max-w-[75%] items-end gap-2"
        >
            <div class="relative shrink-0 self-end pb-1">
                <button
                    type="button"
                    class="cursor-pointer rounded-full transition-opacity hover:opacity-80 focus:outline-none"
                    @click="handleAvatarClick"
                >
                    <ChatUserAvatar
                        :avatar-url="message.user_avatar_url"
                        :name="message.user_name"
                        :initial="message.user_initial"
                        size="sm"
                    />
                </button>

                <Teleport to="body">
                    <div
                        v-if="avatarMenuOpen"
                        class="fixed inset-0 z-50"
                        @click="closeAvatarMenu"
                    ></div>

                    <div
                        v-if="avatarMenuOpen"
                        class="fixed z-50 min-w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                        :style="{ left: avatarMenuX + 'px', top: avatarMenuY + 'px' }"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="handleStartDirect"
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
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                            </svg>
                            Написать личное сообщение
                        </button>
                    </div>
                </Teleport>
            </div>

            <div
                class="message-bubble rounded-2xl px-4 py-2"
                :class="[
                    'border border-gray-100 bg-white text-gray-900 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-100',
                    messageCanShowContextMenu(message) ? 'cursor-context-menu' : '',
                    highlighted ? 'message-bubble-highlight-other' : '',
                ]"
                @contextmenu.prevent="handleMessageContextMenu"
            >
                <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ message.user_name }}
                </p>

                <ChatMessageReplyQuote
                    v-if="message.reply_to"
                    :reply-to="message.reply_to"
                    :is-mine="message.is_mine"
                    @scroll-to-message="emit('scrollToMessage', $event)"
                />

                <div
                    v-if="message.attachments?.length"
                    class="mb-2 space-y-2"
                >
                    <img
                        v-for="attachment in message.attachments"
                        :key="attachment.id"
                        :src="attachment.url"
                        :alt="attachment.original_name"
                        class="max-h-64 cursor-pointer rounded-lg object-cover"
                        @click="handleImageClick(attachment)"
                        @contextmenu.prevent="handleImageContextMenu($event, attachment)"
                    />
                </div>

                <p
                    v-if="message.body"
                    class="whitespace-pre-wrap break-words text-sm"
                >
                    {{ message.body }}
                </p>

                <p
                    class="mt-1 text-right text-xs text-gray-400 dark:text-gray-500"
                >
                    {{ formatTime(message.created_at) }}
                </p>
            </div>
        </div>

        <div
            v-else
            class="message-bubble max-w-[75%] rounded-2xl px-4 py-2"
            :class="[
                'bg-blue-200 text-gray-900 dark:bg-blue-900 dark:text-gray-100',
                messageCanShowContextMenu(message) ? 'cursor-context-menu' : '',
                highlighted ? 'message-bubble-highlight-mine' : '',
            ]"
            @contextmenu.prevent="handleMessageContextMenu"
        >
            <ChatMessageReplyQuote
                v-if="message.reply_to"
                :reply-to="message.reply_to"
                :is-mine="message.is_mine"
                @scroll-to-message="emit('scrollToMessage', $event)"
            />

            <div
                v-if="message.attachments?.length"
                class="mb-2 space-y-2"
            >
                <img
                    v-for="attachment in message.attachments"
                    :key="attachment.id"
                    :src="attachment.url"
                    :alt="attachment.original_name"
                    class="max-h-64 cursor-pointer rounded-lg object-cover"
                    @click="handleImageClick(attachment)"
                    @contextmenu.prevent="handleImageContextMenu($event, attachment)"
                />
            </div>

            <p
                v-if="message.body"
                class="whitespace-pre-wrap break-words text-sm"
            >
                {{ message.body }}
            </p>

            <p
                class="mt-1 text-right text-xs text-gray-500 dark:text-gray-400"
            >
                {{ formatTime(message.created_at) }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.message-bubble-highlight-mine {
    animation: message-highlight-mine 2s ease-out;
}

.message-bubble-highlight-other {
    animation: message-highlight-other 2s ease-out;
}

@keyframes message-highlight-mine {
    0%, 20% {
        background-color: rgb(147 197 253);
    }

    100% {
        background-color: rgb(191 219 254);
    }
}

@keyframes message-highlight-other {
    0%, 20% {
        background-color: rgb(219 234 254);
    }

    100% {
        background-color: rgb(255 255 255);
    }
}

:global(.dark) .message-bubble-highlight-mine {
    animation: message-highlight-mine-dark 2s ease-out;
}

:global(.dark) .message-bubble-highlight-other {
    animation: message-highlight-other-dark 2s ease-out;
}

@keyframes message-highlight-mine-dark {
    0%, 20% {
        background-color: rgb(37 99 235);
    }

    100% {
        background-color: rgb(30 58 138);
    }
}

@keyframes message-highlight-other-dark {
    0%, 20% {
        background-color: rgb(30 64 175);
    }

    100% {
        background-color: rgb(31 41 55);
    }
}
</style>
