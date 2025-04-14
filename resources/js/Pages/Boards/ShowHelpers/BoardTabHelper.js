import axios from 'axios';
import { useToast } from 'vue-toastification';
const toast = useToast();

export function getInitials(name) {
    if (!name || typeof name !== 'string') {
        return '?'; // Return a placeholder if name is invalid
    }

    let nameToProcess = name.trim();

  // Check if the name starts with "Student " and remove it if it does
    if (nameToProcess.startsWith('Student ')) {
        nameToProcess = nameToProcess.substring('Student '.length).trim();
    }

    // Remove potential parentheses around names, e.g., "(firstname)" -> "firstname"
    // This regex replaces leading '(' and trailing ')' from words
    nameToProcess = nameToProcess.replace(/\b\(/g, '').replace(/\)\b/g, '');

    // Split the remaining name into parts based on spaces
    const nameParts = nameToProcess.split(' ').filter((part) => part.length > 0); // Filter out empty strings from multiple spaces

    if (nameParts.length === 0) {
        // If nothing is left, maybe fallback to the first char of the original name?
        return name.trim()[0]?.toUpperCase() || '?';
    } else if (nameParts.length === 1) {
        // If only one part (e.g., "Admin", or just "Firstname"), take its first letter
        return nameParts[0][0].toUpperCase();
    } else {
        // If two or more parts, take the first letter of the first part
        // and the first letter of the *last* part (to handle middle names)
        const firstInitial = nameParts[0][0];
        const lastInitial = nameParts[nameParts.length - 1][0];
        return (firstInitial + lastInitial).toUpperCase();
    }
}

export async function tryAssignUserToCard(cardId, userId, props) {
    const response = await axios.post(`/api/cards/${cardId}/assign`, {
        user_id: userId
    });
    
    if (response.data.success) {
        // Create a deep copy of columns with the updated user assignment
        const updatedColumns = props.columns.map(column => {
            const updatedCards = column.cards.map(card => {
                if (card.id === cardId) {
                    return { ...card, user_id: userId };
                }
                return card;
            });
            return { ...column, cards: updatedCards };
        });
        
        // Return the updated columns
        return updatedColumns;
    } else {
        throw new Error('Failed to assign user');
    }
}

export async function tryMoveCard({ cardId, sourceColumnId, targetColumnId, columns, onSuccess }) {
    // Check if columns array exists
    if (!columns || !Array.isArray(columns)) {
        toast.error('Columns data is not available');
        return null;
    }
    
    // Find source and target columns with null checks
    const sourceColumn = columns.find(col => col && col.id == sourceColumnId);
    const targetColumn = columns.find(col => col && col.id == targetColumnId);
    
    // Validate columns exist
    if (!sourceColumn || !targetColumn) {
        toast.error('Source or target column not found');
        return null;
    }
    
    // Ensure cards arrays exist
    if (!Array.isArray(sourceColumn.cards) || !Array.isArray(targetColumn.cards)) {
        toast.error('Cards data is not available');
        return null;
    }
    
    // Find card in source column
    const cardIndex = sourceColumn.cards.findIndex(c => c && c.id == cardId);
    if (cardIndex === -1) {
        toast.error('Card not found in source column');
        return null;
    }

    // Create a copy of the card to move
    const movedCard = { ...sourceColumn.cards[cardIndex] };
    
    try {
        // Remove from source
        sourceColumn.cards.splice(cardIndex, 1);
        
        // Add to target
        targetColumn.cards.push(movedCard);
        
        // Make API call after optimistic update
        await axios.post(`/api/cards/${cardId}/move`, {
            column_id: targetColumnId
        });
        
        toast.success('Card moved successfully');
        
        if (onSuccess) {
            onSuccess();
        }
        
        return columns;
    } catch (error) {
        // Revert changes if API call fails
        if (Array.isArray(sourceColumn.cards) && Array.isArray(targetColumn.cards)) {
            sourceColumn.cards.splice(cardIndex, 0, movedCard);
            const targetCardIndex = targetColumn.cards.findIndex(c => c && c.id == cardId);
            if (targetCardIndex !== -1) {
                targetColumn.cards.splice(targetCardIndex, 1);
            }
        }
        
        toast.error('Failed to move card');
        return columns;
    }
}

export async function tryAddCard({ columnId, title, description, points, columns }) {
    try {
        const response = await axios.post(`/api/addCardToColumn/${columnId}`, {
            title, description, points
        });

        if (response.data.card) {
            const columnIndex = columns.findIndex(col => col.id === columnId);
            if (columnIndex > -1) {
                const updatedColumns = [...columns];
                updatedColumns[columnIndex].cards.push(response.data.card);
                toast.success('Card added successfully');
                return updatedColumns;
            }
        } else {
            throw new Error(response.data.error);
        }
        return null;
    } catch (error) {
        toast.error('Failed to add card');
        throw error;
    }
}

export async function tryUpdateCard({ cardId, title, description, points, columns }) {
    try {
        const response = await axios.post(`/api/updateCard/${cardId}`, {
            title,
            description,
            points
        });

        if (!response.data.card) {
            throw new Error(response.data.error);
        }
        
        // Update from server response
        const updatedColumns = [...columns];
        updatedColumns.forEach(column => {
            const cardIndex = column.cards.findIndex(c => c.id === cardId);
            if (cardIndex !== -1) {
                column.cards[cardIndex] = response.data.card;
            }
        });

        toast.success('Card updated successfully');
        return updatedColumns;
    } catch (error) {
        console.log(error);
        toast.error('Failed to update card');
        throw error;
    }
}

export async function tryDeleteCard(cardId, columns) {
    try {
        const response = await axios.post(`/api/deleteCard/${cardId}`);
        if (response.data.message) {
            // Create a deep copy of columns with the card removed
            const updatedColumns = columns.map(column => ({
                ...column,
                cards: column.cards.filter(card => card.id !== cardId)
            }));
            
            toast.success(response.data.message);
            return updatedColumns;
        } else {
            throw new Error(response.data.error); 
        }
    } catch (error) {
        toast.error('Failed to delete card');
        throw error;
    }
}

export async function tryAddColumn({ title, done, board_id, status }) {
    try {
        if (!done) done = false;
        const response = await axios.post('/api/addColumn', {
            title, done, board_id, status
        });

        if (response.data.column) {
            toast.success('Column added successfully!');
            return response.data.column;
        } else {
            throw new Error(response.data.error);
        }
    } catch (error) {
        toast.error('Failed to add column');
        throw error;
    }
}

export async function tryUpdateColumn({ id, title }) {
    try {
        const response = await axios.post(`/api/updateColumn`, {
            column_id: id,
            title
        });
        
        if (response.data.column) {
            toast.success('Column updated successfully');
            return response.data.column;
        } else {
            throw new Error(response.data.error);
        }
    } catch (error) {
        toast.error('Failed to update column');
        throw error;
    }
}

export async function tryDeleteColumn(columnId) {
    try {
        const response = await axios.post(`/api/deleteColumn`, {
            column_id: columnId
        });

        if (!response.data.message) {
            throw new Error('Failed to delete column');
        }
        
        toast.success(response.data.message);
        return true;
    } catch (error) {
        toast.error('Failed to delete column');
        throw error;
    }
}