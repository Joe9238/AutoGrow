<script setup lang="ts">
import { defineProps, computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale);

interface SensorReading {
  moisture: number;
  temperature: number;
  recorded_at: string;
}

const props = defineProps<{ readings: SensorReading[], yellowThreshold?: number, redThreshold?: number }>();


function formatDate(dateStr: string) {
  const date = new Date(dateStr);
  // Format to dates like 14 Apr 15:30
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}

const chartData = computed(() => {
  // Reverse readings so most recent is on the right
  const reversed = [...props.readings].reverse();
  const labels = reversed.map(r => formatDate(r.recorded_at));
  const datasets = [
    {
      label: 'Moisture (%)',
      data: reversed.map(r => r.moisture),
      borderColor: 'rgba(59, 130, 246, 1)',
      backgroundColor: 'rgba(59, 130, 246, 0.2)',
      tension: 0.3,
    },
    {
      label: 'Temperature (°C)',
      data: reversed.map(r => r.temperature),
      borderColor: 'rgba(234, 88, 12, 1)',
      backgroundColor: 'rgba(234, 88, 12, 0.2)',
      tension: 0.3,
    },
  ];
  // Add horizontal threshold lines 
  if (typeof props.yellowThreshold === 'number') {
    datasets.push({
      label: 'Yellow Threshold',
      data: Array(labels.length).fill(props.yellowThreshold),
      borderColor: 'rgba(251, 191, 36, 1)', // yellow-400
      borderDash: [8, 4],
      pointRadius: 0,
      fill: false,
      borderWidth: 2,
      order: 0,
    });
  }
  if (typeof props.redThreshold === 'number') {
    datasets.push({
      label: 'Red Threshold',
      data: Array(labels.length).fill(props.redThreshold),
      borderColor: 'rgba(239, 68, 68, 1)', // red-500
      borderDash: [4, 4],
      pointRadius: 0,
      fill: false,
      borderWidth: 2,
      order: 0,
    });
  }
  return {
    labels,
    datasets,
  };
});

const chartOptions = {
  responsive: true,
  plugins: {
    legend: {
      position: 'top' as const,
    },
    title: {
      display: true,
      text: 'Sensor Readings (Last 100)',
    },
  },
  scales: {
    x: {
      title: {
        display: true,
        text: 'Recorded At',
      },
      ticks: {
        maxTicksLimit: 10,
        autoSkip: true,
      },
    },
    y: {
      title: {
        display: true,
        text: 'Value',
      },
      min: 0,
      max: 100,
      ticks: {
        stepSize: 5,
      },
    },
  },
};
</script>

<template>
  <div class="rounded-lg border-2 border-dashed border-gray-300 p-4 mt-4">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>
