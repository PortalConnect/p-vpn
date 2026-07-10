<script setup>
import AppLogo from '@/Components/AppLogo.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Link } from '@inertiajs/vue3';
import { useT } from '@/composables/useT';

const { t } = useT();
</script>

<template>
    <div class="relative flex min-h-screen flex-col bg-[#0a0e1a] font-body text-slate-100 antialiased selection:bg-orange-500/30">
        <!-- фоновое свечение в стиле лендинга -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-40 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-blue-600/15 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 h-72 w-96 rounded-full bg-orange-500/5 blur-3xl"></div>
        </div>

        <header class="relative z-10 border-b border-slate-800/60">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                <Link href="/" class="group flex items-center gap-2" aria-label="P-VPN">
                    <AppLogo :size="36" with-wordmark wordmark-class="text-lg text-white" />
                </Link>
                <LocaleSwitcher />
            </div>
        </header>

        <main class="relative z-10 flex flex-1 flex-col items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900/80 to-slate-950 p-6 shadow-2xl shadow-blue-950/40 sm:p-8">
                <slot />
            </div>

            <p class="mt-6 text-center text-xs text-slate-500">
                {{ t('brand.tagline') }}
                <span class="mx-1">·</span>
                <Link href="/about" class="hover:text-slate-300 transition-colors">{{ t('nav.about') }}</Link>
            </p>
        </main>
    </div>
</template>
