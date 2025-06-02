<script setup>
import CardForm from './CardForm.vue';
import Column from './BoardTabComponents/Column.vue';
import ConfirmModal from './ConfirmModal.vue';
import { ref, computed, onMounted, nextTick } from 'vue';
import { useToast } from 'vue-toastification';
import { 
    getInitials,
    tryAssignUserToCard, 
    tryMoveCard, 
    tryAddCard, 
    tryUpdateCard, 
    tryDeleteCard, 
    tryAddColumn,
    isValidHexColor,
    isLightColor
} from '../ShowHelpers/BoardTabHelper';
import { groupBy } from 'lodash-es';

const toast = useToast();

const props = defineProps({
    columns: Array,
    board: Object,
    users: Array,
    showDescription: {
        type: Boolean,
        default: false
    },
    currentSprint: Object,
    categories: Array,
});

const userDropdownOpen = ref(null);
const userDropdownPosition = ref({ top: '0px', left: '0px' });

// User assignment modal
const toggleUserDropdown = (cardId, event) => {
    // Check if the sprint is locked
    if (props.currentSprint && (props.currentSprint.status === 'locked' || props.currentSprint.status === 'checked')) {
        return;
    }
    
    if (userDropdownOpen.value === cardId) {
        userDropdownOpen.value = null;
        return;
    }
    
    userDropdownOpen.value = cardId;
    
    nextTick(() => {
        const avatarElement = event.target.closest('.relative');
        if (avatarElement) {
            const rect = avatarElement.getBoundingClientRect();
            const dropdownHeight = 240;
            const dropdownWidth = 256;
            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;
            
            const spaceBelow = viewportHeight - rect.bottom;
            
            // Vertical positioning
            if (spaceBelow < dropdownHeight) {
                userDropdownPosition.value = {
                    top: `${rect.top + window.scrollY - 160}px`,
                };
            } else {
                userDropdownPosition.value = {
                    top: `${rect.bottom + window.scrollY + 5}px`,
                };
            }
            
            // Horizontal positioning
            if (rect.right + dropdownWidth > viewportWidth) {
                userDropdownPosition.value.right = `${window.innerWidth - rect.right - window.scrollX}px`;
                userDropdownPosition.value.left = 'auto';
            } else {
                userDropdownPosition.value.left = `${rect.left + window.scrollX}px`;
                userDropdownPosition.value.right = 'auto';
            }
        }
    });
};

// assign user to card
const assignUserToCard = async (cardId, userId) => {
    if (props.currentSprint && (props.currentSprint.status === 'locked' || props.currentSprint.status === 'checked')) {
        toast.warning("Cannot assign users while sprint is locked");
        userDropdownOpen.value = null;
        return;
    }

    loading.value = true;
    try {
        const updatedColumns = await tryAssignUserToCard(cardId, userId, props);
        
        emit('columns-updated', updatedColumns);
        toast.success(userId ? 'User assigned successfully' : 'User unassigned successfully');
    } catch (error) {
        toast.error('Failed to assign user to card');
    } finally {
        loading.value = false;
        userDropdownOpen.value = null;
    }
};

const showUserFilter = ref(false);
const userFilterPosition = ref({ right: 'auto' });

const toggleUserFilter = (event) => {
    showUserFilter.value = !showUserFilter.value;
    
    // Calculate position when opening the dropdown
    if (showUserFilter.value) {
        nextTick(() => {
            const buttonElement = event.target.closest('button');
            if (buttonElement) {
                const rect = buttonElement.getBoundingClientRect();
                const dropdownWidth = 200;
                const viewportWidth = window.innerWidth;
                
                if (rect.right + dropdownWidth > viewportWidth) {
                    userFilterPosition.value = { right: '0px', left: 'auto' };
                } else {
                    userFilterPosition.value = { right: 'auto', left: '0px' };
                }
            }
        });
    }
    
    // Prevent interference with user assignment modal
    if (userDropdownOpen.value) {
        userDropdownOpen.value = null;
    }
};

