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
            return null;
        }

        return response.data.user;
    } catch (e) {
        console.error('Failed to add user', e);
        return null;
    }
};

export const removeUser = async (groupId, userId) => {
    try {
        const response = await axios.get(`/api/removeUserFromGroup/${groupId}/${userId}`);
        
        if (response.data.error) {
            console.error(response.data.error);
            return null;
        }

        return response.data.user;
    } catch (e) {
        console.error('Failed to remove user', e);
        return null;
    }
};