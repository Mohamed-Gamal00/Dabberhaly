<template>
    <div class="auth-page">
        <SeoHead :title="t('verify_title')" noindex />
        <div class="auth-left d-none d-md-flex">
            <div class="auth-left-content">
                <v-icon size="56" color="white" class="mb-4">mdi-email-check-outline</v-icon>
                <h1 class="text-white font-weight-bold mb-3" style="font-size:32px">{{ siteName }}</h1>
                <p class="text-white" style="opacity:0.85; font-size:15px; max-width:280px; line-height:1.8">
                    {{ t('verify_tagline') }}
                </p>
            </div>
        </div>

        <div class="auth-right">
            <div class="auth-form-wrapper">
                <div class="d-flex d-md-none align-center mb-6">
                    <v-icon size="32" color="primary" class="me-2">mdi-email-check-outline</v-icon>
                    <span class="font-weight-bold text-h6">{{ siteName }}</span>
                </div>

                <h2 class="font-weight-bold mb-1" style="font-size:26px">{{ t('verify_title') }}</h2>
                <p class="text-grey-darken-1 mb-6" style="font-size:14px">{{ t('verify_subtitle') }}</p>

                <v-alert v-if="success" type="success" variant="tonal" rounded="lg" class="mb-5" density="compact">
                    {{ success }}
                </v-alert>
                <v-alert v-if="error" type="error" variant="tonal" rounded="lg" class="mb-5" density="compact">
                    {{ error }}
                </v-alert>

                <v-form @submit.prevent="submit">
                    <label class="field-label">{{ t('verify_code_label') }}</label>
                    <v-text-field
                        v-model="form.code"
                        :placeholder="t('verify_code_placeholder')"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-5 mt-1"
                        prepend-inner-icon="mdi-shield-key-outline"
                        dir="ltr"
                        rounded="lg"
                        bg-color="grey-lighten-5"
                        maxlength="4"
                        :error-messages="form.errors.code"
                    />

                    <v-btn
                        type="submit"
                        color="primary"
                        block
                        height="48"
                        rounded="lg"
                        :loading="form.processing"
                        style="font-size:15px; font-weight:600; text-transform:none"
                    >
                        {{ t('verify_btn') }}
                    </v-btn>
                </v-form>

                <div class="text-center mt-5" style="font-size:14px; color:#77736B;">
                    {{ t('verify_resend') }}
                    <v-btn
                        variant="text"
                        color="primary"
                        size="small"
                        :loading="resendForm.processing"
                        style="font-weight:600; text-transform:none; font-size:14px;"
                        @click="resend"
                    >
                        {{ t('verify_resend_btn') }}
                    </v-btn>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SeoHead from '../../components/Shared/SeoHead.vue';
import { useLocale } from '../../composables/useLocale';

defineOptions({ layout: null });

const siteName = computed(() => usePage().props.seo?.site_name || t('common_store_name'));
const { t } = useLocale();

const page    = usePage();
const success = computed(() => page.props.flash?.success);
const error   = computed(() => page.props.flash?.error);

const form       = useForm({ code: '' });
const resendForm = useForm({});

const submit = () => form.post('/verify');
const resend = () => resendForm.post('/verify-resend');
</script>

<style scoped>
.auth-page { display:flex; min-height:100vh; direction:rtl; }
.auth-left { width:42%; background:linear-gradient(135deg,#76543D 0%,#8d6b51 50%,#8d6b51 100%); display:flex; align-items:center; justify-content:center; padding:48px; position:relative; overflow:hidden; }
.auth-left::before { content:''; position:absolute; width:300px; height:300px; border-radius:50%; background:rgba(255,255,255,0.05); top:-80px; right:-80px; }
.auth-left-content { position:relative; z-index:1; }
.auth-right { flex:1; display:flex; align-items:center; justify-content:center; background:#ffffff; padding:32px; }
.auth-form-wrapper { width:100%; max-width:400px; }
.field-label { font-size:13px; font-weight:600; color:#374151; }
</style>
