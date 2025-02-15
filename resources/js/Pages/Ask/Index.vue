<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import ChatMessage from "@/Components/ChatMessage.vue";
import MarkdownIt from "markdown-it";
import hljs from "highlight.js";
import "highlight.js/styles/github-dark.css";
import axios from "axios";
import CustomInstructionsModal from '@/Components/CustomInstructionsModal.vue';

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
const showInstructionsModal = ref(false);

// Modifier les états de connexion
const isConnecting = ref(false);
const reconnectAttempts = ref(0);
const maxReconnectAttempts = 5; // Augmenté à 5 tentatives

// Ajouter un état pour suivre les connexions tentées
const attemptedConnections = ref(new Set());

// Ajouter l'état pour le menu mobile
const isMobileMenuOpen = ref(false);

// Fonction pour fermer le menu mobile
const closeMobileMenu = () => {
  isMobileMenuOpen.value = false;
};

// Fonction de nettoyage du WebSocket améliorée
const cleanupWebSocket = async () => {
  try {
    if (channelSubscription.value) {
      await channelSubscription.value.unsubscribe();
      channelSubscription.value = null;
    }
    isConnecting.value = false;
    reconnectAttempts.value = 0;

    // Réinitialiser Laravel Echo pour ce canal
    if (activeConversationId.value) {
      window.Echo.leave(`chat.${activeConversationId.value}`);
    }
  } catch (error) {
    console.error("Erreur lors du nettoyage du WebSocket:", error);
  }
};

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

// Sélection de conversation optimisée
const selectConversation = async (conversationId) => {
  try {
    // Nettoyage complet avant de changer de conversation
    await cleanupWebSocket();

    activeConversationId.value = conversationId;
    const selectedConversation = conversations.value.find(
      (conv) => conv.id === conversationId
    );

    if (!selectedConversation) {
      throw new Error("Conversation non trouvée");
    }

    // Toujours recharger les données de la conversation depuis le serveur
    const response = await axios.get(`/conversations/${conversationId}`);
    Object.assign(selectedConversation, response.data.conversation);

    messages.value = (selectedConversation.messages || []).map(message => ({
      ...message,
      content: message.role === 'assistant' ? md.render(message.content) : message.content
    }));

    form.model = selectedConversation.model;

    // Attendre un petit moment avant de configurer le WebSocket
    await new Promise(resolve => setTimeout(resolve, 200));
    await setupWebSocket(conversationId);
    scrollToBottom();
  } catch (error) {
    console.error("Erreur lors de la sélection de la conversation:", error);
    if (error.response?.status === 404 || error.response?.status === 500) {
      window.location.reload();
    }
  }
};

// Configuration du WebSocket optimisée
const setupWebSocket = async (conversationId) => {
  if (isConnecting.value) {
    await cleanupWebSocket(); // Force le nettoyage si déjà en cours de connexion
  }

  try {
    isConnecting.value = true;
    const channel = `chat.${conversationId}`;
    let currentText = '';

    // Force la déconnexion du canal précédent
    window.Echo.leave(channel);
    await new Promise(resolve => setTimeout(resolve, 100)); // Petit délai pour assurer la déconnexion

    channelSubscription.value = window.Echo.private(channel)
      .subscribed(() => {
        console.log("✅ Connecté au canal:", channel);
        isConnecting.value = false;
        reconnectAttempts.value = 0;
        attemptedConnections.value.add(conversationId);
      })
      .error(async (error) => {
        console.error("❌ Erreur WebSocket:", error);
        await cleanupWebSocket();

        // Réinitialiser la connexion complètement
        if (reconnectAttempts.value < maxReconnectAttempts) {
          reconnectAttempts.value++;
          const delay = Math.min(1000 * reconnectAttempts.value, 5000);
          setTimeout(() => {
            window.Echo.leave(channel);
            setupWebSocket(conversationId);
          }, delay);
        }
      })
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
            filteredConversations.value = filterConversations(searchQuery.value);
          }
          return;
        }

        const lastMessage = messages.value[messages.value.length - 1];
        if (lastMessage && lastMessage.role === 'assistant') {
          if (!event.isComplete) {
            // Accumuler le texte
            currentText += event.content;
          } else {
            // Message final
            currentText = event.content;
          }

          // Mettre à jour le contenu avec le rendu Markdown
          lastMessage.content = md.render(currentText);

          // Scroll automatique
          nextTick(() => {
            chatContainer.value?.scrollTo({
              top: chatContainer.value.scrollHeight,
              behavior: 'auto'
            });
          });

          if (event.isComplete) {
            isLoading.value = false;
            // Mise à jour de la conversation
            const conversation = conversations.value.find(c => c.id === activeConversationId.value);
            if (conversation?.messages) {
              const existingMessage = conversation.messages.find(m => m.content === '');
              if (existingMessage) {
                existingMessage.content = currentText;
              } else {
                conversation.messages.push({
                  role: 'assistant',
                  content: currentText
                });
              }
            }
          }
        }
      });
  } catch (error) {
    console.error("Erreur lors de la configuration du WebSocket:", error);
    isConnecting.value = false;
  }
};

