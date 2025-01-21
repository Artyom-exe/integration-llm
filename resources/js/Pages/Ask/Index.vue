<script setup>
import { ref, watch, nextTick, onMounted } from "vue";
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
const channelSubscription = ref(null);

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
    // S'assurer que les messages existent
    const conversationMessages = selectedConversation.messages || [];

    messages.value = conversationMessages.map(message => ({
      ...message,
      content: message.role === 'assistant' ? md.render(message.content) : message.content
    }));

    form.model = selectedConversation.model;
    setupWebSocket(conversationId);
    nextTick(scrollToBottom);
  }
};

// Configuration du WebSocket pour le streaming
const setupWebSocket = (conversationId) => {
  if (channelSubscription.value) {
    channelSubscription.value.unsubscribe();
  }

  const channel = `chat.${conversationId}`;
  let currentText = '';
  let typingTimeout;

  const typeText = (text, index = 0) => {
    if (index <= text.length) {
      const lastMessage = messages.value[messages.value.length - 1];
      if (lastMessage && lastMessage.role === 'assistant') {
        // Afficher progressivement le texte
        lastMessage.content = md.render(text.slice(0, index));

        // Scroll automatique
        chatContainer.value?.scrollTo({
          top: chatContainer.value.scrollHeight,
          behavior: 'auto'
        });

        // Programmer le prochain caractère
        typingTimeout = setTimeout(() => {
          typeText(text, index + 1);
        }, 1); // Ajustez cette valeur pour la vitesse de frappe
      }
    }
  };

  channelSubscription.value = window.Echo.private(channel)
    .subscribed(() => console.log("✅ Connecté au canal:", channel))
    .error(error => console.error("❌ Erreur:", error))
    .listen(".message.streamed", (event) => {
      if (event.error) {
        messages.value.pop();
        isLoading.value = false;
        return;
      }

      // Gestion du streaming du titre
      if (event.isTitle) {
        const conversation = conversations.value.find(c => c.id === activeConversationId.value);
        if (conversation) {
          conversation.title = event.content;
          // Mettre à jour les conversations filtrées
          filteredConversations.value = filterConversations(searchQuery.value);
        }
        return;
      }

      // Effacer le timeout précédent si existe
      if (typingTimeout) {
        clearTimeout(typingTimeout);
      }

      if (!event.isComplete) {
        currentText += event.content;
        typeText(currentText);
      } else {
        // Message final
        currentText = event.content;
        typeText(currentText);
        isLoading.value = false;

        // Mise à jour de la conversation
        const conversation = conversations.value.find(c => c.id === activeConversationId.value);
        if (conversation?.messages) {
          const existingMessage = conversation.messages.find(m => m.content === '');
          if (existingMessage) {
            existingMessage.content = event.content;
          } else {
            conversation.messages.push({
              role: 'assistant',
              content: event.content
            });
          }
        }
      }
    });
};

// Ajouter un message
const addMessage = (role, content) => {
  const message = { role, content };
  messages.value.push(message);
  nextTick(() => {
    chatContainer.value?.scrollTo({
      top: chatContainer.value.scrollHeight,
      behavior: 'smooth'
    });
  });
  return message;
};

// Créer une nouvelle conversation
const createNewConversation = async (withMessage = false) => {
  try {
    const response = await axios.post("/conversations", {
      model: form.model,
    });

    const data = response.data;
    const newConversation = {
      ...data.conversation,
      messages: [],
      title: 'Nouvelle conversation'
    };

    conversations.value.unshift(newConversation);
    selectConversation(newConversation.id);

    // Envoyer le message seulement si on en a un
    if (withMessage) {
      await sendMessage();
    }
  } catch (error) {
    console.error("Erreur lors de la création de la conversation", error);
  }
};

// Écouter les nouvelles conversations
const listenForNewConversations = () => {
  window.Echo.private('conversations')
    .listen('.conversation.created', (event) => {
      // Chercher si la conversation existe déjà
      const existingConversation = conversations.value.find(c => c.id === event.conversation.id);

      if (existingConversation) {
        // Mettre à jour la conversation existante
        Object.assign(existingConversation, event.conversation);
      } else {
        // Ajouter la nouvelle conversation seulement si elle n'existe pas
        conversations.value.unshift({
          ...event.conversation,
          messages: []
        });
      }
      // Mettre à jour les conversations filtrées
      filteredConversations.value = filterConversations(searchQuery.value);
    });
};

onMounted(() => {
  if (props.conversations && props.conversations.length) {
    selectConversation(props.conversations[0].id);
  }
  listenForNewConversations();
});

// Envoyer un message
const sendMessage = async () => {
  addMessage("user", form.message);
  addMessage("assistant", ""); // Message vide qui sera mis à jour par le stream
  isLoading.value = true;

  try {
    await axios.post(`/conversations/${activeConversationId.value}/messages`, {
      message: form.message,
      model: form.model,
    });
    form.message = "";
  } catch (error) {
    messages.value.pop();
    addMessage("assistant", "Une erreur est survenue. Veuillez réessayer.");
    console.error("Erreur lors de l'envoi du message", error);
    isLoading.value = false;
  }
};

