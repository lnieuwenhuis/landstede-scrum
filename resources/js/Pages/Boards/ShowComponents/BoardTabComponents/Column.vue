<script setup>
import CardForm from '../CardForm.vue';
import ConfirmModal from '../ConfirmModal.vue';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import { 
    getInitials, 
    tryDeleteCard, 
    tryUpdateColumn, 
    tryDeleteColumn,
    tryAddCard
} from '../../ShowHelpers/BoardTabHelper';

const toast = useToast();
const loading = ref(false);

// Modal state
const showDeleteColumnModal = ref(false);
const showDeleteCardModal = ref(false);
const pendingDeleteColumnId = ref(null);
const pendingDeleteCardId = ref(null);

const props = defineProps({
    column: Object,
    users: Array,
    isDone: Boolean,
    isLocked: Boolean,
    isDragging: Boolean,
    columns: Array,
    cardEditing: [Number, null], 
});

const emit = defineEmits([
    'columns-updated',
    'drag-start',
    'drag-over',
    'drag-leave',
    'drop',
    'toggle-user-dropdown',
    'card-editing-changed'
]);

// Internal state
const newColumnTitle = ref('');
const columnEditing = ref(null);
const cardOpen = ref({});

// Column functions
const resetNewColumnForm = () => {
    newColumnTitle.value = '';
    columnEditing.value = null;
};

const toggleEditColumn = (column) => {
    columnEditing.value = column.id;
    newColumnTitle.value = column.title;
};

const handleUpdateColumn = async ({ id, title }) => {
    if (!title.trim()) {
        toast.error('Column title cannot be empty');
        return;
    }
    
    loading.value = true;
    try {
        const updatedColumn = await tryUpdateColumn({ id, title });
        
        if (updatedColumn) {
            const updatedColumns = props.columns.map(col => 
                col.id === id ? { ...col, title: updatedColumn.title } : col
            );
            emit('columns-updated', updatedColumns);
            resetNewColumnForm();
        }
    } catch (error) {
        toast.error('Failed to update column');
    } finally {
        loading.value = false;
    }
};

// Card functions
const toggleAddCard = () => {
    cardOpen.value = { ...cardOpen.value, [props.column.id]: !cardOpen.value[props.column.id] };
};

const handleAddCard = async ({ columnId, title, description, points }) => {
    loading.value = true;
    try {
        const updatedColumns = await tryAddCard({ 
            columnId, 
            title, 
            description, 
            points, 
            columns: props.columns 
        });
        
        if (updatedColumns) {
            emit('columns-updated', updatedColumns);
            cardOpen.value[columnId] = false;
        }
    } catch (error) {
        toast.error('Failed to add card');
    } finally {
        loading.value = false;
    }
};

const toggleEditCard = (card) => {
    emit('card-editing-changed', card.id);
};

const confirmDeleteColumn = (columnId) => {
    pendingDeleteColumnId.value = columnId;
    showDeleteColumnModal.value = true;
};

const confirmDeleteCard = (cardId) => {
    pendingDeleteCardId.value = cardId;
    showDeleteCardModal.value = true;
};

const handleDeleteColumn = async () => {
    const columnId = pendingDeleteColumnId.value;
    if (!columnId) return;
    
    loading.value = true;
    try {
        const success = await tryDeleteColumn(columnId);
        
        if (success) {
            const filteredColumns = props.columns.filter(column => column.id !== columnId);
            emit('columns-updated', filteredColumns);
        }
    } catch (error) {
        toast.error('Failed to delete column');
        console.error(error);
    } finally {
        loading.value = false;
        showDeleteColumnModal.value = false;
        pendingDeleteColumnId.value = null;
    }
};

const handleDeleteCard = async () => {
    const cardId = pendingDeleteCardId.value;
    if (!cardId) return;
    
    loading.value = true;
    try {
        const success = await tryDeleteCard(cardId, props.columns);
        
        if (success) {
            // Create a deep copy of the columns array to ensure reactivity
            const updatedColumns = JSON.parse(JSON.stringify(props.columns));
            
            // Find and remove the card from the appropriate column
            for (const column of updatedColumns) {
                const cardIndex = column.cards.findIndex(card => card.id === cardId);
                if (cardIndex !== -1) {
                    column.cards.splice(cardIndex, 1);
                    break;
                }
            }
            
            emit('columns-updated', updatedColumns);
        }
    } catch (error) {
        toast.error('Failed to delete card');
        console.error(error);
    } finally {
        loading.value = false;
        showDeleteCardModal.value = false;
        pendingDeleteCardId.value = null;
    }
};

// Drag and drop handlers
const handleDragStart = (event, cardId) => {
    emit('drag-start', event, cardId, props.column.id);
};

const handleDragOver = (event) => {
    emit('drag-over', event, props.column.id);
};

const handleDragLeave = () => {
    emit('drag-leave');
};

const handleDrop = (event) => {
    emit('drop', event, props.column.id);
};

