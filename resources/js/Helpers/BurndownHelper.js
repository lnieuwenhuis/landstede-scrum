import { ref } from "vue";
import Chart from 'chart.js/auto';

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

export const generateIdealBurndown = (totalPoints, totalDays, freeDates, startDate, endDate) => {
    if (totalDays <= 1) return [totalPoints, 0];
    
    // Convert start and end dates to Date objects
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    // Parse freeDates string into an actual array
    let freeDatesArray = [];
    try {
        freeDatesArray = typeof freeDates === 'string' ? JSON.parse(freeDates) : (Array.isArray(freeDates) ? freeDates : []);
    } catch (e) {
        console.error('Error parsing freeDates:', e);
    }
    
    // Convert freeDates to Date objects for easier comparison
    const freeDateObjects = freeDatesArray.map(date => {
        const d = new Date(date);
        d.setHours(0, 0, 0, 0);
        return d.getTime();
    });
    
    // Count actual working days
    let workingDays = 0;
    const idealData = [];
    
    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        date.setHours(0, 0, 0, 0);
        const isWorkingDay = !freeDateObjects.includes(date.getTime());
        
        // For the first day, always use the total points
        if (date.getTime() === start.getTime()) {
            idealData.push(totalPoints);
        } else if (isWorkingDay) {
            workingDays++;
            idealData.push(null);
        } else {
            idealData.push(null);
        }
    }
    
    // Calculate points to burn per working day
    const pointsPerWorkingDay = totalPoints / Math.max(1, workingDays);
    
    // Fill in the actual values
    let remainingPoints = totalPoints;
    for (let i = 1; i < idealData.length; i++) {
        const currentDate = new Date(start);
        currentDate.setDate(currentDate.getDate() + i);
        currentDate.setHours(0, 0, 0, 0);
        
        const isWorkingDay = !freeDateObjects.includes(currentDate.getTime());
        
        if (isWorkingDay) {
            remainingPoints -= pointsPerWorkingDay;
        }
        
        idealData[i] = Math.max(0, remainingPoints);
    }
    
    return idealData;
};

export const buildChart = (board, selectedSprint, columns, startDate, endDate, freeDates) => {
    if (!startDate || startDate === 'Invalid Date') startDate = board.start_date;
    if (!endDate || endDate === 'Invalid Date') endDate = board.end_date;
    
    // Parse freeDates string into an actual array
    let allFreeDates = [];
    try {
        allFreeDates = typeof freeDates === 'string' ? JSON.parse(freeDates) : (Array.isArray(freeDates) ? freeDates : []);
    } catch (e) {
        console.error('Error parsing freeDates:', e);
    }
    
    const dateLabels = generateDateLabels(startDate, endDate);
    // Recalculate total points every time to ensure it's up to date
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
        
        // Generate ideal burndown for this sprint from start to end points, accounting for free dates
        idealBurndownData = generateCustomIdealBurndown(
            expectedStartPoints, 
            expectedEndPoints, 
            dateLabels.length, 
            allFreeDates, 
            startDate, 
            endDate
        );
    } else {
        // For the entire board, use the total points to zero, accounting for free dates
        idealBurndownData = generateIdealBurndown(
            totalPoints, 
            dateLabels.length, 
            allFreeDates, 
            startDate, 
            endDate
        );
    }
    
    // Create chart title based on selected period
    let chartTitle = 'Burndown Chart: Entire Board';
    if (selectedSprint) {
        chartTitle = `Burndown Chart: ${selectedSprint.title}`;
    }
    
    // Create an array to track which dates are free days
    const freeDateIndices = [];
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    // Convert all free dates to timestamp format for easier comparison
    const freeDateTimestamps = allFreeDates.map(date => {
        const d = new Date(date);
        d.setHours(0, 0, 0, 0);
        return d.getTime();
    });
    
    // Identify which indices in our date range correspond to free dates
    let index = 0;
    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        date.setHours(0, 0, 0, 0);
        if (freeDateTimestamps.includes(date.getTime())) {
            freeDateIndices.push(index);
        }
        index++;
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
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        drawOnChartArea: true,
                        lineWidth: 1
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
                },
                tooltip: {
                    callbacks: {
                        title: (tooltipItems) => {
                            const index = tooltipItems[0].dataIndex;
                            const label = dateLabels[index];
                            return freeDateIndices.includes(index) ? 
                                `${label} (Non-working day)` : 
                                label;
                        }
                    }
                },
                nonWorkingDaysHighlighter: {
                    freeDateIndices: freeDateIndices,
                    fillColor: 'rgba(200, 200, 200, 0.6)'
                }
            }
        })
    };
};

