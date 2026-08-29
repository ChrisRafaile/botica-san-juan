<template>
  <div class="min-h-screen">
    <!-- Header -->
    <Header />

    <!-- Contact Hero Section -->
    <ContactHero />

    <!-- Contact Form Section -->
    <ContactForm />

    <!-- Contact Information Section -->
    <ContactInfo />

    <!-- Footer -->
    <Footer />
  </div>
</template>

<script setup lang="ts">
import Header from '../components/Header.vue'
import Footer from '../components/Footer.vue'
import ContactHero from '../components/ContactHero.vue'
import ContactForm from '../components/ContactForm.vue'
import ContactInfo from '../components/ContactInfo.vue'
</script>

<style scoped>
/* Page-specific styles if needed */
</style>