onMounted(() => {
    document.addEventListener('click', (e) => {
        // Close user assignment modal
        if (userDropdownOpen.value && !e.target.closest('.user-dropdown') && !e.target.closest('.user-avatar')) {
            userDropdownOpen.value = null;
        }
        
        // Close user filter dropdown
        if (showUserFilter.value && !e.target.closest('.relative')) {
            showUserFilter.value = false;
        }
    });
});

const isColumnLocked = computed(() => {
    return (columnTitle) => {
        if (!props.currentSprint || !props.currentSprint.status) {
            return false;
        }
        
        const status = props.currentSprint.status;
        
        if (status === 'planning') {
            return columnTitle !== 'Project Backlog' && columnTitle !== 'Sprint Backlog';
        } else if (status === 'locked' || status === 'checked') {
            return true;
        }
        
        return false;
    };
});


const emit = defineEmits([
    'columns-updated',
    'toggle-description',
    'burndown-update'
]);

const selectedUserId = ref(null);

// Toggle description visibility
const toggleDescription = () => {
    emit('toggle-description');
};

// Local state management
const cardOpen = ref({});
const cardTitle = ref('');
const cardDesc = ref('');
const cardPoints = ref(0);
const cardEditing = ref(null);
const newColumnTitle = ref('');
const showNewColumn = ref(false);
const currentDragCardId = ref(null);
const currentDragColumnId = ref(null);
const currentDragSourceColumnId = ref(null);
const loading = ref(false);

// Card drag and drop handlers
const handleDragStart = (event, cardId, columnId) => {
    // Find the source column directly from props.columns
    const sourceColumn = props.columns.find(col => col.id === columnId);
    // Check the status property directly on the column object
    if (!sourceColumn || sourceColumn.status === 'locked') { 
        toast.warning('Cannot move cards from a locked column.'); // Optional: Add feedback
        event.preventDefault();
        return;
    }
    
    currentDragCardId.value = cardId;
    currentDragSourceColumnId.value = columnId;
    event.dataTransfer.effectAllowed = 'move';
};

const handleDragOver = (event, columnId) => {
    // Check if the target column is locked before allowing drop indication
    const targetColumn = props.columns.find(col => col.id === columnId);
    if (targetColumn && targetColumn.status === 'locked') {
        event.dataTransfer.dropEffect = 'none';
    } else {
        event.dataTransfer.dropEffect = 'move';
        event.preventDefault();
        currentDragColumnId.value = columnId;
    }
};

const handleDragLeave = () => {
    currentDragColumnId.value = null;
};

const handleDrop = async (event, targetColumnId) => {
    event.preventDefault();
    
    // Find the target column directly from props.columns
    const targetColumn = props.columns.find(col => col.id === targetColumnId);
    // Check the status property directly on the column object
    if (!targetColumn || targetColumn.status === 'locked') { 
        toast.error('Cannot move cards to a locked column.');
        currentDragCardId.value = null;
        currentDragSourceColumnId.value = null;
        currentDragColumnId.value = null;
        return;
    }
    
    if (currentDragCardId.value && currentDragSourceColumnId.value && targetColumnId && currentDragSourceColumnId.value !== targetColumnId) {
        loading.value = true;
        try {
            const updatedColumns = await tryMoveCard({
                cardId: currentDragCardId.value,
                sourceColumnId: currentDragSourceColumnId.value,
                targetColumnId: targetColumnId,
                columns: props.columns
            });
            
            // Emit the updated columns array received from the helper
            emit('columns-updated', updatedColumns); 
            emit('burndown-update');
            
        } catch (error) {
            console.error("Error moving card:", error);
            toast.error('Failed to move card.'); 
        } finally {
            loading.value = false;
            // Reset drag state regardless of success/failure
            currentDragCardId.value = null;
            currentDragSourceColumnId.value = null;
            currentDragColumnId.value = null;
        }
    } else {
         // Reset drag state if dropped on the same column or invalid drop
         currentDragCardId.value = null;
         currentDragSourceColumnId.value = null;
         currentDragColumnId.value = null;
    }
};

