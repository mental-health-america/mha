AmCharts.makeChart('mapdiv21', {
  type: 'map',

  colorSteps: 4,

  dataProvider: {
    map: 'usaYouthMDETreatmentHelped2024',

    areas: [
      {

        id: 'US-AL',
        value: 25,
      }, {

        id: 'US-AK',
        value: 100,
      }, {

        id: 'US-AZ',
        value: 100,
      }, {

        id: 'US-AR',
        value: 25,
      }, {

        id: 'US-CA',
        value: 50,
      }, {

        id: 'US-CO',
        value: 25,
      }, {

        id: 'US-CT',
        value: 100,
      }, {

        id: 'US-DE',
        value: 75,
      }, {
        id: 'US-DC',
        value: 25,
      }, {

        id: 'US-FL',
        value: 50,
      }, {

        id: 'US-GA',
        value: 50,
      }, {

        id: 'US-HI',
        value: 100,
      }, {

        id: 'US-ID',
        value: 50,
      }, {

        id: 'US-IL',
        value: 50,
      }, {

        id: 'US-IN',
        value: 50,
      }, {

        id: 'US-IA',
        value: 100,
      }, {

        id: 'US-KS',
        value: 75,
      }, {

        id: 'US-KY',
        value: 75,
      }, {

        id: 'US-LA',
        value: 75,
      }, {

        id: 'US-ME',
        value: 25,
      }, {

        id: 'US-MD',
        value: 75,
      }, {

        id: 'US-MA',
        value: 100,
      }, {

        id: 'US-MI',
        value: 75,
      }, {

        id: 'US-MN',
        value: 25,
      }, {

        id: 'US-MS',
        value: 25,
      }, {

        id: 'US-MO',
        value: 100,
      }, {

        id: 'US-MT',
        value: 25,
      }, {

        id: 'US-NE',
        value: 100,
      }, {

        id: 'US-NV',
        value: 100,
      }, {

        id: 'US-NH',
        value: 25,
      }, {

        id: 'US-NJ',
        value: 50,
      }, {

        id: 'US-NM',
        value: 50,
      }, {

        id: 'US-NY',
        value: 25,
      }, {

        id: 'US-NC',
        value: 50,
      }, {

        id: 'US-ND',
        value: 50,
      }, {

        id: 'US-OH',
        value: 75,
      }, {

        id: 'US-OK',
        value: 75,
      }, {

        id: 'US-OR',
        value: 100,
      }, {

        id: 'US-PA',
        value: 50,
      }, {

        id: 'US-RI',
        value: 100,
      }, {

        id: 'US-SC',
        value: 100,
      }, {

        id: 'US-SD',
        value: 100,
      }, {

        id: 'US-TN',
        value: 50,
      }, {

        id: 'US-TX',
        value: 75,
      }, {

        id: 'US-UT',
        value: 75,
      }, {

        id: 'US-VT',
        value: 25,
      }, {

        id: 'US-VA',
        value: 75,
      }, {

        id: 'US-WA',
        value: 50,
      }, {

        id: 'US-WV',
        value: 75,
      }, {

        id: 'US-WI',
        value: 25,
      }, {

        id: 'US-WY',
        value: 25,
      },
    ],
  },

  areasSettings: {
    autoZoom: true,
  },

  valueLegend: {
    right: 10,
    minValue: 'Ranked 1-13',
    maxValue: 'Ranked 39-51',
  },

});
