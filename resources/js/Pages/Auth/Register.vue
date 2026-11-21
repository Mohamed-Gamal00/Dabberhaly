<template>
    <div class="auth-page" :dir="dir">
        <SeoHead :title="t('register_title')" noindex />
        <!-- Left panel: branding -->
        <div class="auth-left d-none d-md-flex">
            <div class="auth-left-content">
                <v-icon size="56" color="white" class="mb-4">mdi-storefront</v-icon>
                <h1 class="text-white font-weight-bold mb-3" style="font-size:32px">{{ siteName }}</h1>
                <p class="text-white mb-8" style="opacity:0.85; font-size:15px; max-width:280px; line-height:1.8">
                    {{ t('register_tagline') }}
                </p>
                <div class="steps">
                    <div class="step-item" v-for="(s, i) in steps" :key="i">
                        <div class="step-num">{{ i + 1 }}</div>
                        <span class="text-white" style="font-size:14px">{{ s }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right panel: form -->
        <div class="auth-right">
            <div class="auth-form-wrapper">
                <!-- Mobile logo -->
                <div class="d-flex d-md-none align-center mb-6">
                    <v-icon size="32" color="primary" class="me-2">mdi-storefront</v-icon>
                    <span class="font-weight-bold text-h6">{{ siteName }}</span>
                </div>

                <h2 class="font-weight-bold mb-1" style="font-size:26px">{{ t('register_title') }}</h2>
                <p class="text-grey-darken-1 mb-6" style="font-size:14px">{{ t('register_subtitle') }}</p>

                <v-form @submit.prevent="submit" ref="formRef">
                    <v-row dense>

                        <!-- First name -->
                        <v-col cols="12" md="6">
                            <label class="field-label">{{ t('first_name') }} <span class="req">*</span></label>
                            <v-text-field
                                v-model="form.first_name"
                                type="text"
                                inputmode="text"
                                autocomplete="given-name"
                                :placeholder="t('first_name')"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mt-1 mb-1"
                                rounded="lg"
                                bg-color="grey-lighten-5"
                                :rules="rules.first_name"
                                :error-messages="form.errors.first_name"
                                maxlength="50"
                                counter="50"
                            />
                        </v-col>

                        <!-- Family name -->
                        <v-col cols="12" md="6">
                            <label class="field-label">{{ t('last_name') }} <span class="req">*</span></label>
                            <v-text-field
                                v-model="form.family_name"
                                type="text"
                                inputmode="text"
                                autocomplete="family-name"
                                :placeholder="t('last_name')"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mt-1 mb-1"
                                rounded="lg"
                                bg-color="grey-lighten-5"
                                :rules="rules.family_name"
                                :error-messages="form.errors.family_name"
                                maxlength="50"
                                counter="50"
                            />
                        </v-col>

                        <!-- Phone -->
                        <v-col cols="12" md="6">
                            <label class="field-label">{{ t('phone') }} <span class="req">*</span></label>
                            <v-text-field
                                v-model="form.phone_number"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                placeholder="01xxxxxxxxx"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mt-1 mb-1"
                                prepend-inner-icon="mdi-phone-outline"
                                dir="ltr"
                                rounded="lg"
                                bg-color="grey-lighten-5"
                                :rules="rules.phone_number"
                                :error-messages="form.errors.phone_number"
                                maxlength="20"
                            />
                        </v-col>

                        <!-- Email -->
                        <v-col cols="12" md="6">
                            <label class="field-label">{{ t('email') }} <span class="req">*</span></label>
                            <v-text-field
                                v-model="form.email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                placeholder="example@email.com"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mt-1 mb-1"
                                prepend-inner-icon="mdi-email-outline"
                                dir="ltr"
                                rounded="lg"
                                bg-color="grey-lighten-5"
                                :rules="rules.email"
                                :error-messages="form.errors.email"
                                maxlength="255"
                            />
                        </v-col>

                        <!-- Address -->
                        <v-col cols="12">
                            <label class="field-label">{{ t('address') }} <span class="req">*</span></label>
                            <v-text-field
                                v-model="form.address"
                                type="text"
                                inputmode="text"
                                autocomplete="street-address"
                                :placeholder="t('enter_address')"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mt-1 mb-1"
                                prepend-inner-icon="mdi-map-marker-outline"
                                rounded="lg"
                                bg-color="grey-lighten-5"
                                :rules="rules.address"
                                :error-messages="form.errors.address"
                                maxlength="255"
                            />
                        </v-col>

                        <!-- Country -->
                        <v-col cols="12" md="6">
                            <label class="field-label">{{ t('country') }} <span class="req">*</span></label>
                            <v-select
                                v-model="form.country_id"
                                :items="countries"
                                item-title="name"
                                item-value="id"
                                :placeholder="t('select_country')"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mt-1 mb-1"
                                rounded="lg"
                                bg-color="grey-lighten-5"
                                :rules="rules.country_id"
                                :error-messages="form.errors.country_id"
                                @update:model-value="form.city_id = ''"
                            />
                        </v-col>

                        <!-- City -->
                        <v-col cols="12" md="6">
                            <label class="field-label">{{ t('city') }} <span class="req">*</span></label>
                            <v-select
                                v-model="form.city_id"
                                :items="filteredCities"
                                item-title="name"
                                item-value="id"
                                :placeholder="t('select_city')"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mt-1 mb-1"
                                rounded="lg"
                                bg-color="grey-lighten-5"
                                :rules="rules.city_id"
                                :error-messages="form.errors.city_id"
                                :disabled="!form.country_id"
                            />
                        </v-col>

                        <!-- Password -->
                        <v-col cols="12" md="6">
                            <label class="field-label">{{ t('login_password') }} <span class="req">*</span></label>
                            <v-text-field
                                v-model="form.password"
                                :type="showPass ? 'text' : 'password'"
                                inputmode="text"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mt-1 mb-1"
                                prepend-inner-icon="mdi-lock-outline"
                                :append-inner-icon="showPass ? 'mdi-eye-off' : 'mdi-eye'"
                                @click:append-inner="showPass = !showPass"
                                rounded="lg"
                                bg-color="grey-lighten-5"
                                :rules="rules.password"
                                :error-messages="form.errors.password"
                                minlength="8"
                                maxlength="64"
                            />
                        </v-col>

                        <!-- Confirm password -->
                        <v-col cols="12" md="6">
                            <label class="field-label">{{ t('reset_confirm_password') }} <span class="req">*</span></label>
                            <v-text-field
                                v-model="form.password_confirmation"
                                :type="showPass2 ? 'text' : 'password'"
                                inputmode="text"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mt-1 mb-1"
                                prepend-inner-icon="mdi-lock-check-outline"
                                :append-inner-icon="showPass2 ? 'mdi-eye-off' : 'mdi-eye'"
                                @click:append-inner="showPass2 = !showPass2"
                                rounded="lg"
                                bg-color="grey-lighten-5"
                                :rules="rules.password_confirmation"
                                minlength="8"
                                maxlength="64"
                            />
                        </v-col>

                    </v-row>

                    <v-btn
                        type="submit"
                        color="primary"
                        block
                        height="48"
                        rounded="lg"
                        :loading="loading"
                        class="mt-4"
                        style="font-size:15px; font-weight:600; text-transform:none"
                    >
                        {{ t('register_btn') }}
                    </v-btn>
                </v-form>

                <v-divider class="my-5">
                    <span class="text-grey" style="font-size:13px">{{ t('login_or') }}</span>
                </v-divider>
                <div class="text-center" style="font-size:14px">
                    {{ t('register_have_account') }}
                    <a href="/login" class="text-primary font-weight-bold text-decoration-none ms-1">{{ t('login_btn') }}</a>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SeoHead from '../../components/Shared/SeoHead.vue';
import { useLocale } from '../../composables/useLocale';

defineOptions({ layout: null });

const siteName = computed(() => usePage().props.seo?.site_name || t('common_store_name'));
const { t, dir } = useLocale();

const props  = defineProps({ countries: Array, cities: Array });
const formRef = ref(null);

const form = useForm({
    first_name: '', family_name: '', phone_number: '', email: '',
    password: '', password_confirmation: '', address: '', country_id: '', city_id: '',
});

const loading  = ref(false);
const showPass  = ref(false);
const showPass2 = ref(false);

const steps = computed(() => [
    t('register_step1'), t('register_step2'), t('register_step3'),
]);

const filteredCities = computed(() =>
    form.country_id
        ? props.cities.filter(c => c.country_id === form.country_id)
        : []
);

// ─── Client-side validation rules ────────────────────────────────────────────
const namePattern = /^[\u0600-\u06FFa-zA-Z\s]+$/;
const phonePattern = /^[0-9+\s\-]{7,20}$/;
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const rules = {
    first_name: [
        v => !!v                    || t('val_required'),
        v => v.length >= 2          || t('val_min_2'),
        v => v.length <= 50         || t('val_max_50'),
        v => namePattern.test(v)    || t('val_letters_only'),
    ],
    family_name: [
        v => !!v                    || t('val_required'),
        v => v.length >= 2          || t('val_min_2'),
        v => v.length <= 50         || t('val_max_50'),
        v => namePattern.test(v)    || t('val_letters_only'),
    ],
    phone_number: [
        v => !!v                    || t('val_required'),
        v => phonePattern.test(v)   || t('val_phone_invalid'),
    ],
    email: [
        v => !!v                    || t('val_required'),
        v => emailPattern.test(v)   || t('val_email_invalid'),
    ],
    address: [
        v => !!v                    || t('val_required'),
        v => v.length >= 5          || t('val_min_5'),
    ],
    country_id: [
        v => !!v                    || t('val_required'),
    ],
    city_id: [
        v => !!v                    || t('val_required'),
    ],
    password: [
        v => !!v                    || t('val_required'),
        v => v.length >= 8          || t('val_password_min'),
        v => /[A-Z]/.test(v)        || t('val_password_uppercase'),
        v => /[a-z]/.test(v)        || t('val_password_lowercase'),
        v => /[0-9]/.test(v)        || t('val_password_number'),
    ],
    password_confirmation: [
        v => !!v                            || t('val_required'),
        v => v === form.password            || t('val_password_match'),
    ],
};

const submit = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    form.post('/register', { onFinish: () => (loading.value = false) });
};
</script>

