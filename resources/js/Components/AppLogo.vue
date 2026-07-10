<script setup>
defineProps({
    size: { type: [Number, String], default: 32 },
    withWordmark: { type: Boolean, default: false },
    wordmarkClass: { type: String, default: '' },
})
</script>

<template>
    <span class="inline-flex items-center gap-2 align-middle">
        <svg
            :width="size"
            :height="size"
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="P-Net"
            class="block shrink-0"
        >
            <defs>
                <linearGradient id="pvpn-grad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#60A5FA" />
                    <stop offset="0.55" stop-color="#3B82F6" />
                    <stop offset="1" stop-color="#1D4ED8" />
                </linearGradient>
            </defs>

            <!-- rounded square plate with subtle inner shadow effect -->
            <rect width="32" height="32" rx="8" fill="url(#pvpn-grad)" />

            <!-- letter P (geometric, matches Outfit display font) -->
            <path
                d="M10.5 8 V24 M10.5 8 H17 A5 5 0 0 1 17 18 H10.5"
                stroke="white"
                stroke-width="2.6"
                stroke-linecap="round"
                stroke-linejoin="round"
                fill="none"
            />

            <!-- network node dot -->
            <circle cx="22.5" cy="22.5" r="1.6" fill="white" />
            <circle cx="22.5" cy="22.5" r="3" fill="none" stroke="white" stroke-opacity="0.35" stroke-width="0.7" />
        </svg>

        <span
            v-if="withWordmark"
            class="font-display font-bold tracking-tight"
            :class="wordmarkClass"
        >
            P<span class="text-blue-400">·</span>Net
        </span>
    </span>
</template>
