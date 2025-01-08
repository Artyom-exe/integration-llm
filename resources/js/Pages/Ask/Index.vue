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
    chatContainer.value.scrollTo({ top: chatContainer.value.scrollHeight, behavior: "smooth" });
  }
};
</script>


<template>
  <div class="flex flex-col h-screen bg-gray-900 text-white">
    <!-- Titre avec dropdown -->
    <header class="flex justify-between items-center py-4 px-6 bg-gray-800 shadow">
      <h1 class="text-xl font-bold">AI Nexus</h1>
      <div class="relative">
        <select
          v-model="form.model"
          class="bg-gray-700 text-white rounded-lg px-4 py-2 border-none focus:outline-none focus:ring focus:ring-indigo-300 scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-transparent"
        >
          <option v-for="model in models" :key="model.id" :value="model.id">
            {{ model.name }}
          </option>
        </select>
      </div>
    </header>

    <!-- Zone de discussion -->
    <div
      v-if="showChat"
      ref="chatContainer"
      class="w-full max-w-4xl flex-grow px-4 py-6 mx-auto bg-gray-900 rounded-lg shadow-md overflow-y-auto scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-transparent flex flex-col space-y-4"
    >
      <ChatMessage
        v-for="(message, index) in messages"
        :key="index"
        :message="message"
      />
    </div>

    <!-- Champ d'entrée -->
    <div
      :class="showChat ? 'px-4 py-4 flex items-center justify-center' : 'flex items-center justify-center flex-grow'"
    >
      <form @submit.prevent="submitForm" class="w-full max-w-4xl">
        <div class="flex items-center space-x-3 bg-gray-700 rounded-full px-6 py-3">
          <!-- Champ de saisie -->
          <input
            v-model="form.message"
            placeholder="Message ChatGPT"
            class="flex-grow bg-gray-700 text-white border-none focus:outline-none focus:ring-0 placeholder-gray-400 placeholder-opacity-75 px-4 py-2 rounded-full"
          />

          <!-- Bouton d'envoi -->
          <button
            type="submit"
            class="bg-blue-500 text-white p-3 rounded-full shadow hover:bg-blue-600 focus:outline-none disabled:bg-blue-300"
            :disabled="isLoading"
          >
            <template v-if="!isLoading">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 12h14m-7-7l7 7-7 7"
                />
              </svg>
            </template>
            <template v-else>
              <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                ></circle>
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                ></path>
              </svg>
            </template>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
