<template>
    <!-- JSON-LD structured data. Rendered in the body (valid for
         schema.org/Google) via v-html so the JSON is never re-escaped
         or parsed as a Vue template. -->
    <component :is="'script'" type="application/ld+json" v-html="json" />
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: { type: [Object, Array], required: true },
});

const json = computed(() => JSON.stringify(props.data));
</script>
