<script setup>
const props = defineProps({
  message: {
    type: Object,
    required: true,
    validator(value) {
      return (
        ["user", "assistant"].includes(value.role) && typeof value.content === "string"
      );
    },
  },
});
</script>



<template>
  <div class="flex mb-4 w-3/6">
    <!-- Message utilisateur : Aligné à droite -->
    <div v-if="message.role === 'user'" class="bg-gray-700 text-white px-4 py-2 rounded-lg ml-auto"
      aria-label="Message utilisateur">
      {{ message.display_content || message.content }}
    </div>

    <!-- Message IA : Aligné à gauche et rendu en Markdown avec surlignage syntaxique -->
    <div v-else-if="message.role === 'assistant'"
      class="text-gray-300 px-4 py-2 rounded-lg mr-auto prose prose-invert" v-html="message.content"
      aria-label="Message assistant"></div>
  </div>
</template>