// Card form handlers
const toggleAddCard = (columnId) => {
    cardOpen.value = { ...cardOpen.value, [columnId]: !cardOpen.value[columnId] };
    resetForm();
};

const toggleEditCard = (cardId) => {
    if (cardEditing.value === cardId) {
        cardEditing.value = null;
        resetForm();
        return;
    }
    
    let foundCard = null;
    for (const column of props.columns) {
        const card = column.cards.find(c => c.id === cardId);
        if (card) {
            foundCard = card;
            break;
        }
    }
    
    if (foundCard) {
        cardEditing.value = foundCard.id;
        cardTitle.value = foundCard.title;
        cardDesc.value = foundCard.description;
        cardPoints.value = foundCard.points;
    }
};

const resetForm = () => {
    cardTitle.value = '';
    cardDesc.value = '';
    cardPoints.value = 0;
    cardEditing.value = null;
};

// Column form handlers
const toggleNewColumn = () => {
    showNewColumn.value = !showNewColumn.value;
    resetNewColumnForm();
};

const resetNewColumnForm = () => {
    newColumnTitle.value = '';
};

// Card movement handler
const handleMoveCard = async ({ cardId, sourceColumnId, targetColumnId }) => {
    const updatedColumns = await tryMoveCard({ 
        cardId, 
        sourceColumnId, 
        targetColumnId, 
        columns: props.columns,
        onSuccess: () => emit('burndown-update')
    });
    
    if (updatedColumns) {
        emit('columns-updated', updatedColumns);
    }
};

// API handlers
const handleUpdateCard = async ({ cardId, title, description, points, categoryId }) => {
    loading.value = true;
    try {
        const updatedColumns = await tryUpdateCard({ 
            cardId, 
            title, 
            description, 
            points, 
            categoryId,
            columns: props.columns 
        });
        
        if (updatedColumns) {
            emit('columns-updated', updatedColumns);
        }
    } catch (error) {
        // Error is already handled in the helper
    } finally {
        loading.value = false;
        cardEditing.value = null;
        resetForm();
    }
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
        // Error is already handled in the helper
    } finally {
        loading.value = false;
        resetForm();
    }
};

const pendingDeleteCardId = ref(null);
const showDeleteCardModal = ref(false);

// Helper function to create swimlanes structure
const createSwimlanes = (cards, users) => {
    const grouped = groupBy(cards, 'user_id');
    
    const swimlanes = [];
    
    // Add Unassigned lane first if it exists
    if (grouped['null'] || grouped['undefined']) {
        swimlanes.push({
            userId: null,
            userName: 'Unassigned',
            userColor: null,
            cards: grouped['null'] || grouped['undefined'] || []
        });
    }
    
    // Add lanes for assigned users, ensuring order matches props.users
    users.forEach(user => {
        if (grouped[user.id]) {
            swimlanes.push({
                userId: user.id,
                userName: user.name,
                userColor: user.color,
                cards: grouped[user.id]
            });
        }
    });
    
    return swimlanes;
};

// Modify regularColumns and doneColumn computed properties
const regularColumns = computed(() => {
    const filteredColumns = props.columns.filter(column => !column.is_done_column);
    
    return filteredColumns.map(column => {
        // Apply user filter *before* grouping if a user is selected
        const cardsToGroup = selectedUserId.value
            ? column.cards.filter(card => card.user_id === selectedUserId.value || card.user_id === null) // Show selected user + unassigned
            : column.cards;
            
        const swimlanes = createSwimlanes(cardsToGroup, props.users);
        
        // If filtering, only keep relevant swimlanes
        const finalSwimlanes = selectedUserId.value
            ? swimlanes.filter(lane => lane.userId === selectedUserId.value || lane.userId === null)
            : swimlanes;

        return {
            ...column,
            swimlanes: finalSwimlanes,
        };
    });
});

