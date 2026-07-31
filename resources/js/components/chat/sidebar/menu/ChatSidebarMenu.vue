<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import ChatSidebarMenuProfileItem from './ChatSidebarMenuProfileItem.vue';
import ChatSidebarMenuLogoutItem from './ChatSidebarMenuLogoutItem.vue';
import ChatConfirmModal from '../../modals/ChatConfirmModal.vue';

defineProps({
    avatarUrl: {
        type: String,
        default: null,
    },
    avatarName: {
        type: String,
        default: '',
    },
    avatarInitial: {
        type: String,
        default: null,
    },
    isDark: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['openProfile', 'toggleTheme', 'logout']);

const menuOpen = ref(false);
const menuRef = ref(null);
const showLogoutConfirm = ref(false);

function toggleMenu() {
    menuOpen.value = !menuOpen.value;
}

function closeMenu() {
    menuOpen.value = false;
}

function handleOpenProfile() {
    closeMenu();
    emit('openProfile');
}

function handleToggleTheme() {
    emit('toggleTheme');
    closeMenu();
}

function handleLogoutClick() {
    closeMenu();
    showLogoutConfirm.value = true;
}

function handleLogoutConfirm() {
    showLogoutConfirm.value = false;
    emit('logout');
}

function handleLogoutCancel() {
    showLogoutConfirm.value = false;
}

function handleDocumentClick(event) {
    if (!menuRef.value?.contains(event.target)) {
        closeMenu();
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <div ref="menuRef" class="relative shrink-0">
        <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-900"
            aria-label="Меню"
            aria-haspopup="true"
            :aria-expanded="menuOpen"
            @click.stop="toggleMenu"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <line x1="4" x2="20" y1="12" y2="12" />
                <line x1="4" x2="20" y1="6" y2="6" />
                <line x1="4" x2="20" y1="18" y2="18" />
            </svg>
        </button>

        <div
            v-if="menuOpen"
            class="absolute left-0 z-20 mt-1 w-48 py-1 rounded-2xl border border-gray-100 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900"
        >
            <ChatSidebarMenuProfileItem
                :avatar-url="avatarUrl"
                :name="avatarName"
                :initial="avatarInitial"
                @click="handleOpenProfile"
            />
            <hr class="my-1 border-t border-gray-100 dark:border-gray-800"/>
            <ChatSidebarMenuLogoutItem @click="handleLogoutClick" />
        </div>

        <Teleport to="body">
            <ChatConfirmModal
                :show="showLogoutConfirm"
                title="Выход"
                message="Вы точно хотите выполнить выход?"
                confirm-label="Выйти"
                cancel-label="Отмена"
                @close="handleLogoutCancel"
                @confirm="handleLogoutConfirm"
            />
        </Teleport>
    </div>
</template>