// Ajouter un message
const addMessage = (role, content, image = null) => {
  const message = { role, content, image };
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
      temporary: true
    });

    const data = response.data;
    const newConversation = {
      ...data.conversation,
      messages: [],
      title: 'Nouvelle conversation'
    };

    conversations.value.unshift(newConversation);
    selectConversation(newConversation.id);

    // Envoyer le message seulement si on en a un message et qu'il n'est pas vide
    if (withMessage && form.message.trim()) {
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
  // Au lieu de chercher une conversation existante, on en crée une nouvelle
  createNewConversation(false);
  listenForNewConversations();
});

// Amélioration du nettoyage lors du démontage
onBeforeUnmount(async () => {
  await cleanupWebSocket();
  // Nettoyer toutes les connexions tentées
  attemptedConnections.value.forEach(id => {
    window.Echo.leave(`chat.${id}`);
  });
  attemptedConnections.value.clear();
});

// Envoyer un message
const sendMessage = async () => {
  const formData = new FormData();
  formData.append('message', form.message);
  formData.append('model', form.model);

  addMessage("user", form.message);
  addMessage("assistant", "");
  isLoading.value = true;

  try {
    await axios.post(`/conversations/${activeConversationId.value}/messages`, formData);
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

// Ajouter une computed property pour vérifier si la conversation est vide
const isEmptyConversation = computed(() => {
  return activeConversationId.value && messages.value.length === 0;
});

function adjustHeight(event) {
  const textarea = event.target;
  textarea.style.height = '40px'; // Hauteur par défaut.
  textarea.style.height = `${textarea.scrollHeight}px`; // Ajustement dynamique.
}

// Ajouter la fonction de déconnexion
const logout = () => {
  router.post('/logout', {}, {
    onSuccess: () => {
      router.visit('/login');
    }
  });
};

// Ajouter la fonction de suppression
const deleteConversation = async (conversationId, event) => {
  // Empêcher la propagation pour éviter la sélection de la conversation
  event.stopPropagation();

  if (!confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
    return;
  }

  try {
    await axios.delete(`/conversations/${conversationId}`);

    // Retirer la conversation de la liste
    conversations.value = conversations.value.filter(c => c.id !== conversationId);
    filteredConversations.value = filterConversations(searchQuery.value);

    // Si c'était la conversation active, réinitialiser
    if (activeConversationId.value === conversationId) {
      activeConversationId.value = null;
      messages.value = [];
    }
  } catch (error) {
    console.error('Erreur lors de la suppression:', error);
  }
};

</script>

<template>
  <div class="flex h-screen bg-gray-900 text-white">
    <!-- Menu mobile -->
    <div
      v-if="isMobileMenuOpen"
      class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden"
      @click="closeMobileMenu"
    ></div>

    <!-- Sidebar (mobile & desktop) -->
    <aside
      :class="[
        'fixed md:relative w-full md:w-60 bg-gray-800 h-screen border-r border-gray-700 flex flex-col z-50 transition-transform duration-300 ease-in-out',
        isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
      ]"
    >
      <!-- En-tête du menu -->
      <div class="flex items-center justify-between p-4 border-b border-gray-700">
        <h1 class="text-lg font-bold">AI Nexus</h1>
        <button
          class="md:hidden text-gray-400 hover:text-white"
          @click="closeMobileMenu"
        >
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

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
          class="w-full text-xs bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition"
        >
          AI Nexus
        </button>
      </div>

      <!-- Liste des conversations (modifiée pour prendre toute la hauteur en mobile) -->
      <ul class="flex-grow overflow-y-auto scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-800">
        <li
          v-for="conversation in filteredConversations"
          :key="conversation.id"
          :class="[ 'px-4 py-3 cursor-pointer hover:bg-gray-700 group', activeConversationId === conversation.id ? 'bg-gray-700' : '' ]"
          @click="selectConversation(conversation.id)"
        >
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-white text-sm text">{{ conversation.title || "Nouvelle conversation" }}</h3>
              <p class="text-xs text-gray-400">
                {{ new Date(conversation.updated_at).toLocaleString() }}
              </p>
            </div>
            <!-- Bouton de suppression -->
            <button
              @click="deleteConversation(conversation.id, $event)"
              class="text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </li>
      </ul>

      <!-- Menu du bas -->
      <div class="border-t border-gray-700 p-4">
        <!-- Boutons du menu du bas -->
        <button
          @click="logout"
          class="w-full flex items-center gap-2 text-gray-400 hover:text-white text-sm py-2"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span>Déconnexion</span>
        </button>
      </div>
    </aside>

    <!-- Chat -->
    <div class="flex flex-col flex-grow">
      <!-- Header mobile -->
      <header class="bg-gray-900 p-4 flex items-center justify-between md:hidden">
        <button
          @click="isMobileMenuOpen = true"
          class="text-gray-400 hover:text-white"
        >
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <div class="flex items-center gap-4">
          <select
            v-model="form.model"
            class="bg-gray-900 text-white text-xs rounded-lg px-2 py-1 border-none focus:outline-none focus:ring-0 focus:bg-gray-800"
          >
            <option v-for="model in models" :key="model.id" :value="model.id">
              {{ model.name }}
            </option>
          </select>
          <button
            @click="showInstructionsModal = true"
            class="text-gray-400 hover:text-white"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
        </div>
      </header>

      <!-- Header desktop -->
      <header class="bg-gray-900 p-4 hidden md:flex items-center justify-between">
        <select
          v-model="form.model"
          class="bg-gray-900 text-white text-sm rounded-lg px-2 py-1 border-none focus:outline-none focus:ring-0 focus:bg-gray-800"
        >
          <option v-for="model in models" :key="model.id" :value="model.id">
            {{ model.name }}
          </option>
        </select>
        <button
          @click="showInstructionsModal = true"
          class="text-gray-400 hover:text-white"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </button>
      </header>

      <!-- Zone principale conditionnelle -->
      <template v-if="!activeConversationId || isEmptyConversation">
        <div class="flex-grow flex flex-col items-center justify-center p-4">
          <h2 class="text-xl md:text-2xl font-light text-gray-300 mb-4 md:mb-8 text-center">
            Que puis-je faire pour vous ?
          </h2>
          <div class="w-full md:w-2/3 max-w-2xl px-4">
            <form @submit.prevent="submitForm" class="flex flex-col items-center">
              <div class="w-full bg-gray-700 rounded-3xl">
                <textarea
                  v-model="form.message"
                  placeholder="Posez votre question..."
                  class="bg-gray-700 text-white border-none pt-3 pl-3 pb-0 rounded-t-3xl w-full text-sm focus:outline-none focus:ring-0 resize-none scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-800 max-h-[200px] h-[40px]"
                  @input="adjustHeight($event)"
                  @keydown.enter.prevent="submitForm"
                ></textarea>
                <div class="bg-gray-700 text-white rounded-b-3xl flex justify-between items-center px-2 py-2">
                  <div></div><!-- Gardé pour maintenir l'espacement -->
                  <button
                    type="submit"
                    class="bg-blue-500 text-white w-8 h-8 hover:bg-blue-600 transition rounded-full flex items-center justify-center"
                    :disabled="isLoading"
                  >
                    <!-- Icône de chargement -->
                    <svg
                      v-if="isLoading"
                      class="animate-spin h-4 w-4"
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                    >
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
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      ></path>
                    </svg>

                    <!-- Icône de flèche -->
                    <svg
                      v-else
                      class="h-4 w-4"
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                      :stroke="'#1f2937'"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </template>

      <template v-else>
        <!-- Vue normale avec les messages -->
        <div ref="chatContainer" class="flex-grow overflow-y-auto p-3 md:p-6 flex flex-col items-center bg-gray-900 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-800">
          <ChatMessage
            v-for="(message, index) in messages"
            :key="index"
            :message="message"
          />
        </div>

        <!-- Zone de saisie normale -->
        <div class="p-3 md:p-6 pt-0 bg-gray-900 justify-center flex">
          <form @submit.prevent="submitForm" class="flex items-center space-x-4 w-full justify-center">
            <div class="w-full md:w-3/6 bg-gray-700 rounded-3xl">
              <textarea
                v-model="form.message"
                placeholder="Message AI Nexus..."
                class="bg-gray-700 text-white border-none pt-3 pl-3 pb-0 rounded-t-3xl w-full text-sm focus:outline-none focus:ring-0 resize-none scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-800 max-h-[200px] h-[40px]"
                @input="adjustHeight($event)"
                @keydown.enter.prevent="submitForm"
              ></textarea>
              <div class="bg-gray-700 text-white rounded-b-3xl flex justify-between items-center px-2 py-2">
                <div></div><!-- Gardé pour maintenir l'espacement -->
                <button
                  type="submit"
                  class="bg-blue-500 text-white w-8 h-8 hover:bg-blue-600 transition rounded-full flex items-center justify-center"
                  :disabled="isLoading"
                >
                  <!-- Icône de chargement -->
                  <svg
                    v-if="isLoading"
                    class="animate-spin h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
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
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                  </svg>

                  <!-- Icône de flèche -->
                  <svg
                    v-else
                    class="h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    :stroke="'#1f2937'"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M5 13l4 4L19 7"
                      />
                  </svg>
                </button>
              </div>
            </div>
          </form>
        </div>
      </template>
    </div>
  </div>

  <CustomInstructionsModal
    :is-open="showInstructionsModal"
    :conversation-id="activeConversationId"
    @close="showInstructionsModal = false"
    @update="selectConversation(activeConversationId)"
  />
</template>