// Helper function to generate ideal burndown with custom start and end points
export const generateCustomIdealBurndown = (startPoints, endPoints, totalDays, freeDates, startDate, endDate) => {
    if (totalDays <= 1) return [startPoints, endPoints];
    
    // Convert start and end dates to Date objects
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    // Convert freeDates to Date objects for easier comparison
    const freeDateObjects = (freeDates || []).map(date => {
        const d = new Date(date);
        d.setHours(0, 0, 0, 0);
        return d.getTime();
    });
    
    // Initialize the ideal data array with the start points
    const idealData = [startPoints];
    
    // Count actual working days (excluding the first day which we've already handled)
    let workingDays = 0;
    
    // Temporary array to track which days are working days
    const workingDayFlags = [];
    
    // First, identify all working days
    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        // Skip the first day as we've already added it
        if (date.getTime() === start.getTime()) {
            workingDayFlags.push(false); // Not counted as a working day for burndown
            continue;
        }
        
        date.setHours(0, 0, 0, 0);
        const isWorkingDay = !freeDateObjects.includes(date.getTime());
        workingDayFlags.push(isWorkingDay);
        
        if (isWorkingDay) {
            workingDays++;
        }
    }
    
    // Calculate points to burn per working day
    const pointsToBurn = startPoints - endPoints;
    const pointsPerWorkingDay = pointsToBurn / Math.max(1, workingDays);
    
    // Fill in the actual values
    let remainingPoints = startPoints;
    
    for (let i = 1; i < totalDays; i++) {
        if (workingDayFlags[i]) {
            remainingPoints -= pointsPerWorkingDay;
        }
        
        idealData.push(Math.max(endPoints, remainingPoints));
    }
    
    return idealData;
}


const nonWorkingDaysPlugin = {
    id: 'nonWorkingDaysHighlighter',
    beforeDraw: (chart, args, options) => {
        const { ctx, chartArea, scales } = chart;
        const { top, bottom, left, right } = chartArea;
        
        // First, ensure the entire chart area is white
        ctx.save();
        ctx.fillStyle = 'white';
        ctx.fillRect(left, top, right - left, bottom - top);
        
        // Then draw the darker grey only for non-working days
        if (!options.freeDateIndices || !options.freeDateIndices.length) {
            ctx.restore();
            return;
        }
        
        ctx.fillStyle = options.fillColor || 'rgba(200, 200, 200, 0.6)';
        
        options.freeDateIndices.forEach(index => {
            // Get the exact position of this data point
            const x = scales.x.getPixelForValue(index);
            
            // Calculate the width of a single column
            let columnWidth;
            if (index < scales.x.ticks.length - 1) {
                const nextX = scales.x.getPixelForValue(index + 1);
                columnWidth = nextX - x;
            } else if (index > 0) {
                const prevX = scales.x.getPixelForValue(index - 1);
                columnWidth = x - prevX;
            } else {
                columnWidth = chartArea.width / scales.x.ticks.length;
            }
            
            let startX;
            if (index === 0) {
                startX = left;
            } else {
                startX = Math.max(left, x - columnWidth);
            }
            
            const rectWidth = Math.min(columnWidth, right - startX);
            
            ctx.fillRect(startX, top, rectWidth, bottom - top);
        });
        
        ctx.restore();
    }
};

Chart.register(nonWorkingDaysPlugin);