<style scoped>
.auth-page { display: flex; min-height: 100vh; }

.auth-left {
    width: 38%;
    background: linear-gradient(135deg, #76543D 0%, #8d6b51 50%, #8d6b51 100%);
    display: flex; align-items: center; justify-content: center;
    padding: 48px; position: relative; overflow: hidden;
}
.auth-left::before {
    content: ''; position: absolute;
    width: 300px; height: 300px; border-radius: 50%;
    background: rgba(255,255,255,0.05); top: -80px; right: -80px;
}
.auth-left::after {
    content: ''; position: absolute;
    width: 200px; height: 200px; border-radius: 50%;
    background: rgba(255,255,255,0.05); bottom: -60px; left: -60px;
}
.auth-left-content { position: relative; z-index: 1; }

.steps .step-item { display: flex; align-items: center; margin-bottom: 16px; gap: 12px; }
.step-num {
    width: 28px; height: 28px; border-radius: 50%;
    background: rgba(255,255,255,0.2); color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}

.auth-right {
    flex: 1; display: flex; align-items: center; justify-content: center;
    background: #ffffff; padding: 32px; overflow-y: auto;
}
.auth-form-wrapper { width: 100%; max-width: 520px; padding: 8px 0; }

.field-label { font-size: 13px; font-weight: 600; color: #374151; }
.req { color: #ef4444; margin-right: 2px; }
</style>
