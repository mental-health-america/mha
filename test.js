AmCharts.makeChart('mapdiv9', {
  type: 'map',
  colorSteps: 4,

  dataProvider: {
    map: 'usaYouthMDE2023',
    areas: [
      { id: 'US-AL', value: 50 },
      { id: 'US-AK', value: 100 },
      { id: 'US-AZ', value: 100 },
      { id: 'US-AR', value: 25 },
      { id: 'US-CA', value: 50 },
      { id: 'US-CO', value: 100 },
      { id: 'US-CT', value: 50 },
      { id: 'US-DE', value: 75 },
      { id: 'US-DC', value: 25 },
      { id: 'US-FL', value: 75 },
      { id: 'US-GA', value: 25 },
      { id: 'US-HI', value: 25 },
      { id: 'US-ID', value: 75 },
      { id: 'US-IL', value: 50 },
      { id: 'US-IN', value: 75 },
      { id: 'US-IA', value: 75 },
      { id: 'US-KS', value: 75 },
      { id: 'US-KY', value: 50 },
      { id: 'US-LA', value: 50 },
      { id: 'US-ME', value: 50 },
      { id: 'US-MD', value: 100 },
      { id: 'US-MA', value: 25 },
      { id: 'US-MI', value: 25 },
      { id: 'US-MN', value: 100 },
      { id: 'US-MS', value: 25 },
      { id: 'US-MO', value: 75 },
      { id: 'US-MT', value: 100 },
      { id: 'US-NE', value: 75 },
      { id: 'US-NV', value: 100 },
      { id: 'US-NH', value: 100 },
      { id: 'US-NJ', value: 50 },
      { id: 'US-NM', value: 100 },
      { id: 'US-NY', value: 50 },
      { id: 'US-NC', value: 50 },
      { id: 'US-ND', value: 75 },
      { id: 'US-OH', value: 75 },
      { id: 'US-OK', value: 25 },
      { id: 'US-OR', value: 100 },
      { id: 'US-PA', value: 75 },
      { id: 'US-RI', value: 100 },
      { id: 'US-SC', value: 25 },
      { id: 'US-SD', value: 25 },
      { id: 'US-TN', value: 75 },
      { id: 'US-TX', value: 25 },
      { id: 'US-UT', value: 25 },
      { id: 'US-VT', value: 25 },
      { id: 'US-VA', value: 50 },
      { id: 'US-WA', value: 100 },
      { id: 'US-WV', value: 50 },
      { id: 'US-WI', value: 50 },
      { id: 'US-WY', value: 100 }
    ]
  },

  areasSettings: {
    autoZoom: true
  },

  valueLegend: {
    right: 10,
    minValue: 'Ranked 1-13',
    maxValue: 'Ranked 39-51'
  }
});