const doneColumn = computed(() => {
    const found = props.columns.find(column => column.is_done_column);
    if (!found) return null;

    // Apply user filter *before* grouping if a user is selected
    const cardsToGroup = selectedUserId.value
        ? found.cards.filter(card => card.user_id === selectedUserId.value || card.user_id === null) // Show selected user + unassigned
        : found.cards;

    const swimlanes = createSwimlanes(cardsToGroup, props.users);

    // If filtering, only keep relevant swimlanes
    const finalSwimlanes = selectedUserId.value
        ? swimlanes.filter(lane => lane.userId === selectedUserId.value || lane.userId === null)
        : swimlanes;
        
    return {
        ...found,
        swimlanes: finalSwimlanes,
    };
});

const handleDeleteCard = async () => {
    const cardId = pendingDeleteCardId.value;
    if (!cardId) return;
    
    loading.value = true;
    try {
        const updatedColumns = await tryDeleteCard(cardId, props.columns);
        
        if (updatedColumns) {
            emit('columns-updated', updatedColumns);
            toast.success('Card deleted successfully');
        }
    } catch (error) {
        toast.error('Failed to delete card');
    } finally {
        loading.value = false;
        showDeleteCardModal.value = false;
        pendingDeleteCardId.value = null;
    }
};

const handleAddColumn = async ({ title, done, board_id, status }) => {
    loading.value = true;
    try {
        const newColumn = await tryAddColumn({ title, done, board_id, status });
        
        if (newColumn) {
            const updatedColumns = [...props.columns, newColumn];
            emit('columns-updated', updatedColumns);
            resetNewColumnForm();
        }
    } catch (error) {
        // Error is already handled in the helper
    } finally {
        loading.value = false;
        showNewColumn.value = false; 
    }
};

const isUserAssigned = (cardId, userId) => {
    for (const column of props.columns) {
        const card = column.cards.find(c => c.id === cardId);
        if (card) {
            return card.user_id === userId;
        }
    }
    return false;
};
const showConfirmModal = ref(false);
const confirmModalMessage = ref('');
const confirmModalAction = ref(null);

const showConfirmation = (message, action) => {
    confirmModalMessage.value = message;
    confirmModalAction.value = action;
    showConfirmModal.value = true;
};

const handleConfirm = () => {
    if (confirmModalAction.value) {
        confirmModalAction.value();
    }
    showConfirmModal.value = false;
};
// Add touch-specific state variables
const touchDragCardId = ref(null);
const touchDragSourceColumnId = ref(null);
const touchDragColumnId = ref(null);

// Touch event handlers
const handleTouchDragStart = (cardId, columnId) => {
    // Find the source column directly from props.columns
    const sourceColumn = props.columns.find(col => col.id === columnId);
    // Check the status property directly on the column object
    if (!sourceColumn || sourceColumn.status === 'locked') { 
        toast.warning('Cannot move cards from a locked column.');
        return;
    }
    
    touchDragCardId.value = cardId;
    touchDragSourceColumnId.value = columnId;
};

const handleTouchDragOver = (columnId) => {
    // Check if the target column is locked before allowing drop indication
    const targetColumn = props.columns.find(col => col.id === columnId);
    if (targetColumn && targetColumn.status === 'locked') {
        return;
    }
    
    touchDragColumnId.value = columnId;
};

const handleTouchDragEnd = () => {
    touchDragColumnId.value = null;
};

