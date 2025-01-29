import axios from 'axios';

export const addUser = async (groupId, emailValue) => {
    if (!emailValue.trim()) {
        console.error('Email is required');
        return null;
    }

    try {
        const response = await axios.get(`/api/addUserToGroup/${groupId}/${emailValue}`);
        
        return response.data.user;
    } catch (e) {
        console.error('Failed to add user', e);
        return null;
    }
};

export const removeUser = async (groupId, userId) => {
    try {
        const response = await axios.get(`/api/removeUserFromGroup/${groupId}/${userId}`);
        
        return response.data.user;
    } catch (e) {
        console.error('Failed to remove user', e);
        return null;
    }
};