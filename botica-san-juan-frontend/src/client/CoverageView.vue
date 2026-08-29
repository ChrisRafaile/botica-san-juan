<template>
  <div class="min-h-screen">
    <!-- Header -->
    <Header />

    <!-- Coverage Hero Section -->
    <CoverageHero />

    <!-- Coverage Selector Section -->
    <CoverageSelector />

    <!-- Coverage Services Section -->
    <CoverageServices />

    <!-- Coverage Contact Section -->
    <CoverageContact />

    <!-- Footer -->
    <Footer />
  </div>
</template>

<script setup lang="ts">
import Header from '../components/Header.vue'
import Footer from '../components/Footer.vue'
import CoverageHero from '../components/CoverageHero.vue'
import CoverageSelector from '../components/CoverageSelector.vue'
import CoverageServices from '../components/CoverageServices.vue'
import CoverageContact from '../components/CoverageContact.vue'
</script>