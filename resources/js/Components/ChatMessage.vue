<script setup>

const props = defineProps({
  message: {
    type: Object,
    required: true,
  },
});

// Initialisation de MarkdownIt pour la conversion Markdown
</script>

<template>
  <div class="flex mb-4">
    <!-- Message utilisateur : Aligné à droite -->
    <div
      v-if="message.role === 'user'"
      class="bg-gray-700 text-white px-4 py-2 rounded-lg ml-auto"
    >
      {{ message.content }}
    </div>

    <!-- Message IA : Aligné à gauche et rendu en Markdown -->
    <div
    v-else-if="message.role === 'assistant'"
    class="bg-gray-800 text-gray-300 px-4 py-2 rounded-lg mr-auto prose prose-invert"
    v-html="message.content"
    ></div>
  </div>
</template>
