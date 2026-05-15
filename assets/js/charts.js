/* ============================================================
   SPECS – Charts JavaScript
   File: assets/js/charts.js
   Used by: user/trends.php and admin/reports.php
   Requires: Chart.js (loaded via CDN in header)
   ============================================================ */

/* ── PRICE TREND LINE CHART ───────────────────────────────── */
function renderTrendChart(canvasId, historyData) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  const storeColors = [
    '#18382a', '#e9a820', '#52b788',
    '#2196F3', '#9c27b0', '#f4a261', '#e63946'
  ];

  const datasets = Object.entries(historyData).map(([storeId, points], i) => ({
    label           : points[0]?.store || `Store ${storeId}`,
    data            : points.map(p => ({ x: p.date, y: p.price })),
    borderColor     : storeColors[i % storeColors.length],
    backgroundColor : storeColors[i % storeColors.length] + '22',
    tension         : 0.4,
    fill            : false,
    pointRadius     : 4,
    pointHoverRadius: 7,
  }));

  return new Chart(canvas, {
    type: 'line',
    data: { datasets },
    options: {
      responsive : true,
      interaction: { intersect: false, mode: 'index' },
      scales: {
        x: {
          type : 'time',
          time : { unit: 'day', tooltipFormat: 'dd MMM yyyy' },
          title: { display: true, text: 'Date', color: '#7a7060' },
          grid : { color: '#e8e2d9' },
        },
        y: {
          title : { display: true, text: 'Price (UGX)', color: '#7a7060' },
          grid  : { color: '#e8e2d9' },
          ticks : { callback: v => 'UGX ' + v.toLocaleString() },
        }
      },
      plugins: {
        legend : { position: 'bottom', labels: { boxWidth: 12, font: { family: 'Nunito Sans' } } },
        tooltip: {
          callbacks: {
            label: ctx => `${ctx.dataset.label}: UGX ${ctx.parsed.y.toLocaleString()}`
          }
        }
      }
    }
  });
}

/* ── STORE BAR CHART ──────────────────────────────────────── */
function renderStoreBars(canvasId, storeData) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  const labels = storeData.map(s => s.store_name);
  const prices = storeData.map(s => s.price);
  const minP   = Math.min(...prices);

  const bgColors = prices.map(p =>
    p === minP ? '#2d6a4f' : '#52b788'
  );

  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label          : 'Price (UGX)',
        data           : prices,
        backgroundColor: bgColors,
        borderRadius   : 6,
        borderSkipped  : false,
      }]
    },
    options: {
      responsive: true,
      indexAxis : 'y',
      scales: {
        x: {
          ticks: { callback: v => 'UGX ' + v.toLocaleString() },
          grid : { color: '#e8e2d9' },
        },
        y: {
          grid: { display: false }
        }
      },
      plugins: {
        legend : { display: false },
        tooltip: {
          callbacks: {
            label: ctx => 'UGX ' + ctx.parsed.x.toLocaleString()
          }
        }
      }
    }
  });
}

/* ── PRICE CHANGES DOUGHNUT (admin dashboard) ─────────────── */
function renderChangesDonut(canvasId, increased, decreased, unchanged) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  return new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels  : ['Increased ▲', 'Decreased ▼', 'Unchanged'],
      datasets: [{
        data           : [increased, decreased, unchanged],
        backgroundColor: ['#e63946', '#2d6a4f', '#e8e2d9'],
        borderWidth    : 0,
        hoverOffset    : 6,
      }]
    },
    options: {
      responsive: true,
      cutout    : '70%',
      plugins: {
        legend: {
          position: 'bottom',
          labels  : { boxWidth: 12, font: { family: 'Nunito Sans' } }
        },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.label}: ${ctx.parsed} changes`
          }
        }
      }
    }
  });
}

/* ── SAVINGS BAR CHART (admin reports) ───────────────────── */
function renderSavingsChart(canvasId, savingsData) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels  : savingsData.map(s => s.name),
      datasets: [{
        label          : 'Max Saving (UGX)',
        data           : savingsData.map(s => s.saving),
        backgroundColor: '#e9a820',
        borderRadius   : 6,
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          ticks: { callback: v => 'UGX ' + v.toLocaleString() },
          grid : { color: '#e8e2d9' },
        },
        x: { grid: { display: false } }
      },
      plugins: {
        legend : { display: false },
        tooltip: {
          callbacks: {
            label: ctx => 'Save UGX ' + ctx.parsed.y.toLocaleString()
          }
        }
      }
    }
  });
}

/* ── PRICE HISTORY MINI SPARKLINE ─────────────────────────── */
function renderSparkline(canvasId, prices) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  const color = prices[prices.length - 1] <= prices[0] ? '#2d6a4f' : '#e63946';

  return new Chart(canvas, {
    type: 'line',
    data: {
      labels  : prices.map((_, i) => i),
      datasets: [{
        data           : prices,
        borderColor    : color,
        backgroundColor: color + '22',
        fill           : true,
        tension        : 0.4,
        pointRadius    : 0,
      }]
    },
    options: {
      responsive: true,
      scales    : { x: { display: false }, y: { display: false } },
      plugins   : { legend: { display: false }, tooltip: { enabled: false } },
      animation : { duration: 600 }
    }
  });
}

/* ── LOAD CHART.JS IF NOT ALREADY LOADED ──────────────────── */
function ensureChartJs(callback) {
  if (window.Chart) {
    callback();
    return;
  }
  const script = document.createElement('script');
  script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
  script.onload = callback;
  document.head.appendChild(script);
}