// Envoyer le formulaire
const submitForm = () => {
  if (!form.message.trim()) return;

  if (!activeConversationId.value) {
    createNewConversation(true); // true indique qu'on a un message à envoyer
  } else {
    sendMessage();
  }
  form.message = ""; // Effacer le champ d'entrée après l'envoi du message
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

// Mettre à jour le modèle d'IA pour une conversation
const updateModel = async (conversationId, model) => {
  if (!conversationId || !model) return;

  try {
    const response = await axios.put(`/conversations/${conversationId}/model`, {
      model: model,
    });

    // Mettre à jour la conversation dans le state
    const conversation = conversations.value.find(c => c.id === conversationId);
    if (conversation && response.data.conversation) {
      // Sauvegarder les messages actuels
      const currentMessages = [...messages.value];

      // Mettre à jour la conversation
      Object.assign(conversation, response.data.conversation);

      // Si c'est la conversation active, restaurer les messages
      if (activeConversationId.value === conversationId) {
        messages.value = currentMessages;
      }
    }
  } catch (error) {
    console.error("Erreur lors de la mise à jour du modèle", error);
  }
};

// Surveiller les changements de modèle
watch(() => form.model, (newModel, oldModel) => {
  if (activeConversationId.value && newModel !== oldModel) {
    updateModel(activeConversationId.value, newModel);
  }
});

function adjustHeight(event) {
  const textarea = event.target;
  textarea.style.height = '40px'; // Hauteur par défaut.
  textarea.style.height = `${textarea.scrollHeight}px`; // Ajustement dynamique.
}


</script>

<template>

  <div class="flex h-screen bg-gray-900 text-white">
    <!-- Sidebar -->
    <aside class=" min-w-60 max-w-60 bg-gray-800 border-r border-gray-700 flex flex-col">

      <!-- Recherche -->
      <div class="p-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher une conversation..."
          class="w-full bg-gray-700 text-center mb-2 text-white text-xs rounded-lg px-4 py-2 placeholder-gray-400 focus:outline-none focus:ring-0 border-none"
        />
        <button
          @click="createNewConversation(false)"
          class="w-full text-xs  bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition"
        >
          Nouvelle Conversation
        </button>
      </div>

      <!-- Liste des conversations -->
      <ul class="flex-grow overflow-y-auto scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-800">
        <li
          v-for="conversation in filteredConversations"
          :key="conversation.id"
          :class="[ 'px-4 py-3 cursor-pointer hover:bg-gray-700', activeConversationId === conversation.id ? 'bg-gray-700' : '' ]"
          @click="selectConversation(conversation.id)"
        >
          <h3 class="text-white text-sm text">{{ conversation.title || "Nouvelle conversation" }}</h3>
          <p class="text-xs text-gray-400">
            {{ new Date(conversation.updated_at).toLocaleString() }}
          </p>
        </li>
      </ul>
    </aside>

    <!-- Chat -->
    <div class="flex flex-col flex-grow">

    <header class="bg-gray-900 p-4 flex items-center justify-between">
      <select
        v-model="form.model"
        class="bg-gray-900 text-white text-sm rounded-lg px-2 py-1 border-none focus:outline-none focus:ring-0 focus:bg-gray-800 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-800"
      >
        <option v-for="model in models" :key="model.id" :value="model.id" class="text-xs">
          {{ model.name }}
        </option>
      </select>
    </header>

      <div ref="chatContainer" class="flex-grow overflow-y-auto p-6 flex flex-col items-center bg-gray-900 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-800">
        <ChatMessage v-for="(message, index) in messages" :key="index" :message="message" />
      </div>


      <div class="p-6 pt-0 bg-gray-900 justify-center flex">
        <form @submit.prevent="submitForm" class="flex items-center space-x-4 w-full justify-center">
          <div class="w-3/6 bg-gray-700 rounded-3xl">
            <textarea
              v-model="form.message"
              placeholder="Message AI Nexus..."
              class="bg-gray-700 text-white border-none pt-3 pl-3 pb-0 rounded-t-3xl w-full text-sm focus:outline-none focus:ring-0 resize-none scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-800 max-h-[200px] h-[40px]"
              @input="adjustHeight($event)"
              @keydown.enter.prevent="submitForm"
              >

          </textarea>
            <div class="bg-gray-700 text-white rounded-b-3xl flex justify-end px-2 py-2">
              <button
                type="submit"
                class="bg-blue-500 text-white px-4 py-2 hover:bg-blue-600 transition rounded-full w-8 h-8"
                :disabled="isLoading"
                >
                <span v-if="!isLoading"></span>
                <span v-else>Chargement...</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
