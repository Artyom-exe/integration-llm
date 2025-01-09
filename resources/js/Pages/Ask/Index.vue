<script setup>
import { ref, nextTick } from "vue";
import { useForm } from "@inertiajs/vue3";
import ChatMessage from "@/Components/ChatMessage.vue";
import MarkdownIt from "markdown-it";
import hljs from "highlight.js";
import "highlight.js/styles/github-dark.css"; // Thème de surlignage syntaxique

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

// Initialisation de MarkdownIt avec surlignage syntaxique
const md = new MarkdownIt({
  highlight: function (str, lang) {
    if (lang && hljs.getLanguage(lang)) {
      try {
        return `<pre class="hljs"><code>${hljs.highlight(str, { language: lang }).value}</code></pre>`;
      } catch (__) { }
    }
    return `<pre class="hljs"><code>${md.utils.escapeHtml(str)}</code></pre>`;
  },
});

const form = useForm({
  message: "",
  model: props.selectedModel || props.models[0]?.id || "",
});

const messages = ref([]);
const chatContainer = ref(null);
const showChat = ref(false);
const isLoading = ref(false);
const isScrolled = ref(false);
const showDropdown = ref(false); // Gérer l'affichage du menu déroulant

// Gérer le scrolling
const handleScroll = (event) => {
  const scrollTop = event.target.scrollTop || 0;
  isScrolled.value = scrollTop > 0;
  if (scrollTop === 0) showDropdown.value = false; // Masquer le menu déroulant si l'utilisateur revient en haut
};

// Basculer l'affichage du menu déroulant
const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value;
};

// Fermer la liste déroulante si un clic est détecté en dehors
const closeDropdown = (event) => {
  if (!event.target.closest('.dropdown-container')) {
    showDropdown.value = false;
  }
};

// Sélectionner une IA depuis le menu déroulant
const selectModel = (modelId) => {
  form.model = modelId;
  showDropdown.value = false; // Fermer le menu après sélection
};

// Fonction pour ajouter un message
const addMessage = (role, content) => {
  messages.value.push({ role, content });
  nextTick(() => scrollToBottom());
};

const submitForm = () => {
  if (!form.message.trim()) return;

  if (!showChat.value) showChat.value = true;

  addMessage("user", form.message);

  isLoading.value = true;

  form.post("/ask", {
    onSuccess: (page) => {
      const response = page.props.flash?.message || "Pas de réponse.";
      addMessage("assistant", md.render(response)); // Conversion en Markdown
      isLoading.value = false;
    },
    onError: () => {
      addMessage("assistant", "Une erreur est survenue. Veuillez réessayer.");
      isLoading.value = false;
    },
  });

  form.message = "";
};

const scrollToBottom = () => {
  if (chatContainer.value) {
    chatContainer.value.scrollTo({
      top: chatContainer.value.scrollHeight,
      behavior: "smooth",
    });
  }
};

document.addEventListener('click', closeDropdown);
</script>

<template>
  <div class="flex flex-col h-screen bg-gray-900 text-white">
    <!-- Zone défilable avec scrollbar -->
    <div ref="chatContainer"
      class="scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-transparent h-full overflow-y-auto"
      @scroll="handleScroll">
      <!-- Titre et Select -->
      <header class="flex justify-between items-center py-4 px-6 bg-gray-800 shadow z-10">
        <h1 class="text-xl font-bold text-white">AI Nexus</h1>
        <div class="relative">
          <!-- Select : visible uniquement avant le scrolling -->
          <select v-if="!isScrolled" v-model="form.model"
            class="bg-gray-700 text-white rounded-lg px-4 py-2 border-none focus:outline-none focus:ring focus:ring-indigo-300 scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-transparent h-full overflow-y-auto">
            <option v-for="model in models" :key="model.id" :value="model.id">
              {{ model.name }}
            </option>
          </select>

          <!-- Bouton rond avec flèche : visible après le scrolling -->
          <div v-else class="fixed top-4 right-6">
            <div class="dropdown-container relative">

              <button @click="toggleDropdown"
                class="p-2 bg-gray-700 rounded-full shadow-lg text-white flex items-center justify-center transition-all duration-250 ease-in-out transform hover:scale-90"
                aria-label="Select Models">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                  class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Menu déroulant -->
              <div v-if="showDropdown"
                class="absolute top-14 right-0 bg-gray-700 text-white rounded-lg shadow-lg overflow-hidden max-h-96 w-96 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-transparent">
                <ul>
                  <li v-for="model in models" :key="model.id" @click="selectModel(model.id)"
                    class="px-4 py-2 hover:bg-gray-600 cursor-pointer">
                    {{ model.name }}
                  </li>
                </ul>
              </div>
            </div>

            </div>
          </div>
      </header>

      <!-- Zone de discussion -->
      <div v-if="showChat"
        class="w-full max-w-2xl flex-grow px-4 py-6 mx-auto bg-gray-900 rounded-lg flex flex-col space-y-4">
        <ChatMessage v-for="(message, index) in messages" :key="index" :message="message" />
      </div>
    </div>

    <!-- Champ d'entrée -->
    <div :class="showChat
      ? 'bottom-0 px-4 pb-4 flex items-center justify-center bg-gray-900'
      : 'flex items-center justify-center flex-grow absolute top-1/2 w-full bg-gray-900'">
      <form @submit.prevent="submitForm" class="w-full max-w-2xl">
        <div class="flex items-center space-x-3 bg-gray-700 rounded-full px-6 py-3">
          <input v-model="form.message" placeholder="Message AI Nexus..."
            class="flex-grow bg-gray-700 text-white border-none focus:outline-none focus:ring-0 placeholder-gray-400 placeholder-opacity-75 px-4 py-2 rounded-full" />
          <button type="submit"
            class="bg-blue-500 text-white p-2 rounded-full shadow hover:bg-blue-600 focus:outline-none disabled:bg-blue-300"
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
