<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AppLogo from '@/Components/AppLogo.vue'
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue'
import { useT } from '@/composables/useT'

defineProps({
    canLogin: { type: Boolean, default: true },
    canRegister: { type: Boolean, default: true },
    laravelVersion: { type: String, default: '' },
    phpVersion: { type: String, default: '' },
})

const page = usePage()
const { t } = useT()
const isAuthenticated = computed(() => Boolean(page.props.auth?.user))

const features = computed(() => [
    { title: t('landing.feat_1_title'), body: t('landing.feat_1_body') },
    { title: t('landing.feat_2_title'), body: t('landing.feat_2_body') },
    { title: t('landing.feat_3_title'), body: t('landing.feat_3_body') },
])

const proofStats = computed(() => [
    { value: '5',     label: t('landing.stats.locations') },
    { value: '99.9%', label: t('landing.stats.uptime') },
    { value: 'AWG',   label: t('landing.stats.obfuscation') },
    { value: '200₽',  label: t('landing.stats.per_month') },
])
</script>

<template>
    <Head :title="t('landing.title')">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Work+Sans:wght@400;500;600&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="min-h-screen bg-[#0a0e1a] text-slate-100 antialiased font-body selection:bg-orange-500/30">
        <!-- HEADER -->
        <header class="relative z-10 border-b border-slate-800/60">
            <div class="mx-auto max-w-6xl px-6 py-5 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2 group" aria-label="P-VPN">
                    <AppLogo :size="36" with-wordmark wordmark-class="text-lg text-white" />
                </Link>
                <nav class="flex items-center gap-2 sm:gap-3">
                    <LocaleSwitcher />
                    <template v-if="isAuthenticated">
                        <Link
                            href="/dashboard"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg bg-white text-slate-900 hover:bg-slate-100 transition-colors duration-200 shadow"
                        >
                            {{ t('nav.cabinet') }}
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/>
                            </svg>
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            v-if="canLogin"
                            href="/login"
                            class="px-4 py-2 text-sm rounded-lg text-slate-200 hover:text-white hover:bg-slate-800 transition-colors duration-200"
                        >
                            {{ t('nav.login') }}
                        </Link>
                        <Link
                            v-if="canRegister"
                            href="/register"
                            class="px-4 py-2 text-sm font-semibold rounded-lg bg-white text-slate-900 hover:bg-slate-100 transition-colors duration-200 shadow"
                        >
                            {{ t('nav.register') }}
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- HERO -->
        <section class="relative overflow-hidden">
            <!-- decorative grid + blob -->
            <div class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute inset-0 opacity-[0.06]"
                     style="background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px); background-size: 56px 56px; mask-image: radial-gradient(ellipse 800px 500px at 50% 0%, black 30%, transparent 70%);"></div>
                <div class="absolute -top-40 right-1/4 w-[600px] h-[600px] rounded-full bg-blue-600/20 blur-[120px]"></div>
                <div class="absolute top-40 -left-40 w-[500px] h-[500px] rounded-full bg-orange-500/10 blur-[120px]"></div>
            </div>

            <div class="mx-auto max-w-6xl px-6 pt-16 pb-24 sm:pt-24 sm:pb-32">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    <div class="lg:col-span-7">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/70 border border-slate-700/60 text-xs font-medium text-slate-300 mb-6">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
                            </span>
                            {{ t('landing.badge_works_in_ru') }}
                        </div>

                        <h1 class="font-display font-extrabold text-4xl sm:text-5xl lg:text-6xl leading-[1.05] tracking-tight">
                            {{ t('landing.hero_h1_a') }}
                            <span class="block bg-gradient-to-r from-blue-400 via-sky-300 to-blue-500 bg-clip-text text-transparent">
                                {{ t('landing.hero_h1_b') }}
                            </span>
                        </h1>

                        <p class="mt-6 text-lg sm:text-xl text-slate-300 max-w-xl leading-relaxed">
                            {{ t('landing.hero_sub') }}
                        </p>

                        <div class="mt-9 flex flex-col sm:flex-row gap-3">
                            <Link
                                :href="isAuthenticated ? '/dashboard' : '/register'"
                                class="group inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-orange-500 hover:bg-orange-400 active:bg-orange-600 text-white font-semibold text-base shadow-lg shadow-orange-500/30 transition-all duration-200 hover:shadow-orange-500/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e1a]"
                            >
                                {{ isAuthenticated ? t('landing.cta_to_cabinet') : t('landing.cta_start') }}
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-5 h-5 transition-transform group-hover:translate-x-0.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/>
                                </svg>
                            </Link>
                            <Link
                                v-if="!isAuthenticated"
                                href="/login"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl border border-slate-700 hover:border-slate-500 text-slate-200 hover:text-white font-medium transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                            >
                                {{ t('landing.cta_have_account') }}
                            </Link>
                            <Link
                                v-else
                                href="/keys"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl border border-slate-700 hover:border-slate-500 text-slate-200 hover:text-white font-medium transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                            >
                                {{ t('landing.cta_my_keys') }}
                            </Link>
                        </div>

                        <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-slate-400">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/></svg>
                                {{ t('landing.no_logs') }}
                            </span>
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/></svg>
                                {{ t('landing.own_servers') }}
                            </span>
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/></svg>
                                {{ t('landing.platforms') }}
                            </span>
                        </div>
                    </div>

                    <!-- visual -->
                    <div class="lg:col-span-5 relative" aria-hidden="true">
                        <div class="relative rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 p-6 shadow-2xl shadow-blue-950/50">
                            <div class="flex items-center justify-between mb-5">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500/70"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500/70"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/70"></span>
                                </div>
                                <span class="text-xs text-slate-500 font-mono">/keys</span>
                            </div>

                            <div class="space-y-3">
                                <div class="rounded-lg border border-slate-700/60 bg-slate-900/60 p-4 flex items-center justify-between">
                                    <div>
                                        <div class="text-xs text-slate-500 mb-1">{{ t('landing.mockup_current') }}</div>
                                        <div class="flex items-center gap-2 font-semibold">
                                            <span class="text-2xl leading-none">🇳🇱</span>
                                            NL
                                        </div>
                                    </div>
                                    <span class="text-emerald-400 text-xs font-medium px-2 py-1 rounded-full bg-emerald-500/10">{{ t('landing.mockup_connected') }}</span>
                                </div>

                                <div class="rounded-lg border border-slate-700/60 bg-slate-900/40 p-3 flex items-center justify-between hover:bg-slate-800/60 transition-colors">
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="text-lg">🇩🇪</span> DE
                                    </div>
                                    <span class="text-xs text-slate-500">12 ms</span>
                                </div>
                                <div class="rounded-lg border border-slate-700/60 bg-slate-900/40 p-3 flex items-center justify-between hover:bg-slate-800/60 transition-colors">
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="text-lg">🇺🇸</span> US
                                    </div>
                                    <span class="text-xs text-slate-500">87 ms</span>
                                </div>
                                <div class="rounded-lg border border-slate-700/60 bg-slate-900/40 p-3 flex items-center justify-between hover:bg-slate-800/60 transition-colors">
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="text-lg">🇫🇮</span> FI
                                    </div>
                                    <span class="text-xs text-slate-500">28 ms</span>
                                </div>
                            </div>

                            <div class="mt-5 pt-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                                <span>{{ t('landing.mockup_balance') }}: <span class="text-white font-semibold">600 ₽</span></span>
                                <span>{{ t('landing.mockup_until') }} 25.05.2026</span>
                            </div>
                        </div>

                        <!-- glow under card -->
                        <div class="absolute -inset-2 -z-10 bg-gradient-to-r from-blue-600/30 via-blue-500/10 to-orange-500/20 blur-3xl opacity-50"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- PROOF / STATS -->
        <section class="border-y border-slate-800/60 bg-slate-950/40">
            <div class="mx-auto max-w-6xl px-6 py-10 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div v-for="s in proofStats" :key="s.label">
                    <div class="font-display text-3xl sm:text-4xl font-bold text-white">{{ s.value }}</div>
                    <div class="mt-1 text-sm text-slate-400">{{ s.label }}</div>
                </div>
            </div>
        </section>

        <!-- FEATURES -->
        <section class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
            <div class="max-w-2xl mb-12">
                <p class="text-sm font-semibold uppercase tracking-wider text-blue-400 mb-3">{{ t('landing.features_eyebrow') }}</p>
                <h2 class="font-display text-3xl sm:text-4xl font-bold tracking-tight">
                    {{ t('landing.features_h2') }}
                </h2>
                <p class="mt-4 text-slate-400 text-lg leading-relaxed">
                    {{ t('landing.features_sub') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <article
                    v-for="(f, i) in features"
                    :key="f.title"
                    class="group relative rounded-2xl border border-slate-800 bg-gradient-to-b from-slate-900/80 to-slate-950 p-7 hover:border-slate-700 transition-colors duration-200"
                >
                    <div class="grid place-items-center w-11 h-11 rounded-xl bg-blue-500/10 border border-blue-500/20 mb-5 group-hover:bg-blue-500/15 transition-colors">
                        <!-- icon: Shield (i=0), Globe (i=1), Wallet (i=2) -->
                        <svg v-if="i === 0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 6v6c0 5 3.5 9.4 8 10 4.5-.6 8-5 8-10V6l-8-3zm-3 9 2 2 5-5"/>
                        </svg>
                        <svg v-else-if="i === 1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5 text-blue-400">
                            <circle cx="12" cy="12" r="9" stroke-linecap="round"/>
                            <path stroke-linecap="round" d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>
                        </svg>
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h12l4 3v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm14 5h2"/>
                        </svg>
                    </div>
                    <h3 class="font-display font-semibold text-xl text-white">{{ f.title }}</h3>
                    <p class="mt-3 text-slate-400 leading-relaxed">{{ f.body }}</p>
                </article>
            </div>
        </section>

        <!-- PRICING -->
        <section class="mx-auto max-w-6xl px-6 pb-20 sm:pb-28">
            <div class="rounded-3xl border border-slate-800 bg-gradient-to-br from-slate-900 via-slate-900 to-blue-950/40 p-8 sm:p-12 text-center relative overflow-hidden">
                <div class="pointer-events-none absolute -top-32 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-blue-500/10 blur-3xl rounded-full"></div>

                <p class="text-sm font-semibold uppercase tracking-wider text-orange-400 mb-3">{{ t('landing.pricing_eyebrow') }}</p>
                <h2 class="font-display text-4xl sm:text-5xl font-bold tracking-tight">
                    200 <span class="text-slate-500 font-medium">{{ t('landing.pricing_per_month_label') }}</span>
                </h2>
                <p class="mt-4 text-slate-400 max-w-md mx-auto">
                    {{ t('landing.pricing_sub') }}
                </p>

                <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-xl mx-auto text-left">
                    <div v-for="(item, j) in [t('landing.pricing_feature_1'), t('landing.pricing_feature_2'), t('landing.pricing_feature_3')]" :key="j" class="flex items-center gap-2 text-sm text-slate-300">
                        <svg class="w-4 h-4 text-emerald-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ item }}
                    </div>
                </div>

                <Link
                    :href="isAuthenticated ? '/pricing' : '/register'"
                    class="mt-9 inline-flex items-center gap-2 px-7 py-3.5 rounded-xl bg-orange-500 hover:bg-orange-400 active:bg-orange-600 text-white font-semibold shadow-lg shadow-orange-500/30 transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e1a]"
                >
                    {{ isAuthenticated ? t('landing.cta_buy_subscription') : t('landing.cta_create_account') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-7 7 7-7 7"/>
                    </svg>
                </Link>

                <p v-if="!isAuthenticated" class="mt-4 text-xs text-slate-500">{{ t('landing.reg_note') }}</p>
                <p v-else class="mt-4 text-xs text-slate-500">{{ t('landing.reg_note_auth') }}</p>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="border-t border-slate-800/60">
            <div class="mx-auto max-w-6xl px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-slate-500">
                <div class="flex items-center gap-2">
                    <AppLogo :size="28" />
                    <span class="text-slate-300 font-medium">{{ t('brand.name') }}</span>
                    <span class="hidden sm:inline">— {{ t('brand.tagline') }}</span>
                </div>
                <div class="flex items-center gap-5">
                    <Link href="/about/terms" class="hover:text-slate-300 transition-colors">{{ t('landing.footer_offer') }}</Link>
                    <Link href="/about/privacy" class="hover:text-slate-300 transition-colors">{{ t('landing.footer_policy') }}</Link>
                    <Link href="/about/contacts" class="hover:text-slate-300 transition-colors">{{ t('landing.footer_support') }}</Link>
                    <Link href="/about" class="hover:text-slate-300 transition-colors">{{ t('nav.about') }}</Link>
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
.font-display { font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif; }
.font-body    { font-family: 'Work Sans', ui-sans-serif, system-ui, sans-serif; }

@media (prefers-reduced-motion: reduce) {
    .animate-ping { animation: none !important; }
    * { transition-duration: 0.01ms !important; }
}
</style>
