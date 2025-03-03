import axios from 'axios';

export const addUser = async (groupId, userString) => {
    if (!userString.trim()) {
        console.error('Email or username is required');
        return null;
    }

    try {
        const response = await axios.get(`/api/addUserToGroup/${groupId}/${userString}`);
        
        if (response.data.error) {
            console.error(response.data.error);
            return response.data;
        }

        return response.data;
    } catch (e) {
        console.error('Failed to add user', e);
        return null;
    }
};

export async function removeUser(groupId, userId) {
    try {
        const response = await axios.delete(`/api/groups/${groupId}/users/${userId}`);
        return response.data;
    } catch (error) {
        console.error('Error removing user:', error);
        return false;
    }
}