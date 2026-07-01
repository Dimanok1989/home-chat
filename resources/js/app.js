import './echo';
import { createApp } from 'vue';
import ChatApp from './components/ChatApp.vue';

document.addEventListener('contextmenu', (event) => {
    event.preventDefault();
});

createApp(ChatApp).mount('#app');
