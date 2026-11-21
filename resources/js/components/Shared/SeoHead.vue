<template>
    <Head>
        <title>{{ fullTitle }}</title>
        <meta v-if="description" name="description" :content="description" />
        <meta v-if="keywords" name="keywords" :content="keywords" />
        <meta name="robots" :content="robots" />

        <!-- Open Graph -->
        <meta property="og:type"        :content="ogType" />
        <meta property="og:site_name"   :content="siteName" />
        <meta property="og:title"       :content="ogTitle || fullTitle" />
        <meta v-if="ogDesc"  property="og:description" :content="ogDesc" />
        <meta v-if="ogImage" property="og:image"       :content="ogImage" />
        <meta property="og:url"         :content="canonical" />

        <!-- Twitter Card -->
        <meta name="twitter:card"        :content="twCard" />
        <meta name="twitter:title"       :content="twTitle || fullTitle" />
        <meta v-if="twDesc"  name="twitter:description" :content="twDesc" />
        <meta v-if="twImage" name="twitter:image"       :content="twImage" />

        <!-- Canonical — the single source: app.blade.php emits none -->
        <link rel="canonical" :href="canonical" />
    </Head>
</template>

<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

const props = defineProps({
    title:       { type: String, default: '' },
    description: { type: String, default: '' },
    keywords:    { type: String, default: '' },
    image:       { type: String, default: '' },
    canonical:   { type: String, default: '' },
    // e.g. "product" on the product page; was hardcoded "website" everywhere.
    ogType:      { type: String, default: 'website' },
    // Private pages (checkout, payment, profile) set this to stay out of indexes.
    noindex:     { type: Boolean, default: false },
});

const { props: pageProps } = usePage();
const global = computed(() => pageProps.seo || {});

const siteName    = computed(() => global.value.site_name || '');
const fullTitle   = computed(() => props.title
    ? `${props.title} — ${siteName.value}`
    : (global.value.meta_title || siteName.value));

// Meta descriptions used to come from raw product descriptions (HTML,
// unbounded length). Strip tags and cap at ~160 chars.
const cleanDescription = (text) => {
    if (!text) return '';
    const plain = String(text).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return plain.length > 160 ? plain.slice(0, 157).trimEnd() + '…' : plain;
};
const description = computed(() => cleanDescription(props.description || global.value.meta_description || ''));
const keywords    = computed(() => props.keywords    || global.value.meta_keywords    || '');
const ogType      = computed(() => props.ogType);
const ogTitle     = computed(() => props.title       || global.value.og_title         || fullTitle.value);
const ogDesc      = computed(() => description.value || global.value.og_description   || '');
const ogImage     = computed(() => props.image       || global.value.og_image         || '');
const twCard      = computed(() => global.value.twitter_card || 'summary_large_image');
const twTitle     = computed(() => ogTitle.value);
const twDesc      = computed(() => ogDesc.value);
const twImage     = computed(() => props.image || global.value.twitter_image || ogImage.value);
const robots      = computed(() => props.noindex ? 'noindex,nofollow' : (global.value.robots_index || 'index,follow'));
// The settings canonical_url is deliberately NOT used here: it is one
// URL for the whole site, and using it forced the same canonical onto
// every page. A page either passes its own canonical or falls back to
// its own URL — without the query string or hash, so filtered/paginated
// URLs (/products?sort=price_asc, ?page=2, ...) don't canonicalize
// themselves as separate pages.
const stripQueryAndHash = (url) => {
    try {
        const u = new URL(url);
        return u.origin + u.pathname;
    } catch {
        return String(url).split(/[?#]/)[0];
    }
};
const canonical   = computed(() => props.canonical || (typeof window !== 'undefined' ? stripQueryAndHash(window.location.href) : ''));
</script>
