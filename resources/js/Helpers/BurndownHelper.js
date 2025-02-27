import { ref } from "vue";

export const generateDateLabels = (board) => {
    const labels = [];
    const start = new Date(board.start_date);
    const end = new Date(board.end_date);
    
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
        column.cards.forEach(card => {
            total += card.points;
        });
    });
    return total;
};

export const generateBurndownData = (board, columns) => {
    const labels = generateDateLabels(board);
    const totalPoints = calculateTotalPoints(columns);
    const data = [];
    
    // Find the done column
    const doneColumn = columns.value.find(col => col.is_done_column === true);
    if (!doneColumn) {
        return new Array(labels.length).fill(totalPoints);
    }

    const start = new Date(board.start_date);
    const end = new Date(board.end_date);
    
    // For each day in the sprint
    for (let currentDate = new Date(start); currentDate <= end; currentDate.setDate(currentDate.getDate() + 1)) {
        currentDate.setHours(0, 0, 0, 0);
        let remainingPoints = totalPoints;

        // Check each card in done column
        doneColumn.cards.forEach(card => {
            // On the last day or later, count all cards in done column
            if (currentDate.getTime() >= end.getTime()) {
                remainingPoints -= card.points;
            } 
            // Otherwise, only count cards completed before or on the current date
            else if (card.status_updated_at) {
                const cardDate = new Date(card.status_updated_at);
                cardDate.setHours(0, 0, 0, 0);
                
                if (cardDate <= currentDate) {
                    remainingPoints -= card.points;
                }
            }
        });

        data.push(Math.max(0, remainingPoints));
    }
    
    return data;
};

export const buildChart = (board, columns) => {
    return { 
        chartData: ref({
            labels: generateDateLabels(board),
            datasets: [{
                label: 'Remaining Points',
                data: generateBurndownData(board, columns),
                borderColor: '#3b82f6',
                tension: 0.1,
                fill: false
            }, {
                label: 'Ideal Burndown',
                data: (() => {
                    const totalDays = generateDateLabels(board).length;
                    const totalPoints = calculateTotalPoints(columns);
                    const pointsPerDay = totalPoints / (totalDays - 1);
                    return Array.from({ length: totalDays }, (_, i) => Math.max(0, totalPoints - (i * pointsPerDay)));
                })(),
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
                        text: 'Sprint Days'
                    }
                }
            }
        })
    };
}