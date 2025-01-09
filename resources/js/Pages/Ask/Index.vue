<script setup>
import { ref, nextTick } from "vue";
import { useForm } from "@inertiajs/vue3";
import ChatMessage from "@/Components/ChatMessage.vue";
import MarkdownIt from "markdown-it";

const props = defineProps({
  models: {
    type: Array,
    default: () => [],
  },
  selectedModel: {
    type: String,
    default: null,
  },
  flash: {
    type: Object,
    default: () => ({}),
  },
});

const md = new MarkdownIt();

const form = useForm({
  message: "",
  model: props.selectedModel || props.models[0]?.id || "",
});

const messages = ref([]);
const chatContainer = ref(null);
const showChat = ref(false);
const isLoading = ref(false);
const isScrolled = ref(false);

const submitForm = () => {
  if (!form.message.trim()) return;

  if (!showChat.value) showChat.value = true;

  messages.value.push({ role: "user", content: form.message });

  isLoading.value = true;

  form.post("/ask", {
    onSuccess: (page) => {
      const response = page.props.flash?.message || "Pas de réponse.";
      messages.value.push({ role: "assistant", content: md.render(response) });
      isLoading.value = false;
      nextTick(() => scrollToBottom());
    },
    onError: () => {
      messages.value.push({
        role: "assistant",
        content: "Une erreur est survenue. Veuillez réessayer.",
      });
      isLoading.value = false;
      nextTick(() => scrollToBottom());
    },
  });

  form.message = "";
  nextTick(() => scrollToBottom());
};

const scrollToBottom = () => {
  if (chatContainer.value) {
    chatContainer.value.scrollTo({
      top: chatContainer.value.scrollHeight,
      behavior: "smooth",
    });
  }
};

const handleScroll = (event) => {
  const scrollTop = event.target.scrollTop || 0;
  if (scrollTop > 0 && !isScrolled.value) {
    isScrolled.value = true;
  } else if (scrollTop === 0 && isScrolled.value) {
    isScrolled.value = false;
  }
};
</script>



<template>
  <div class="flex flex-col h-screen bg-gray-900 text-white">
    <!-- Zone défilable avec scrollbar -->
    <div ref="chatContainer"
      class="scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-transparent h-full overflow-y-auto"
      @scroll="handleScroll">
      <!-- Titre et Select -->
      <!-- Titre et Select -->
      <header class="flex justify-between items-center py-4 px-6 bg-gray-800 shadow z-10">
        <h1 class="text-xl font-bold text-white">AI Nexus</h1>

        <!-- Conteneur avec Select et Flèche -->
        <div class="relative">
          <!-- Select avec transition de disparition -->
          <select v-if="!isScrolled" v-model="form.model"
            class="transition-all duration-500 ease-in-out opacity-100 scale-100 transform bg-gray-700 text-white rounded-lg px-4 py-2 border-none focus:outline-none focus:ring focus:ring-indigo-300"
            :class="{ 'opacity-0 scale-75': isScrolled }">
            <option v-for="model in models" :key="model.id" :value="model.id">
              {{ model.name }}
            </option>
          </select>

          <!-- Flèche avec transition d'apparition -->
          <svg v-if="isScrolled" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
            stroke="currentColor"
            class="transition-all duration-500 ease-in-out opacity-0 scale-75 transform fixed top-4 right-6 w-12 h-12 bg-gray-700 rounded-full shadow-lg text-white p-2 hover:scale-110"
            :class="{ 'opacity-100 scale-100': isScrolled }">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </header>

      <!-- Zone de discussion -->
      <div v-if="showChat"
        class="w-full max-w-4xl flex-grow px-4 py-6 mx-auto bg-gray-900 rounded-lg flex flex-col space-y-4">
        <ChatMessage v-for="(message, index) in messages" :key="index" :message="message" />
      </div>
    </div>

    <!-- Champ d'entrée -->
    <div :class="showChat
      ? 'bottom-0 w-full px-4 pb-4 flex items-center justify-center bg-gray-900'
      : 'flex items-center justify-center flex-grow absolute top-1/2 w-full bg-gray-900'">
      <form @submit.prevent="submitForm" class="w-full max-w-4xl">
        <div class="flex items-center space-x-3 bg-gray-700 rounded-full px-6 py-3">
          <!-- Champ de saisie -->
          <input v-model="form.message" placeholder="Message AI Nexus..."
            class="flex-grow bg-gray-700 text-white border-none focus:outline-none focus:ring-0 placeholder-gray-400 placeholder-opacity-75 px-4 py-2 rounded-full" />
          <!-- Bouton d'envoi -->
          <button type="submit"
            class="bg-blue-500 text-white p-3 rounded-full shadow hover:bg-blue-600 focus:outline-none disabled:bg-blue-300"
            :disabled="isLoading">
            <template v-if="!isLoading">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7l7 7-7 7" />
              </svg>
            </template>
            <template v-else>
              <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
            </template>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
