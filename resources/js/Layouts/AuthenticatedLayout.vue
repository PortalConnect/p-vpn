<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLogo from '@/Components/AppLogo.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue'
import { useT } from '@/composables/useT'

const { t } = useT()
const showingNavigationDropdown = ref(false)

const navItems = computed(() => [
    { labelKey: 'nav.dashboard', routeName: 'dashboard' },
    { labelKey: 'nav.keys',      routeName: 'keys.index' },
    { labelKey: 'nav.wallet',    routeName: 'wallet.index' },
    { labelKey: 'nav.pricing',   routeName: 'pricing' },
])
</script>

<template>
    <Head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Work+Sans:wght@400;500;600&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="min-h-screen bg-[#0a0e1a] text-slate-100 font-body antialiased selection:bg-orange-500/30">
        <!-- top nav -->
        <nav class="sticky top-0 z-30 backdrop-blur bg-[#0a0e1a]/80 border-b border-slate-800/60">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center gap-8">
                        <Link :href="route('dashboard')" class="flex items-center gap-2 group">
                            <AppLogo :size="34" with-wordmark wordmark-class="text-base text-white" />
                        </Link>

                        <div class="hidden md:flex items-center gap-1">
                            <Link
                                v-for="item in navItems"
                                :key="item.routeName"
                                :href="route(item.routeName)"
                                :class="[
                                    'px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-200',
                                    route().current(item.routeName)
                                        ? 'bg-slate-800 text-white'
                                        : 'text-slate-300 hover:text-white hover:bg-slate-800/60',
                                ]"
                            >
                                {{ t(item.labelKey) }}
                            </Link>
                        </div>
                    </div>

                    <div class="hidden md:flex items-center gap-3">
                        <LocaleSwitcher />
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 hover:border-slate-500 bg-slate-900/40 px-3 py-2 text-sm text-slate-200 hover:text-white transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                >
                                    <span class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 grid place-items-center text-xs font-bold text-white">
                                        {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                    </span>
                                    <span class="max-w-[160px] truncate">{{ $page.props.auth.user.name }}</span>
                                    <svg class="w-4 h-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('profile.edit')">{{ t('nav.profile') }}</DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">{{ t('nav.logout') }}</DropdownLink>
                            </template>
                        </Dropdown>
                    </div>

                    <!-- mobile burger -->
                    <button
                        @click="showingNavigationDropdown = !showingNavigationDropdown"
                        class="md:hidden inline-flex items-center justify-center rounded-lg p-2 text-slate-300 hover:text-white hover:bg-slate-800 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        :aria-expanded="showingNavigationDropdown"
                        :aria-label="t('nav.menu')"
                    >
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2">
                            <path v-if="!showingNavigationDropdown" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- mobile menu -->
            <div v-if="showingNavigationDropdown" class="md:hidden border-t border-slate-800/60 bg-[#0a0e1a]">
                <div class="px-4 py-3 space-y-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.routeName"
                        :href="route(item.routeName)"
                        :class="[
                            'block px-3 py-2 rounded-lg text-sm font-medium',
                            route().current(item.routeName)
                                ? 'bg-slate-800 text-white'
                                : 'text-slate-300 hover:text-white hover:bg-slate-800/60',
                        ]"
                    >
                        {{ t(item.labelKey) }}
                    </Link>
                </div>
                <div class="border-t border-slate-800/60 px-4 py-3 space-y-3">
                    <LocaleSwitcher />
                    <div>
                        <div class="text-sm font-medium text-white">{{ $page.props.auth.user.name }}</div>
                        <div class="text-xs text-slate-400">{{ $page.props.auth.user.email }}</div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Link :href="route('profile.edit')" class="px-3 py-2 rounded-lg text-sm text-slate-200 hover:bg-slate-800">{{ t('nav.profile') }}</Link>
                        <Link :href="route('logout')" method="post" as="button" class="text-left px-3 py-2 rounded-lg text-sm text-slate-200 hover:bg-slate-800">{{ t('nav.logout') }}</Link>
                    </div>
                </div>
            </div>
        </nav>

        <header v-if="$slots.header" class="border-b border-slate-800/60 bg-[#0a0e1a]/40">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 py-6">
                <slot name="header" />
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 sm:px-6">
            <slot />
        </main>

        <footer class="mt-auto pt-12 pb-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 text-center text-xs text-slate-500">
                <Link href="/about" class="hover:text-slate-300 transition-colors underline decoration-slate-700 underline-offset-2">{{ t('nav.about') }}</Link>
                <span class="mx-2">·</span>
                {{ t('brand.name') }} — {{ t('brand.tagline') }}
            </div>
        </footer>
    </div>
</template>

<style>
.font-display { font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif; }
.font-body    { font-family: 'Work Sans', ui-sans-serif, system-ui, sans-serif; }
</style>
