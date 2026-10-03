/**
 * Admin reports — ApexCharts + Apache ECharts
 */
(function () {
  'use strict';

  var d = window.__RAPID_CHARTS__;
  if (!d) return;

  var NAVY = '#0A0C10';
  var BLUE = '#1F5BFF';
  var BLUE_SOFT = '#6E9BFF';
  var NAVY_MID = '#1A3A6B';
  var MUTED = '#5A6170';
  // Ten distinct hues so every ticket status gets its own colour (no repeats in the status donut).
  var PALETTE = [BLUE, NAVY, '#0891B2', '#E3A008', '#7C3AED', '#059669', BLUE_SOFT, '#DC2626', '#94A3B8', '#B45309'];
  var FONT = 'Manrope, system-ui, sans-serif';
  var apexInstances = [];
  var echartInstances = [];

  /* ----- Print: switch to an A4-width layout, redraw charts at that width, then print ----- */
  function redrawAll() {
    var pending = apexInstances.map(function (c) {
      // redrawPaths=true re-measures the parent, so charts fit the current layout width
      return c.updateOptions({ chart: { animations: { enabled: false } } }, true, false);
    });
    echartInstances.forEach(function (c) { c.resize(); });
    return Promise.all(pending);
  }

  function setPrintLayout(on) {
    if (document.body.classList.contains('reports-print') === on) return Promise.resolve();
    document.body.classList.toggle('reports-print', on);
    void document.body.offsetWidth; // apply the layout before measuring
    return redrawAll();
  }

  var printBtn = document.getElementById('reportsPrint');
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      setPrintLayout(true).then(function () {
        window.requestAnimationFrame(function () { window.print(); });
      });
    });
  }
  // Ctrl+P / browser menu: best effort, the layout switch and redraw run synchronously enough for print preview.
  window.addEventListener('beforeprint', function () { setPrintLayout(true); });
  window.addEventListener('afterprint', function () { setPrintLayout(false); });

  function hasSeries(pack) {
    return pack && Array.isArray(pack.labels) && pack.labels.length > 0
      && Array.isArray(pack.data) && pack.data.some(function (n) { return Number(n) > 0; });
  }

  function setEmpty(id, text) {
    var el = document.getElementById(id);
    if (!el) return null;
    if (text) {
      el.innerHTML = '<div class="chart-empty">' + text + '</div>';
      return null;
    }
    return el;
  }

  var apexBase = {
    chart: {
      fontFamily: FONT,
      fontWeight: 500,
      toolbar: { show: false },
      zoom: { enabled: false },
      animations: { speed: 600 }
    },
    colors: PALETTE,
    dataLabels: { enabled: false },
    legend: {
      fontFamily: FONT,
      fontWeight: 500,
      fontSize: '12px',
      labels: { colors: MUTED }
    },
    grid: {
      borderColor: '#E8EEF6',
      strokeDashArray: 4
    },
    tooltip: {
      theme: 'light',
      style: { fontFamily: FONT }
    }
  };

  function renderVolume() {
    var el = document.getElementById('chartVolume');
    if (!el || typeof ApexCharts === 'undefined') return;
    var months = d.months || { labels: [], opened: [], completed: [] };
    if (!months.labels.length) {
      setEmpty('chartVolume', 'No monthly volume yet.');
      return;
    }
    var chart = new ApexCharts(el, Object.assign({}, apexBase, {
      chart: Object.assign({}, apexBase.chart, { type: 'area', height: 340, stacked: false }),
      colors: [NAVY, BLUE],
      stroke: { curve: 'monotoneCubic', width: 3 }, // smooth without dipping below real values
      fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 0.5, opacityFrom: 0.35, opacityTo: 0.04, stops: [0, 90, 100] }
      },
      series: [
        { name: 'Opened', data: months.opened },
        { name: 'Completed', data: months.completed }
      ],
      xaxis: {
        categories: months.labels,
        labels: { style: { colors: MUTED, fontFamily: FONT, fontSize: '12px' } },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: {
        min: 0,
        decimalsInFloat: 0,
        labels: {
          formatter: function (v) { return Math.round(v); },
          style: { colors: MUTED, fontFamily: FONT }
        }
      },
      markers: { size: 4, strokeWidth: 0 }
    }));
    chart.render();
    apexInstances.push(chart);
  }

  function renderDonut(id, pack, name) {
    var el = document.getElementById(id);
    if (!el || typeof ApexCharts === 'undefined') return;
    if (!hasSeries(pack)) {
      setEmpty(id, 'No data for this chart.');
      return;
    }
    var chart = new ApexCharts(el, Object.assign({}, apexBase, {
      chart: Object.assign({}, apexBase.chart, { type: 'donut', height: 300 }),
      series: pack.data,
      labels: pack.labels,
      stroke: { width: 2, colors: ['#fff'] },
      plotOptions: {
        pie: {
          donut: {
            size: '68%',
            labels: {
              show: true,
              name: { fontFamily: FONT, fontSize: '12px', color: MUTED },
              value: {
                fontFamily: FONT,
                fontWeight: 700,
                fontSize: '22px',
                color: NAVY,
                formatter: function (v) { return v; }
              },
              total: {
                show: true,
                label: name || 'Total',
                fontFamily: FONT,
                fontSize: '12px',
                color: MUTED,
                formatter: function (w) {
                  return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0);
                }
              }
            }
          }
        }
      },
      legend: Object.assign({}, apexBase.legend, { position: 'bottom' })
    }));
    chart.render();
    apexInstances.push(chart);
  }

  function renderBrands() {
    var el = document.getElementById('chartBrands');
    if (!el || typeof ApexCharts === 'undefined') return;
    if (!hasSeries(d.brands)) {
      setEmpty('chartBrands', 'No brand data yet.');
      return;
    }
    var chart = new ApexCharts(el, Object.assign({}, apexBase, {
      chart: Object.assign({}, apexBase.chart, { type: 'bar', height: 300 }),
      plotOptions: {
        bar: { horizontal: true, borderRadius: 6, barHeight: '62%' }
      },
      colors: [BLUE], // one series, one colour
      series: [{ name: 'Tickets', data: d.brands.data }],
      xaxis: {
        categories: d.brands.labels,
        labels: {
          formatter: function (v) { return Math.round(v); },
          style: { colors: MUTED, fontFamily: FONT }
        }
      },
      yaxis: {
        labels: { style: { colors: NAVY, fontFamily: FONT, fontSize: '12px' } }
      },
      legend: { show: false },
      dataLabels: { enabled: true, style: { fontFamily: FONT, fontSize: '11px' } }
    }));
    chart.render();
    apexInstances.push(chart);
  }

  function renderTypes() {
    var el = document.getElementById('chartTypes');
    if (!el || typeof ApexCharts === 'undefined') return;
    if (!hasSeries(d.types)) {
      setEmpty('chartTypes', 'No device types yet.');
      return;
    }
    var chart = new ApexCharts(el, Object.assign({}, apexBase, {
      chart: Object.assign({}, apexBase.chart, { type: 'polarArea', height: 280 }),
      series: d.types.data,
      labels: d.types.labels,
      stroke: { colors: ['#fff'] },
      fill: { opacity: 0.88 },
      yaxis: { show: false },
      legend: Object.assign({}, apexBase.legend, { position: 'bottom' })
    }));
    chart.render();
    apexInstances.push(chart);
  }

  function renderOutcome() {
    var el = document.getElementById('chartOutcome');
    if (!el || typeof ApexCharts === 'undefined') return;
    if (!hasSeries(d.outcome)) {
      setEmpty('chartOutcome', 'No outcome data yet.');
      return;
    }
    var total = d.outcome.data.reduce(function (a, b) { return a + b; }, 0) || 1;
    var percents = d.outcome.data.map(function (n) { return Math.round((n / total) * 100); });
    var chart = new ApexCharts(el, Object.assign({}, apexBase, {
      chart: Object.assign({}, apexBase.chart, { type: 'radialBar', height: 280 }),
      colors: [NAVY, BLUE, MUTED],
      series: percents,
      labels: d.outcome.labels,
      plotOptions: {
        radialBar: {
          offsetY: 8,
          hollow: { size: '28%' },
          dataLabels: {
            name: { fontSize: '12px', fontFamily: FONT, color: MUTED },
            value: { fontSize: '16px', fontFamily: FONT, fontWeight: 700, color: NAVY, formatter: function (v) { return v + '%'; } },
            total: {
              show: true,
              label: 'Done',
              fontFamily: FONT,
              color: MUTED,
              formatter: function () { return (d.completionRate || 0) + '%'; }
            }
          }
        }
      },
      legend: Object.assign({}, apexBase.legend, { show: true, position: 'bottom' })
    }));
    chart.render();
    apexInstances.push(chart);
  }

  function renderFunnel() {
    var el = document.getElementById('chartFunnel');
    if (!el || typeof echarts === 'undefined') return;
    if (!hasSeries(d.funnel)) {
      setEmpty('chartFunnel', 'No pipeline data yet.');
      return;
    }
    var items = d.funnel.labels.map(function (label, i) {
      return { name: label, value: d.funnel.data[i] };
    }).filter(function (item) { return item.value > 0; });
    if (!items.length) {
      setEmpty('chartFunnel', 'No pipeline data yet.');
      return;
    }
    var chart = echarts.init(el);
    chart.setOption({
      color: [NAVY, NAVY_MID, BLUE, BLUE_SOFT, '#A9C1FF'],
      tooltip: { trigger: 'item', formatter: '{b}: {c}' },
      series: [{
        type: 'funnel',
        left: '8%',
        top: 12,
        bottom: 12,
        width: '84%',
        minSize: '28%',
        maxSize: '100%',
        sort: 'none',
        gap: 8,
        label: {
          show: true,
          position: 'inside',
          color: '#fff',
          fontFamily: FONT,
          fontSize: 12,
          formatter: '{b}  {c}'
        },
        itemStyle: { borderColor: '#fff', borderWidth: 2 },
        emphasis: { label: { fontSize: 13 } },
        data: items
      }]
    });
    echartInstances.push(chart);
  }

  function renderTechnicians() {
    var el = document.getElementById('chartTechnicians');
    if (!el || typeof echarts === 'undefined') return;
    if (!hasSeries(d.technicians)) {
      setEmpty('chartTechnicians', 'No technicians assigned yet.');
      return;
    }
    var chart = echarts.init(el);
    chart.setOption({
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
      grid: { left: 8, right: 24, top: 16, bottom: 8, containLabel: true },
      xAxis: {
        type: 'value',
        minInterval: 1,
        splitLine: { lineStyle: { type: 'dashed', color: '#E8EEF6' } },
        axisLabel: { color: MUTED, fontFamily: FONT }
      },
      yAxis: {
        type: 'category',
        data: d.technicians.labels.slice().reverse(),
        axisTick: { show: false },
        axisLine: { show: false },
        axisLabel: { color: NAVY, fontFamily: FONT, fontSize: 12 }
      },
      series: [{
        type: 'bar',
        barWidth: 18,
        data: d.technicians.data.slice().reverse(),
        itemStyle: {
          borderRadius: [0, 8, 8, 0],
          color: BLUE
        }
      }]
    });
    echartInstances.push(chart);
  }

  if (typeof ApexCharts !== 'undefined') {
    renderVolume();
    renderDonut('chartStatus', d.status, 'Tickets');
    renderBrands();
    renderTypes();
    renderOutcome();
    if (document.getElementById('chartClaims')) {
      renderDonut('chartClaims', d.claims, 'Claims');
    }
  }

  if (typeof echarts !== 'undefined') {
    renderFunnel();
    renderTechnicians();
  }

  window.addEventListener('resize', function () {
    echartInstances.forEach(function (c) { c.resize(); });
  });
})();
