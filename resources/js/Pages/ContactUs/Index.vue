<template>
    <div class="cu-page">
        <SeoHead :title="t('contact_title')" :description="t('contact_subtitle')" />
        <div class="cu-hero">
            <h1>{{ t('contact_title') }}</h1>
            <p>{{ t('contact_subtitle') }}</p>
        </div>

        <div class="cu-wrap">
            <v-row>
                <!-- Contact info -->
                <v-col cols="12" md="4">
                    <v-card rounded="xl" elevation="1" class="pa-5">
                        <h2 class="cu-card-title">{{ t('contact_support') }}</h2>

                        <div v-for="info in contactInfo" :key="info.label" class="cu-info-row">
                            <div class="cu-icon">
                                <v-icon color="#76543D" size="20">{{ info.icon }}</v-icon>
                            </div>
                            <div class="cu-info-text">
                                <div class="cu-info-label">{{ info.label }}</div>
                                <a v-if="info.href" :href="info.href" class="cu-info-value" :dir="info.dir">{{ info.value }}</a>
                                <div v-else class="cu-info-value" :dir="info.dir">{{ info.value }}</div>
                            </div>
                        </div>
                    </v-card>
                </v-col>

                <!-- Form -->
                <v-col cols="12" md="8">
                    <v-card rounded="xl" elevation="1" class="pa-5 pa-sm-6">
                        <h2 class="cu-card-title">{{ t('contact_form_title') }}</h2>
                        <p class="cu-card-sub">{{ t('contact_form_sub') }}</p>

                        <v-alert v-if="success" type="success" variant="tonal" rounded="lg" class="mb-4" density="compact">{{ success }}</v-alert>

                        <v-form @submit.prevent="submit">
                            <v-row dense>
                                <v-col cols="12">
                                    <label class="field-label">{{ t('contact_full_name') }}</label>
                                    <v-text-field v-model="form.full_name" variant="outlined" density="comfortable" rounded="lg" bg-color="#FAF6EF" hide-details="auto" class="mt-1 mb-3" :error-messages="form.errors.full_name" />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <label class="field-label">{{ t('email') }}</label>
                                    <v-text-field v-model="form.contact_email" variant="outlined" density="comfortable" rounded="lg" bg-color="#FAF6EF" hide-details="auto" class="mt-1 mb-3" dir="ltr" :error-messages="form.errors.contact_email" />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <label class="field-label">{{ t('phone') }}</label>
                                    <v-text-field v-model="form.phone_number" variant="outlined" density="comfortable" rounded="lg" bg-color="#FAF6EF" hide-details="auto" class="mt-1 mb-3" dir="ltr" :error-messages="form.errors.phone_number" />
                                </v-col>
                                <v-col cols="12">
                                    <label class="field-label">{{ t('contact_message') }}</label>
                                    <v-textarea v-model="form.text" variant="outlined" density="comfortable" rounded="lg" bg-color="#FAF6EF" hide-details="auto" rows="4" class="mt-1 mb-4" :error-messages="form.errors.text" />
                                </v-col>
                            </v-row>
                            <v-btn type="submit" color="primary" block height="48" rounded="lg" style="text-transform:none; font-size:15px; font-weight:600" :loading="form.processing">
                                <v-icon start size="18" class="cu-send-icon">mdi-send</v-icon>
                                {{ t('contact_send') }}
                            </v-btn>
                        </v-form>
                    </v-card>
                </v-col>
            </v-row>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SeoHead from '../../components/Shared/SeoHead.vue';
import { useLocale } from '../../composables/useLocale';
const { t } = useLocale();

const form = useForm({ full_name: '', contact_email: '', phone_number: '', text: '' });
const success = computed(() => usePage().props.flash?.success);
const contact = computed(() => usePage().props.contact ?? {});

// Contact details come from the store settings (no hardcoded
// phone/email — the old page showed placeholder values). Only
// rows with a value are shown.
const contactInfo = computed(() => {
    const rows = [];
    if (contact.value.phone) {
        rows.push({
            icon: 'mdi-phone-outline',
            label: t('contact_phone_label'),
            value: contact.value.phone,
            href: 'tel:' + String(contact.value.phone).replace(/\s/g, ''),
            dir: 'ltr',
        });
    }
    if (contact.value.email) {
        rows.push({
            icon: 'mdi-email-outline',
            label: t('contact_email_label'),
            value: contact.value.email,
            href: 'mailto:' + contact.value.email,
            dir: 'ltr',
        });
    }
    if (contact.value.address) {
        rows.push({
            icon: 'mdi-map-marker-outline',
            label: t('contact_address_label'),
            value: contact.value.address,
        });
    }
    rows.push({
        icon: 'mdi-clock-outline',
        label: t('contact_hours_label'),
        value: t('contact_hours_value'),
    });
    return rows;
});

const submit = () => form.post('/contact-us', {
    onSuccess: () => form.reset(),
});
</script>

<style scoped>
.cu-page { background: #F7F0E5; min-height: 100vh; padding-bottom: 48px; }
.cu-hero { background: linear-gradient(135deg, #76543D, #8d6b51); padding: 44px 16px 54px; text-align: center; }
.cu-hero h1 { color: #fff; font-size: 30px; font-weight: 700; }
.cu-hero p { color: rgba(255, 255, 255, 0.78); font-size: 14px; margin-top: 8px; }

.cu-wrap { max-width: 960px; margin: 32px auto 0; padding: 0 16px; }

.cu-card-title { font-size: 17px; font-weight: 700; color: #302F2B; }
.cu-card-sub { font-size: 13px; color: #77736B; margin: 4px 0 20px; }

.cu-info-row { display: flex; align-items: flex-start; gap: 12px; padding: 14px 0; }
.cu-info-row + .cu-info-row { border-top: 1px solid #EFE7D8; }
.cu-icon { width: 44px; height: 44px; background: #EFE7D8; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.cu-info-text { min-width: 0; }
.cu-info-label { font-size: 11px; color: #77736B; }
.cu-info-value { font-size: 13px; font-weight: 600; color: #302F2B; text-decoration: none; word-break: break-word; }
a.cu-info-value:hover { color: #76543D; }

.field-label { font-size: 13px; font-weight: 600; color: #302F2B; }

/* mdi-send points right; in RTL it must point left (forward). */
[dir="rtl"] .cu-send-icon { transform: scaleX(-1); }
</style>