const handleTouchDrop = async (targetColumnId) => {
    // Use the same column finding logic as in handleDrop
    const targetColumn = props.columns.find(col => col.id === parseInt(targetColumnId) || col.id === targetColumnId);
    
    if (!targetColumn) {
        toast.error('Target column not found');
        touchDragCardId.value = null;
        touchDragSourceColumnId.value = null;
        touchDragColumnId.value = null;
        return;
    }
    
    // Check if the column is locked
    if (targetColumn.status === 'locked') {
        toast.error('Cannot move cards to a locked column');
        touchDragCardId.value = null;
        touchDragSourceColumnId.value = null;
        touchDragColumnId.value = null;
        return;
    }
    
    if (touchDragCardId.value && touchDragSourceColumnId.value && targetColumnId && touchDragSourceColumnId.value !== targetColumnId) {
        loading.value = true;
        try {
            const updatedColumns = await tryMoveCard({
                cardId: touchDragCardId.value,
                sourceColumnId: touchDragSourceColumnId.value,
                targetColumnId: targetColumnId,
                columns: props.columns
            });
            
            // Emit the updated columns array received from the helper
            emit('columns-updated', updatedColumns); 
            emit('burndown-update');
            
        } catch (error) {
            console.error("Error moving card:", error);
            toast.error('Failed to move card.'); 
        } finally {
            loading.value = false;
            // Reset drag state regardless of success/failure
            touchDragCardId.value = null;
            touchDragSourceColumnId.value = null;
            touchDragColumnId.value = null;
        }
    } else {
        // Reset drag state if dropped on the same column or invalid drop
        touchDragCardId.value = null;
        touchDragSourceColumnId.value = null;
        touchDragColumnId.value = null;
    }
};
</script>

