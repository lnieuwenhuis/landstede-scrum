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
} from '../../ShowHelpers/BoardTabHelper';

const toast = useToast();
const loading = ref(false);

// Modal state
const showDeleteColumnModal = ref(false);
const showDeleteCardModal = ref(false);
const pendingDeleteColumnId = ref(null);
const pendingDeleteCardId = ref(null);

const props = defineProps({
    column: Object,// Expects column object with 'swimlanes' array and 'status' ('locked' or 'active')
    columns: Array, 
    users: Array,
    isDone: Boolean, // Keep this if it's separate from column.status
    // isLocked: Boolean, // Remove this prop if status is the source of truth
    isDragging: Boolean, // Keep this for drag state
    cardEditing: [Number, null], 
    cardOpen: Object, 
});

const emit = defineEmits([
    'columns-updated', // Keep this if Column modifies columns directly (e.g., title edit)
    'drag-start',
    'drag-over',
    'drag-leave',
    'drop',
    'toggle-user-dropdown',
    'card-editing-changed',
    // Remove 'delete-card' emit, handle update via 'columns-updated'
    'add-card-toggle', 
]);

// Internal state
const newColumnTitle = ref('');
const columnEditing = ref(null);
// Remove cardOpen ref - controlled by parent prop now
// const cardOpen = ref({}); 

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
// Remove toggleAddCard - handled by parent via emit
// const toggleAddCard = () => { ... };

// Remove handleAddCard - handled by parent via slot event
// const handleAddCard = async ({ columnId, title, description, points }) => { ... };

