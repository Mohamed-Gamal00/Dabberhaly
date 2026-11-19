<template>
  <v-container fluid class="pa-0">
    <!-- Dynamic banners from DB -->
    <template v-if="banners && banners.length">
      <v-carousel
        v-if="banners.length > 1"
        hide-delimiter-background
        show-arrows="hover"
        cycle
        height="clamp(180px, 25.5vw, 520px)"
      >
        <v-carousel-item
          v-for="(banner, index) in banners"
          :key="index"
        >
          <component :is="banner.link ? 'a' : 'div'" :href="banner.link || undefined" :target="banner.link && isExternal(banner.link) ? '_blank' : undefined" :rel="banner.link && isExternal(banner.link) ? 'noopener' : undefined" class="banner-link">
            <v-img :src="banner.image" alt="Banner Image" cover />
          </component>
        </v-carousel-item>
      </v-carousel>

      <!-- Single banner — no carousel overhead -->
      <component v-else :is="banners[0].link ? 'a' : 'div'" :href="banners[0].link || undefined" :target="banners[0].link && isExternal(banners[0].link) ? '_blank' : undefined" :rel="banners[0].link && isExternal(banners[0].link) ? 'noopener' : undefined" class="banner-link">
        <v-img :src="banners[0].image" alt="Banner Image" cover />
      </component>
    </template>

    <!-- Fallback to static image when no banners in DB -->
    <v-img v-else :src="staticBanner" alt="Banner Image" cover />
  </v-container>
</template>

<script setup>
import staticBanner from "@/assets/images/banner-2.jpg";

defineProps({
  banners: {
    type: Array,
    default: () => [],
  },
});

// A banner without a link renders as a plain <div>, never a dead href="#".
// Internal links stay in the same tab; only external links open a new tab.
function isExternal(link) {
  return /^https?:\/\//i.test(link || '');
}
</script>

<style scoped>
.banner-link { display: block; }
</style>
