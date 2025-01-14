<script setup>
import { ref, watch, nextTick } from "vue";
import { useForm } from "@inertiajs/vue3";
import ChatMessage from "@/Components/ChatMessage.vue";
import MarkdownIt from "markdown-it";
import hljs from "highlight.js";
import "highlight.js/styles/github-dark.css";
import axios from "axios";

const props = defineProps({
  models: {
    type: Array,
    default: () => [],
  },
  selectedModel: {
    type: String,
    default: null,
  },
  conversations: {
    type: Array,
    default: () => [],
  },
});

const md = new MarkdownIt({
  highlight: (str, lang) =>
    lang && hljs.getLanguage(lang)
      ? `<pre class="hljs"><code>${hljs.highlight(str, { language: lang }).value}</code></pre>`
      : `<pre class="hljs"><code>${md.utils.escapeHtml(str)}</code></pre>`,
});

const form = useForm({
  message: "",
  model: props.selectedModel || props.models[0]?.id || "",
});

const conversations = ref([...props.conversations]);
const messages = ref([]);
const activeConversationId = ref(null);
const chatContainer = ref(null);
const searchQuery = ref("");
const filteredConversations = ref([]);
const isLoading = ref(false);

// Filtrer les conversations en fonction de la recherche
const filterConversations = (query) => {
  return conversations.value
    .filter((conv) =>
      conv.title?.toLowerCase().includes(query.toLowerCase())
    )
    .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));
};

watch(searchQuery, (newQuery) => {
  filteredConversations.value = filterConversations(newQuery);
}, { immediate: true });

// Sélectionner une conversation
const selectConversation = (conversationId) => {
  activeConversationId.value = conversationId;

  const selectedConversation = conversations.value.find(
    (conv) => conv.id === conversationId
  );

  if (selectedConversation) {
    messages.value = selectedConversation.messages || [];
    form.model = selectedConversation.model; // Mettre à jour le modèle d'IA
    nextTick(scrollToBottom);
  }
};

// Ajouter un message
const addMessage = (role, content) => {
  messages.value.push({ role, content });
  nextTick(scrollToBottom);
};

// Créer une nouvelle conversation
const createNewConversation = async () => {
  try {
    const response = await axios.post("/conversations", {
      model: form.model,
    });

    const data = response.data;
    conversations.value.unshift(data.conversation);
    selectConversation(data.conversation.id);
  } catch (error) {
    console.error("Erreur lors de la création de la conversation", error);
  }
};

// Envoyer un message
const sendMessage = async () => {
  addMessage("user", form.message);
  isLoading.value = true;

  try {
    const response = await axios.post(`/conversations/${activeConversationId.value}/messages`, {
      message: form.message,
    });

    const data = response.data;
    addMessage("assistant", md.render(data.message.content));

    const conversation = conversations.value.find(
      (conv) => conv.id === activeConversationId.value
    );
    if (conversation) {
      if (!Array.isArray(conversation.messages)) {
        conversation.messages = [];
      }
      conversation.messages.push(data.message);
      if (conversation.title === 'Nouvelle conversation') {
        conversation.title = data.message.content.substring(0, 20) + "...";
      }
    }
  } catch (error) {
    addMessage("assistant", "Une erreur est survenue. Veuillez réessayer.");
    console.error("Erreur lors de l'envoi du message", error);
  } finally {
    isLoading.value = false;
    form.message = "";
  }
};

// Envoyer le formulaire
const submitForm = () => {
  if (!form.message.trim()) return;

  if (!activeConversationId.value) {
    createNewConversation();
  } else {
    sendMessage();
  }
};

// Faire défiler jusqu'en bas
const scrollToBottom = () => {
  if (chatContainer.value) {
    chatContainer.value.scrollTo({
      top: chatContainer.value.scrollHeight,
      behavior: "smooth",
    });
  }
};
</script>

<template>
  <div class="flex h-screen bg-gray-900 text-white">
    <!-- Sidebar -->
    <aside class="w-1/4 bg-gray-800 border-r border-gray-700 flex flex-col">
      <div class="p-4 flex items-center justify-between">
        <h1 class="text-lg font-bold">AI Nexus</h1>
        <select
          v-model="form.model"
          class="bg-gray-600 text-white rounded-lg px-2 py-1 border-none focus:ring focus:ring-blue-500"
        >
          <option v-for="model in models" :key="model.id" :value="model.id">
            {{ model.name }}
          </option>
        </select>
      </div>

      <!-- Recherche -->
      <div class="p-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher une conversation..."
          class="w-full bg-gray-700 text-white rounded-lg px-4 py-2 placeholder-gray-400 focus:outline-none focus:ring focus:ring-blue-500"
        />
      </div>

      <!-- Liste des conversations -->
      <ul class="flex-grow overflow-y-auto">
        <li
          v-for="conversation in filteredConversations"
          :key="conversation.id"
          :class="[ 'px-4 py-3 cursor-pointer hover:bg-gray-700', activeConversationId === conversation.id ? 'bg-gray-700' : '' ]"
          @click="selectConversation(conversation.id)"
        >
          <h3 class="font-semibold text-white">{{ conversation.title || "Nouvelle conversation" }}</h3>
          <p class="text-sm text-gray-400">
            {{ new Date(conversation.updated_at).toLocaleString() }}
          </p>
        </li>
      </ul>

      <div class="p-4">
        <button
          @click="createNewConversation"
          class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition"
        >
          Nouvelle Conversation
        </button>
      </div>
    </aside>

    <!-- Chat -->
    <div class="flex flex-col flex-grow">
      <div ref="chatContainer" class="flex-grow overflow-y-auto p-6 bg-gray-900">
        <ChatMessage v-for="(message, index) in messages" :key="index" :message="message" />
      </div>

      <div class="p-4 bg-gray-800">
        <form @submit.prevent="submitForm" class="flex items-center space-x-4">
          <input
            v-model="form.message"
            placeholder="Message AI Nexus..."
            class="flex-grow bg-gray-700 text-white border-none focus:outline-none px-4 py-2 rounded-lg"
          />
          <button
            type="submit"
            class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition"
            :disabled="isLoading"
          >
            <span v-if="!isLoading">Envoyer</span>
            <span v-else>Chargement...</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
