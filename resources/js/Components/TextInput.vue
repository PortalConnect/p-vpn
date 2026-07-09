<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-lg border-slate-700 bg-slate-900 text-white placeholder-slate-500 shadow-sm focus:border-blue-500 focus:ring-blue-500/40"
        v-model="model"
        ref="input"
    />
</template>