const toggleUserDropdown = (cardId, event) => {
    emit('toggle-user-dropdown', cardId, event);
};
</script>

<template>
    <div 
        class="flex-shrink-0 w-72 rounded-lg shadow"
        :class="{ 
            'bg-gray-100': !isLocked && !isDone,
            'bg-gray-200': isLocked && !isDone,
            'bg-green-50': !isLocked && isDone,
            'bg-green-100': isLocked && isDone,
            'border-2 border-blue-400': isDragging 
        }"
        @dragover.prevent="handleDragOver"
        @dragleave="handleDragLeave"
        @drop.prevent="handleDrop"
    >
        <!-- Column header -->
        <div 
            class="p-3 rounded-t-lg flex justify-between items-center"
            :class="{ 'bg-gray-200': !isDone, 'bg-green-100': isDone }"
        >
            <div v-if="columnEditing === column.id" class="flex-1 relative">
                <input 
                    v-model="newColumnTitle" 
                    @keyup.enter="handleUpdateColumn({ id: column.id, title: newColumnTitle })"
                    :disabled="loading"
                    class="w-full px-2 py-1 border border-gray-300 rounded pr-8"
                    placeholder="Column title"
                />
                <span v-if="loading" class="absolute right-3 top-1/2 -translate-y-1/2">⏳</span>
            </div>
            <div v-else class="flex items-center justify-between w-full">
                <h3 class="font-medium" :class="{ 'text-gray-700': !isDone, 'text-green-800': isDone }">{{ column.title }}</h3>
                <span v-if="isLocked" class="flex items-center text-sm text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    <span>Locked</span>
                </span>
            </div>
            <div v-if="column.user_created !== 0 && !isLocked" class="flex space-x-1">
                <button 
                    @click="toggleEditColumn(column)"
                    class="p-1 text-gray-500 hover:text-gray-700 focus:outline-none"
                    :disabled="loading"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                    </svg>
                </button>
                <button
                    @click="confirmDeleteColumn(column.id)"
                    class="p-1 text-gray-500 hover:text-red-600 focus:outline-none"
                    :disabled="loading"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Cards container -->
        <div class="p-2 max-h-[calc(100vh-12rem)] overflow-y-auto">
            <!-- Cards -->
            <div 
                v-for="card in column.cards" 
                :key="card.id"
                class="bg-white p-3 rounded shadow-sm mb-2"
                :class="{ 'cursor-pointer': !isLocked }"
                :draggable="!isLocked"
                @dragstart="handleDragStart($event, card.id)"
            >
                <!-- Use the cardEditing prop directly -->
                <template v-if="cardEditing === card.id">
                    <slot name="card-edit-form" :card="card"></slot>
                </template>
                
                <template v-else>
                    <div class="flex justify-between items-start">
                        <h4 class="font-medium text-gray-800">{{ card.title }}</h4>
                        <div class="flex space-x-1" v-if="!isLocked">
                            <button 
                                @click="toggleEditCard(card)"
                                class="p-1 text-gray-500 hover:text-gray-700 focus:outline-none"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                </svg>
                            </button>
                            <button 
                                @click="confirmDeleteCard(card.id)"
                                class="p-1 text-gray-500 hover:text-red-600 focus:outline-none"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mt-1">{{ card.description }}</p>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs font-medium px-2 py-1 bg-blue-100 text-blue-800 rounded">{{ card.points }} points</span>
                        <!-- User avatar with dropdown -->
                        <div class="relative">
                            <div 
                                @click="toggleUserDropdown(card.id, $event)"
                                class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium cursor-pointer border-gray-400 border-2"
                            >
                                {{ getInitials(users.find(u => u.id === card.user_id)?.name || '') }}
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            
            <!-- Add card form -->
            <CardForm 
                v-if="cardOpen[column.id]"
                :loading="loading"
                :column-id="column.id"
                :key="`form-${column.id}-${Date.now()}`"
                @save="handleAddCard"
                @cancel="toggleAddCard"
            />
            
            <!-- Add card button -->
            <button 
                @click="toggleAddCard"
                class="w-full py-2 px-3 text-sm text-gray-600 hover:bg-gray-200 rounded flex items-center justify-center mt-2"
                :disabled="isLocked"
                :class="{ 'opacity-50 cursor-not-allowed': isLocked }"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Add Card
            </button>
            
            <ConfirmModal
                :show="showDeleteColumnModal"
                title="Delete Column"
                message="Are you sure you want to delete this column? All cards will be lost."
                confirm-text="Delete"
                @confirm="handleDeleteColumn"
                @cancel="showDeleteColumnModal = false"
            />
            
            <ConfirmModal
                :show="showDeleteCardModal"
                title="Delete Card"
                message="Are you sure you want to delete this card?"
                confirm-text="Delete"
                @confirm="handleDeleteCard"
                @cancel="showDeleteCardModal = false"
            />
        </div>
    </div>
</template>