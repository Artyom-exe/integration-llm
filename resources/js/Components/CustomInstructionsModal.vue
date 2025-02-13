<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    isOpen: Boolean,
    conversationId: Number,
});

const emit = defineEmits(['close', 'update']);
const instructions = ref([]);
const selectedInstruction = ref(null);

const instructionTypes = [
    { value: 'general', label: 'Instructions générales' },
    { value: 'tone', label: 'Ton des réponses' },
    { value: 'format', label: 'Format des réponses' },
    { value: 'command', label: 'Commandes personnalisées' }
];

const loadInstructions = async () => {
    try {
        const response = await axios.get('/custom-instructions');
        instructions.value = response.data;
    } catch (error) {
        console.error('Erreur lors du chargement des instructions:', error);
    }
};

const updateConversationInstruction = async () => {
    try {
        await axios.put(`/conversations/${props.conversationId}/instruction`, {
            custom_instruction_id: selectedInstruction.value
        });
        emit('update');
        emit('close');
    } catch (error) {
        console.error('Erreur lors de la mise à jour:', error);
    }
};

onMounted(() => {
    loadInstructions();
});

const newInstruction = ref({
    title: '',
    content: '',
    type: 'general',
    is_active: true,
    priority: 0
});

const isEditing = ref(false);
const currentTab = ref('list'); // 'list' ou 'form'

const saveInstruction = async () => {
    try {
        const endpoint = isEditing.value
            ? `/custom-instructions/${newInstruction.value.id}` // Utiliser l'ID de l'instruction
            : '/custom-instructions';

        const method = isEditing.value ? 'put' : 'post';

        const response = await axios[method](endpoint, {
            title: newInstruction.value.title,
            content: newInstruction.value.content,
            type: newInstruction.value.type,
            is_active: newInstruction.value.is_active,
            priority: newInstruction.value.priority
        });

        if (!isEditing.value) {
            instructions.value.push(response.data);
        } else {
            const index = instructions.value.findIndex(i => i.id === newInstruction.value.id);
            if (index !== -1) {
                instructions.value[index] = response.data;
            }
        }

        currentTab.value = 'list';
        resetForm();
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error.response?.data || error);
        alert('Erreur lors de la sauvegarde: ' + (error.response?.data?.message || error.message));
    }
};

const editInstruction = (instruction) => {
    newInstruction.value = { ...instruction }; // Copier toutes les propriétés, y compris l'ID
    isEditing.value = true;
    currentTab.value = 'form';
};

const resetForm = () => {
    newInstruction.value = {
        title: '',
        content: '',
        type: 'general',
        is_active: true,
        priority: 0
    };
    isEditing.value = false;
};

const deleteInstruction = async (id) => {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette instruction ?')) return;

    try {
        await axios.delete(`/custom-instructions/${id}`);
        instructions.value = instructions.value.filter(i => i.id !== id);
    } catch (error) {
        console.error('Erreur lors de la suppression:', error.response?.data || error);
        alert('Erreur lors de la suppression: ' + (error.response?.data?.message || error.message));
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-gray-800 p-6 rounded-lg w-[800px] max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl text-white">Instructions personnalisées</h2>
                <button @click="$emit('close')" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Onglets -->
            <div class="flex mb-4 border-b border-gray-700">
                <button
                    @click="currentTab = 'list'"
                    :class="['px-4 py-2', currentTab === 'list' ? 'text-blue-500 border-b-2 border-blue-500' : 'text-gray-400']"
                >
                    Liste des instructions
                </button>
                <button
                    @click="currentTab = 'form'; resetForm()"
                    :class="['px-4 py-2', currentTab === 'form' ? 'text-blue-500 border-b-2 border-blue-500' : 'text-gray-400']"
                >
                    {{ isEditing ? 'Modifier' : 'Nouvelle instruction' }}
                </button>
            </div>

            <!-- Liste des instructions -->
            <div v-if="currentTab === 'list'" class="space-y-4">
                <div v-for="instruction in instructions" :key="instruction.id"
                     class="bg-gray-700 p-4 rounded-lg">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-white font-medium">{{ instruction.title }}</h3>
                        <div class="flex space-x-2">
                            <button @click="editInstruction(instruction)"
                                    class="text-blue-400 hover:text-blue-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button @click="deleteInstruction(instruction.id)"
                                    class="text-red-400 hover:text-red-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="text-sm text-gray-300">{{ instruction.content }}</div>
                    <div class="mt-2 flex items-center text-sm text-gray-400">
                        <span class="mr-4">Type: {{ instruction.type }}</span>
                        <span>Priorité: {{ instruction.priority }}</span>
                    </div>
                </div>
            </div>

            <!-- Formulaire -->
            <div v-if="currentTab === 'form'" class="space-y-4">
                <div>
                    <label class="block text-white mb-2">Titre</label>
                    <input v-model="newInstruction.title"
                           type="text"
                           class="w-full bg-gray-700 text-white rounded-lg p-2">
                </div>

                <div>
                    <label class="block text-white mb-2">Type</label>
                    <select v-model="newInstruction.type"
                            class="w-full bg-gray-700 text-white rounded-lg p-2">
                        <option v-for="type in instructionTypes"
                                :key="type.value"
                                :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-white mb-2">Contenu</label>
                    <textarea v-model="newInstruction.content"
                              rows="6"
                              class="w-full bg-gray-700 text-white rounded-lg p-2"
                              placeholder="Écrivez votre instruction personnalisée ici..."></textarea>
                </div>

                <div class="flex items-center space-x-4">
                    <div>
                        <label class="block text-white mb-2">Priorité</label>
                        <input v-model="newInstruction.priority"
                               type="number"
                               class="w-20 bg-gray-700 text-white rounded-lg p-2">
                    </div>
                    <div class="flex items-center">
                        <input v-model="newInstruction.is_active"
                               type="checkbox"
                               class="mr-2">
                        <span class="text-white">Actif</span>
                    </div>
                </div>

                <div class="flex justify-end space-x-4 mt-6">
                    <button @click="currentTab = 'list'"
                            class="px-4 py-2 text-gray-400 hover:text-white">
                        Annuler
                    </button>
                    <button @click="saveInstruction"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        {{ isEditing ? 'Mettre à jour' : 'Créer' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