const toggleEditCard = (card) => {
    // Emit the card ID to BoardTab to handle which card is being edited
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

// Modify handleDeleteCard to rely on tryDeleteCard returning updated columns
const handleDeleteCard = async () => {
    const cardId = pendingDeleteCardId.value;
    if (!cardId) return;
    
    loading.value = true;
    try {
        // Assume tryDeleteCard now returns the updated columns array on success
        // or throws an error on failure.
        const updatedColumns = await tryDeleteCard(cardId, props.columns); // Pass columns if needed by helper
        
        // Emit the updated columns array received from the helper
        emit('columns-updated', updatedColumns);

    } catch (error) {
        // Error toast is likely handled within tryDeleteCard or BoardTab catches it
        console.error("Error deleting card:", error);
        // Optionally add a toast here if the helper doesn't show one on error
        // toast.error('Failed to delete card on frontend.'); 
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

// Remove the console.log
// console.log("hmm", props.column.status)
</script>

<template>
    <div 
        class="flex flex-col flex-shrink-0 w-72 bg-gray-100 p-3 rounded-lg shadow max-h-[82vh]" 
        :class="{ 
            'border-2 border-blue-500': isDragging, 
            'bg-green-50': isDone,
            'opacity-70': column.status === 'locked'
        }"
        @dragover.prevent="handleDragOver"
        @dragleave.prevent="handleDragLeave"
        @drop.prevent="handleDrop"
    >
        <!-- Column Header (remains fixed at the top) -->
        <div class="flex justify-between items-center mb-3 flex-shrink-0">
            <h3 v-if="columnEditing !== column.id" class="font-medium text-gray-700">{{ column.title }}</h3>
            <!-- Column Edit Form -->
            <form v-if="columnEditing === column.id" @submit.prevent="handleUpdateColumn({ id: column.id, title: newColumnTitle })" class="flex-grow mr-2">
                 <input 
                    v-model="newColumnTitle" 
                    class="w-full px-2 py-1 border border-gray-300 rounded"
                    :disabled="loading"
                />
            </form>
            <!-- Column Actions: Shown only if NOT done, NOT backlog, and NOT locked -->
             <div v-if="!props.column.is_done_column && column.title !== 'Project Backlog' && column.title !== 'Sprint Backlog' && props.column.status !== 'locked'" class="flex space-x-1 flex-shrink-0">
                 <!-- Edit Button -->
                 <button v-if="columnEditing !== column.id" @click="toggleEditColumn(column)" class="p-1 text-gray-400 hover:text-gray-600 rounded hover:bg-gray-200" title="Edit column">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                     </svg>
                 </button>
                 <!-- Save Button -->
                 <button v-if="columnEditing === column.id" @click="handleUpdateColumn({ id: column.id, title: newColumnTitle })" class="p-1 text-green-500 hover:text-green-700 rounded hover:bg-green-100" :disabled="loading || !newColumnTitle.trim()" title="Save">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                     </svg>
                 </button>
                 <!-- Cancel Button -->
                 <button v-if="columnEditing === column.id" @click="resetNewColumnForm" class="p-1 text-red-500 hover:text-red-700 rounded hover:bg-red-100" :disabled="loading" title="Cancel">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                     </svg>
                 </button>
                 <!-- Delete Button -->
                 <button v-if="columnEditing !== column.id" @click="confirmDeleteColumn(column.id)" class="p-1 text-gray-400 hover:text-red-500 rounded hover:bg-red-100" title="Delete column">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                     </svg>
                 </button>
             </div>
             <!-- Lock Icon: Shown only if locked -->
             <div v-if="props.column.status === 'locked'" class="flex-shrink-0 ml-2" title="Column is locked">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                     <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                 </svg>
             </div>
        </div>

        <!-- Scrollable Swimlanes Container -->
        <div class="flex-grow overflow-y-auto space-y-3 pr-1 min-h-[50px]"> 
            <!-- Placeholder: Show if there are no swimlanes or no cards in any swimlane -->
            <div v-if="!column.swimlanes || column.swimlanes.every(lane => !lane.cards || lane.cards.length === 0)" class="text-center text-gray-500 text-sm py-4">
                No cards
            </div>
            
            <!-- Swimlanes loop: Render only if there are swimlanes with cards -->
            <template v-else>
                <!-- Use v-for on the <template> tag -->
                <template v-for="swimlane in column.swimlanes" :key="swimlane.userId || 'unassigned'">
                    <!-- Apply v-if to the inner div, ensuring swimlane is defined -->
                    <div 
                        v-if="swimlane && swimlane.cards && swimlane.cards.length > 0" 
                        class="swimlane border-t pt-2 first:border-t-0 first:pt-0"
                    >
                        <!-- Swimlane Header -->
                        <!-- The outer v-if ensures swimlane and cards exist, this condition might need review based on filtering logic -->
                        <div v-if="(swimlane.cards && swimlane.cards.length > 0) || !selectedUserId" class="flex items-center space-x-2 mb-2 px-1 text-sm font-medium text-gray-600">
                             <div v-if="swimlane.userId" class="w-6 h-5 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-medium flex-shrink-0">
                                 {{ getInitials(swimlane.userName) }}
                             </div>
                             <!-- Show 'Unassigned' header -->
                             <span v-else class="text-gray-500 italic">Unassigned</span> 
                             <span class="truncate">{{ swimlane.userId ? swimlane.userName : '' }}</span>
                             <span class="text-gray-400 text-xs">({{ swimlane.cards.length }})</span>
                        </div>

                        <!-- Cards within Swimlane -->
                        <div class="space-y-2"> 
                             <div 
                                v-for="card in swimlane.cards" 
                                :key="card.id"
                                :draggable="props.column.status !== 'locked'"
                                @dragstart="handleDragStart($event, card.id)"
                                class="bg-white p-3 rounded shadow-sm cursor-grab active:cursor-grabbing border border-gray-200"
                                :class="{ 'opacity-75': cardEditing === card.id }"
                            >
                        <!-- Card Edit Form Slot -->
                         <div v-if="cardEditing === card.id">
                             <slot name="card-edit-form" :card="card"></slot>
                         </div>
                         <!-- Card Display -->
                         <div v-else>
                             <div class="flex justify-between items-start mb-1"> <!-- Added margin-bottom -->
                                 <span class="font-medium text-gray-800 break-words">{{ card.title }}</span> <!-- Added break-words -->
                                 <!-- Card Actions (Edit/Delete) -->
                                 <div v-if="!isLocked" class="flex space-x-1 flex-shrink-0 ml-2"> <!-- Added margin-left -->
                                     <!-- Edit Card Button -->
                                     <button @click="toggleEditCard(card)" class="p-0.5 text-xs text-gray-400 hover:text-blue-500 rounded hover:bg-gray-100" title="Edit card">
                                         <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                         </svg>
                                     </button>
                                     <!-- Delete Card Button -->
                                     <button @click="confirmDeleteCard(card.id)" class="p-0.5 text-xs text-gray-400 hover:text-red-500 rounded hover:bg-red-100" title="Delete card">
                                         <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                         </svg>
                                     </button>
                                 </div>
                             </div>
                             <p v-if="card.description" class="text-sm text-gray-600 mt-1 break-words">{{ card.description }}</p> <!-- Added break-words -->
                             <div class="flex justify-between items-center mt-2">
                                 <span class="text-xs text-gray-500">Points: {{ card.points || 0 }}</span>
                                 <!-- User Avatar/Assignment -->
                                 <div class="relative user-assign-trigger-class"> <!-- Added trigger class -->
                                     <button 
                                        @click.stop="toggleUserDropdown(card.id, $event)" 
                                        class="w-7 h-6 rounded-full flex items-center justify-center text-white font-medium text-xs border-2 border-gray-300"
                                        :class="card.user_id ? 'bg-blue-500 hover:bg-blue-600' : 'bg-gray-400 hover:bg-gray-500'"
                                        :title="users.find(u => u.id === card.user_id)?.name || 'Assign user'"
                                    >
                                         {{ card.user_id ? getInitials(users.find(u => u.id === card.user_id)?.name || '?') : '+' }}
                                     </button>
                                 </div>
                             </div>
                         </div>
                    </div>
                </div>
                </div>
            </template>
            </template>
        </div>

        <!-- Add Card Button/Form (remains fixed at the bottom) -->
        <div class="mt-3 flex-shrink-0">
            <!-- Use parent's cardOpen state -->
            <div v-if="cardOpen && cardOpen[column.id]"> 
                 <!-- Pass columnId to the slot scope -->
                 <slot name="add-card-form" :column-id="column.id"></slot> 
            </div>
             <button 
                v-else-if="props.column.status !== 'locked'" 
                @click="$emit('add-card-toggle', column.id)"
                class="w-full text-left px-3 py-2 text-sm text-gray-500 hover:bg-gray-200 rounded transition-colors" 
            >
                + Add a card
            </button>
        </div>

        <!-- Modals -->
         <ConfirmModal
            :show="showDeleteColumnModal"
            title="Delete Column"
            message="Are you sure you want to delete this column and all its cards?"
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
</template>