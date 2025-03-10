import { ref } from "vue";

export const generateDateLabels = (startDate, endDate) => {
    const labels = [];
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        labels.push(date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric' 
        }));
    }
    return labels;
};

export const calculateTotalPoints = (columns) => {
    let total = 0;
    columns.value.forEach(column => {
        if (!column.cards) return;
        column.cards.forEach(card => {
            total += card.points || 0;
        });
    });
    return total;
};

export const generateBurndownData = (board, columns, startDate, endDate) => {
    const labels = generateDateLabels(startDate, endDate);
    const totalPoints = calculateTotalPoints(columns);
    const data = [];
    
    // Find the done column
    const doneColumn = columns.value.find(col => col.is_done_column === true);
    if (!doneColumn) {
        return new Array(labels.length).fill(totalPoints);
    }

    const start = new Date(startDate);
    const end = new Date(endDate);
    
    // For each day in the selected period
    for (let currentDate = new Date(start); currentDate <= end; currentDate.setDate(currentDate.getDate() + 1)) {
        currentDate.setHours(0, 0, 0, 0);
        let remainingPoints = totalPoints;

        // Check each card in done column
        doneColumn.cards.forEach(card => {
            // On the last day or later, count all cards in done column
            if (currentDate.getTime() >= end.getTime()) {
                remainingPoints -= card.points || 0;
            } 
            // Otherwise, only count cards completed before or on the current date
            else if (card.status_updated_at) {
                const cardDate = new Date(card.status_updated_at);
                cardDate.setHours(0, 0, 0, 0);
                
                if (cardDate <= currentDate) {
                    remainingPoints -= card.points || 0;
                }
            }
        });

        data.push(Math.max(0, remainingPoints));
    }
    
    return data;
};

export const generateIdealBurndown = (totalPoints, totalDays) => {
    if (totalDays <= 1) return [totalPoints, 0];
    
    const pointsPerDay = totalPoints / (totalDays - 1);
    return Array.from({ length: totalDays }, (_, i) => Math.max(0, totalPoints - (i * pointsPerDay)));
};

export const buildChart = (board, selectedSprint, columns, startDate, endDate) => {
    // Ensure we have valid dates
    if (!startDate || startDate === 'Invalid Date') startDate = board.start_date;
    if (!endDate || endDate === 'Invalid Date') endDate = board.end_date;
    
    const dateLabels = generateDateLabels(startDate, endDate);
    const totalPoints = calculateTotalPoints(columns);
    const actualBurndownData = generateBurndownData(board, columns, startDate, endDate);
    
    // Calculate ideal burndown based on sprint selection
    let idealBurndownData;
    if (selectedSprint) {
        // For a specific sprint, calculate the expected starting and ending points
        const boardStart = new Date(board.start_date);
        const boardEnd = new Date(board.end_date);
        const sprintStart = new Date(startDate);
        const sprintEnd = new Date(endDate);
        
        const totalBoardDays = (boardEnd - boardStart) / (1000 * 60 * 60 * 24);
        
        // Calculate what percentage of the project timeline has passed at sprint start
        const daysPassedAtStart = (sprintStart - boardStart) / (1000 * 60 * 60 * 24);
        const startProgressPercentage = Math.max(0, Math.min(1, daysPassedAtStart / totalBoardDays));
        
        // Calculate what percentage of the project timeline has passed at sprint end
        const daysPassedAtEnd = (sprintEnd - boardStart) / (1000 * 60 * 60 * 24);
        const endProgressPercentage = Math.max(0, Math.min(1, daysPassedAtEnd / totalBoardDays));
        
        // Calculate expected remaining points at sprint start and end
        const expectedStartPoints = totalPoints * (1 - startProgressPercentage);
        const expectedEndPoints = totalPoints * (1 - endProgressPercentage);
        
        // Generate ideal burndown for this sprint from start to end points
        idealBurndownData = generateCustomIdealBurndown(expectedStartPoints, expectedEndPoints, dateLabels.length);
    } else {
        // For the entire board, use the total points to zero
        idealBurndownData = generateIdealBurndown(totalPoints, dateLabels.length);
    }
    
    // Create chart title based on selected period
    let chartTitle = 'Burndown Chart: Entire Board';
    if (selectedSprint) {
        chartTitle = `Burndown Chart: ${selectedSprint.title}`;
    }
    
    return { 
        chartData: ref({
            labels: dateLabels,
            datasets: [{
                label: 'Remaining Points',
                data: actualBurndownData,
                borderColor: '#3b82f6',
                tension: 0,
                fill: false
            }, {
                label: 'Ideal Burndown',
                data: idealBurndownData,
                borderColor: '#dc2626',
                borderDash: [5, 5],
                tension: 0,
                fill: false
            }]
        }),
        chartOptions: ref({
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Story Points'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Days'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: chartTitle,
                    font: {
                        size: 16
                    }
                }
            }
        })
    };
};

// Helper function to generate ideal burndown with custom start and end points
export const generateCustomIdealBurndown = (startPoints, endPoints, totalDays) => {
    if (totalDays <= 1) return [startPoints, endPoints];
    
    const pointsPerDay = (startPoints - endPoints) / (totalDays - 1);
    return Array.from({ length: totalDays }, (_, i) => Math.max(0, startPoints - (i * pointsPerDay)));
};