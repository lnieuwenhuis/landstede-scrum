import { ref } from "vue";
import Chart from 'chart.js/auto';
import { useTranslations } from '@/translations';

export const generateDateLabels = (startDate, endDate) => {
    const labels = [];
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        const dayStr = date.toLocaleDateString('nl-NL', { weekday: 'short' });
        const dateStr = date.toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' });

        const parts = dateStr.split(' ');
        const formattedDateStr = `${parts[0]} ${parts[1].charAt(0).toUpperCase() + parts[1].slice(1)}`;
        labels.push(`${dayStr.charAt(0).toUpperCase() + dayStr.slice(1)} ${formattedDateStr}`);
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
    
    // Convert and normalize dates to midnight
    const start = new Date(startDate);
    start.setHours(0, 0, 0, 0);
    const end = new Date(endDate);
    end.setHours(0, 0, 0, 0);
    
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
    
    // First pass: count working days and initialize array
    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        date.setHours(0, 0, 0, 0);
        const isWorkingDay = !freeDateObjects.includes(date.getTime());
        if (isWorkingDay) workingDays++;
        idealData.push(null);
    }

    // Handle edge cases
    workingDays = Math.max(workingDays, 1);
    const pointsPerDay = parseFloat((totalPoints / (workingDays - 1)).toFixed(3));
    
    // Second pass: calculate values with zero-fill
    let dayCounter = 0;
    let zeroReached = false;
    
    for (let i = 0; i < idealData.length; i++) {
        const currentDate = new Date(start);
        currentDate.setDate(start.getDate() + i);
        currentDate.setHours(0, 0, 0, 0);
        
        const isWorkingDay = !freeDateObjects.includes(currentDate.getTime());
        
        if (zeroReached) {
            idealData[i] = 0;
            continue;
        }
        
        if (isWorkingDay) {
            const calculatedValue = parseFloat((totalPoints - (pointsPerDay * dayCounter)).toFixed(3));
            idealData[i] = Math.max(0, calculatedValue < 0.1 ? 0 : calculatedValue);
            dayCounter++;
            
            if (calculatedValue <= 0) {
                zeroReached = true;
            }
        } else {
            idealData[i] = idealData[i-1] || totalPoints;
        }
    }

    // Force exact zero at end and fill any remaining values after first zero
    let firstZeroIndex = idealData.findIndex(val => val <= 0);
    if (firstZeroIndex !== -1) {
        idealData.fill(0, firstZeroIndex);
    }
    idealData[idealData.length - 1] = 0;
    
    return idealData;
};

export const buildChart = (board, selectedSprint, columns, startDate, endDate, freeDates) => {
    const { __ } = useTranslations();
    
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
        // Parse sprints if they're in string format
        let sprintsArray = [];
        try {
            sprintsArray = typeof board.sprints === 'string' 
                ? JSON.parse(board.sprints) 
                : Array.isArray(board.sprints) 
                    ? board.sprints 
                    : [];
        } catch (e) {
            console.error('Error parsing sprints:', e);
            sprintsArray = [];
        }

        // Calculate working days across ALL sprints
        let totalWorkingDays = 0;
        const allFreeDatesSet = new Set(allFreeDates.map(d => new Date(d).setHours(0,0,0,0)));
        
        sprintsArray.forEach(sprint => {
            const sprintStart = new Date(sprint.start_date);
            const sprintEnd = new Date(sprint.end_date);
            for (let d = new Date(sprintStart); d <= sprintEnd; d.setDate(d.getDate() + 1)) {
                d.setHours(0,0,0,0);
                if (!allFreeDatesSet.has(d.getTime())) {
                    totalWorkingDays++;
                }
            }
        });

        // Calculate working days in THIS sprint
        let sprintWorkingDays = 0;
        const currentSprintStart = new Date(startDate);
        const currentSprintEnd = new Date(endDate);
        for (let d = new Date(currentSprintStart); d <= currentSprintEnd; d.setDate(d.getDate() + 1)) {
            d.setHours(0,0,0,0);
            if (!allFreeDatesSet.has(d.getTime())) {
                sprintWorkingDays++;
            }
        }

        // Calculate points allocation based on working day ratio
        const pointsAllocation = totalWorkingDays > 0 
            ? (sprintWorkingDays / totalWorkingDays) * totalPoints
            : totalPoints / (board.sprints?.length || 1);

        // Find sprint index
        const sprintIndex = sprintsArray.findIndex(s => s?.id === selectedSprint['id']) ?? 0;  
              
        // Calculate cumulative points burned from previous sprints
        const previousPoints = sprintsArray.slice(0, sprintIndex).reduce((sum, s) => {
            const sprintStart = new Date(s.start_date);
            const sprintEnd = new Date(s.end_date);
            let days = 0;
            for (let d = new Date(sprintStart); d <= sprintEnd; d.setDate(d.getDate() + 1)) {
                d.setHours(0,0,0,0);
                if (!allFreeDatesSet.has(d.getTime())) days++;
            }
            return sum + (days/totalWorkingDays * totalPoints);
        }, 0) || 0;

        const expectedStartPoints = totalPoints - previousPoints;
        const expectedEndPoints = Math.max(0, expectedStartPoints - pointsAllocation);

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
    let chartTitle = __('Burndown Chart: Entire Board');
    if (selectedSprint) {
        const chartPrefix = __('Burndown Chart:');
        chartTitle = `${chartPrefix} ${selectedSprint.title}`;
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
                label: __('Remaining Points'),
                data: actualBurndownData,
                borderColor: '#3b82f6',
                backgroundColor: '#3b82f6',
                tension: 0,
                fill: false,
                pointRadius: 0,
                borderWidth: 3
            }, {
                label: __('Ideal Burndown'),
                data: idealBurndownData,
                borderColor: '#dc2626',
                backgroundColor: '#dc2626',
                borderWidth: 3,
                tension: 0,
                fill: false,
                pointRadius: 0, 
                borderDash: [],
                opacity: 0.6, 
                borderColor: 'rgba(220, 38, 38, 0.6)' 
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
                        text: __('Story Points')
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: __('Days')
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
                            const nonWorkingDayText = __('Non-working day');
                            return freeDateIndices.includes(index) ? 
                                `${label} (${nonWorkingDayText})` : 
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
    let zeroReached = false;
    
    for (let i = 1; i < totalDays; i++) {
        if (zeroReached) {
            idealData.push(0);
            continue;
        }
        
        if (workingDayFlags[i]) {
            remainingPoints -= pointsPerWorkingDay;
        }
        
        idealData.push(Math.max(endPoints, remainingPoints < 0.1 ? 0 : remainingPoints));

        // Once we hit 0, mark remaining days
        if (idealData[i] <= 0) {
            zeroReached = true;
        }
    }

    // Force exact end value and fill trailing zeros
    for (let i = 0; i < idealData.length; i++) {
        if (idealData[i] <= 0) {
            // Set all subsequent values to 0
            idealData.fill(0, i);
            break;
        }
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