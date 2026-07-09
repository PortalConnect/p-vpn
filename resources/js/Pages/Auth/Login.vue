<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useT } from '@/composables/useT';

const { t } = useT();

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const linkSent = computed(() => props.status === 'magic-link-sent');

const submit = () => {
    form.post(route('login.magic.send'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.login_title')" />

        <div v-if="linkSent" class="text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/15">
                <svg class="h-6 w-6 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            </div>
            <h2 class="font-display text-xl font-bold text-white">{{ t('auth.sent_title') }}</h2>
            <p class="mt-2 text-sm text-slate-400">
                {{ t('auth.sent_sub', { email: form.email || '' }) }}
            </p>
            <button
                type="button"
                class="mt-6 text-sm font-medium text-blue-400 hover:text-blue-300"
                @click="submit"
                :disabled="form.processing"
            >
                {{ t('auth.sent_again') }}
            </button>
        </div>

        <form v-else @submit.prevent="submit">
            <h2 class="font-display text-xl font-bold text-white">{{ t('auth.login_title') }}</h2>
            <p class="mt-1 mb-6 text-sm text-slate-400">{{ t('auth.login_sub') }}</p>

            <div>
                <InputLabel for="email" :value="t('auth.email_label')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="email"
                    :placeholder="t('auth.email_placeholder')"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-6">
                <PrimaryButton
                    class="w-full justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? t('auth.sending') : t('auth.send_link') }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