<template>
    <div class="flex justify-center">
        <div class="bg-white p-6 rounded-lg shadow w-full">
            <!-- Board header - restructured -->
            <div class="flex flex-col space-y-4">
                <!-- Title row with board title and sprint info -->
                <div class="flex justify-between items-center">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-semibold text-gray-800 truncate">{{ board.title }}</h1>
                    </div>
                    <div v-if="currentSprint" class="text-gray-600 truncate flex-shrink-0 flex items-center">
                        <span>({{ currentSprint.title }})</span>
                        <button 
                            @click="toggleDescription" 
                            class="text-gray-500 hover:text-gray-700 focus:outline-none flex-shrink-0 ml-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 15 15" fill="currentColor">
                                <path v-if="!showDescription" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                <path v-else fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Board description (shown/hidden based on showDescription) -->
                <div v-if="showDescription" class="rounded-md text-gray-700 text-sm">
                    {{ board.description || 'No description available.' }}
                </div>
                
                <!-- User filter row -->
                <div class="flex items-center">
                    <div class="relative min-w-[200px] mb-3">
                        <button 
                            @click.stop="toggleUserFilter"
                            class="flex items-center justify-between w-full px-2 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors min-w-[200px]"
                        >
                            <div class="flex items-center space-x-2 flex-1 min-w-0">
                                <div v-if="selectedUserId" class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium flex-shrink-0">
                                    {{ getInitials(users.find(u => u.id === selectedUserId)?.name || '') }}
                                </div>
                                <span class="truncate">{{ selectedUserId ? users.find(u => u.id === selectedUserId)?.name : 'All Users' }}</span>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 flex-shrink-0 ml-3 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path v-if="!showUserFilter" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                <path v-else fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <div 
                            v-if="showUserFilter"
                            class="absolute z-50 mt-1 min-w-[200px] max-w-[325px] bg-white rounded-md shadow-lg border border-gray-200 overflow-hidden"
                            :style="userFilterPosition"
                        >
                            <div class="py-1 max-h-60 overflow-auto">
                                <div 
                                    @click="selectedUserId = null; showUserFilter = false"
                                    class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer flex items-center space-x-2"
                                    :class="{ 'bg-blue-50': !selectedUserId }"
                                >
                                    <span class="w-6"></span>
                                    <span class="truncate">All Users</span>
                                </div>
                                <div 
                                    v-for="user in users" 
                                    :key="user.id" 
                                    @click="selectedUserId = user.id; showUserFilter = false"
                                    class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer flex items-center space-x-2"
                                    :class="{ 'bg-blue-50': selectedUserId === user.id }"
                                >
                                    <div class="w-6 h-5 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium flex-shrink-0">
                                        {{ getInitials(user.name) }}
                                    </div>
                                    <span class="truncate">{{ user.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columns container -->
            <div class="flex justify-center">
                <div 
                    class="flex space-x-4 overflow-x-auto pb-4 w-full"
                >
                    <!-- Regular columns are rendered first using v-for -->
                    <Column 
                        v-for="column in regularColumns" 
                        :key="column.id"
                        :column="column"
                        :users="props.users"
                        :is-done="false"
                        :is-locked="isColumnLocked(column.title)"
                        :is-dragging="currentDragColumnId === column.id"
                        :card-open="cardOpen"
                        :columns="props.columns"
                        :cardEditing="cardEditing"
                        :categories="props.categories"
                        @add-card-toggle="toggleAddCard"
                        @card-editing-changed="toggleEditCard"
                        @delete-card="handleDeleteCard"
                        @drag-start="handleDragStart"
                        @drag-over="handleDragOver"
                        @drag-leave="handleDragLeave"
                        @drop="handleDrop"
                        @touch-drag-start="handleTouchDragStart"
                        @touch-drag-over="handleTouchDragOver"
                        @touch-drag-end="handleTouchDragEnd"
                        @touch-drop="handleTouchDrop"
                        @toggle-user-dropdown="toggleUserDropdown"
                        @columns-updated="emit('columns-updated', $event)"
                    >
                        <template #card-edit-form="{ card }">
                            <CardForm 
                                :loading="loading"
                                :column-id="card.column_id"
                                :card="card"
                                :initial-title="cardTitle"
                                :initial-description="cardDesc"
                                :initial-points="cardPoints"
                                :initial-category-id="card.category_id"
                                :is-editing="true"
                                :categories="props.categories"
                                @save="(data) => handleUpdateCard({
                                    cardId: card.id,
                                    columnId: card.column_id,
                                    title: data.title,
                                    description: data.description,
                                    points: data.points,
                                    categoryId: data.categoryId,
                                })"
                                @cancel="resetForm"
                            />
                        </template>
                        <template #add-card-form>
                            <CardForm 
                                :loading="loading"
                                :column-id="column.id"
                                :key="`form-${column.id}-${Date.now()}`"
                                :categories="props.categories"
                                @save="handleAddCard"
                                @cancel="toggleAddCard(column.id)"
                            />
                        </template>
                    </Column>
                    
                    <!-- Done column is rendered *after* the regular columns -->
                    <Column 
                        v-if="doneColumn"
                        :key="doneColumn.id"
                        :column="doneColumn"
                        :users="props.users"
                        :is-done="true"
                        :is-locked="isColumnLocked(doneColumn.title)"
                        :is-dragging="currentDragColumnId === doneColumn.id"
                        :card-open="cardOpen"
                        :columns="props.columns"
                        :cardEditing="cardEditing"
                        @add-card-toggle="toggleAddCard"
                        @card-editing-changed="toggleEditCard"
                        @delete-card="handleDeleteCard"
                        @drag-start="handleDragStart"
                        @drag-over="handleDragOver"
                        @drag-leave="handleDragLeave"
                        @drop="handleDrop"
                        @touch-drag-start="handleTouchDragStart"
                        @touch-drag-over="handleTouchDragOver"
                        @touch-drag-end="handleTouchDragEnd"
                        @touch-drop="handleTouchDrop"
                        @toggle-user-dropdown="toggleUserDropdown"
                    >
                        <template #card-edit-form="{ card }">
                            <CardForm 
                                :loading="loading"
                                :column-id="card.column_id"
                                :initial-title="cardTitle"
                                :initial-description="cardDesc"
                                :initial-points="cardPoints"
                                :is-editing="true"
                                @save="(data) => handleUpdateCard({
                                    cardId: card.id,
                                    columnId: card.column_id,
                                    title: data.title,
                                    description: data.description,
                                    points: data.points,
                                    categoryId: data.categoryId,
                                })"
                                @cancel="resetForm"
                            />
                        </template>
                        <template #add-card-form>
                            <CardForm 
                                :loading="loading"
                                :column-id="doneColumn.id"
                                :key="`form-${doneColumn.id}-${Date.now()}`"
                                @save="handleAddCard"
                                @cancel="toggleAddCard(doneColumn.id)"
                            />
                        </template>
                    </Column>
                    
                    <!-- Add column form/button is rendered last -->
                    <div v-if="showNewColumn" class="flex-shrink-0 w-72 bg-white p-3 rounded-lg shadow">
                        <h3 class="font-medium text-gray-700 mb-2">Add New Column</h3>
                        <div class="relative">
                            <input 
                                v-model="newColumnTitle" 
                                class="w-full px-3 py-2 border border-gray-300 rounded mb-2 pr-8"
                                placeholder="Column title"
                                :disabled="loading"
                            />
                            <span v-if="loading" class="absolute right-3 top-3">⏳</span>
                        </div>
                        <div class="flex items-center mb-3">
                            <input 
                                id="done-column" 
                                type="checkbox" 
                                v-model="newColumnDone" 
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                :disabled="loading"
                            />
                            <label for="done-column" class="ml-2 block text-sm text-gray-900">
                                This is a "Done" column
                            </label>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button 
                                @click="toggleNewColumn" 
                                class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded"
                                :disabled="loading"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="handleAddColumn({
                                    title: newColumnTitle,
                                    done: newColumnDone,
                                    board_id: board.id,
                                    status: board.status
                                })"
                                class="px-3 py-1 text-sm bg-blue-600 text-white hover:bg-blue-700 rounded relative"
                                :disabled="loading"
                            >
                                <span v-if="loading">⏳</span>
                                {{ loading ? 'Adding...' : 'Add Column' }}
                            </button>
                        </div>
                    </div>
                    
                    <!-- Add column button -->
                    <button 
                        v-else
                        @click="toggleNewColumn"
                        class="flex-shrink-0 w-72 bg-gray-100 rounded-lg shadow-sm border-2 border-dashed border-gray-300 flex items-center justify-center p-6 hover:bg-gray-200"
                    >
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <span class="mt-2 block text-sm font-medium text-gray-600">Add Column</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User assignment modal -->
    <div v-if="userDropdownOpen && (!props.currentSprint || (props.currentSprint.status !== 'locked' && props.currentSprint.status !== 'checked'))" class="absolute inset-0 z-50">
        <div 
            class="bg-white rounded-md shadow-lg w-64 absolute overflow-hidden user-dropdown"
            :style="userDropdownPosition"
        >
            <div class="py-1">
                <div 
                    v-for="user in props.users" 
                    :key="user.id"
                    class="flex items-center px-4 py-2 hover:bg-gray-100 cursor-pointer"
                    @click="assignUserToCard(userDropdownOpen, user.id)"
                >
                    <div 
                        class="h-8 w-8 rounded-full flex items-center justify-center font-bold text-sm mr-2"
                        :style="{ 
                            backgroundColor: isValidHexColor(user.color) ? user.color : '#3b82f6',
                            color: isLightColor(user.color) ? '#000000' : '#ffffff'
                        }"
                    >
                        {{ getInitials(user.name) }}
                    </div>
                    <span>{{ user.name }}</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add at the end of the template -->
    <ConfirmModal
        :show="showDeleteCardModal"
        title="Delete Card"
        message="Are you sure you want to delete this card?"
        confirm-text="Delete"
        @confirm="handleDeleteCard"
        @cancel="showDeleteCardModal = false"
    />
</template>