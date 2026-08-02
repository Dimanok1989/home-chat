<script setup>
import { computed } from 'vue';
import { linkifyText } from '../../../utils/linkify';

const props = defineProps({
    text: {
        type: String,
        default: '',
    },
});

const segments = computed(() => linkifyText(props.text));
</script>

<template>
    <template
        v-for="(segment, index) in segments"
        :key="index"
    >
        <a
            v-if="segment.type === 'link'"
            :href="segment.href"
            target="_blank"
            rel="noopener noreferrer"
            class="underline underline-offset-2 hover:opacity-80"
            @click.stop
        >
            {{ segment.value }}
        </a>
        <template v-else>{{ segment.value }}</template>
    </template>
</template>
