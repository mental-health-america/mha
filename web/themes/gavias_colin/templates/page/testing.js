AmCharts.makeChart("mapdiv2", {
  type: "map",


  colorSteps: 4,

  dataProvider: {
    map: "usaLowAdultRank2019",


    areas: [{


      id: "US-AL",
      value: 50
    }, {

      id: "US-AK",
      value: 100
    }, {

      id: "US-AZ",
      value: 75
    }, {

      id: "US-AR",
      value: 100
    }, {

      id: "US-CA",
      value: 50
    }, {

      id: "US-CO",
      value: 75
    }, {

      id: "US-CT",
      value: 50
    }, {

      id: "US-DC",
      value: 100
    }, {
      id: "US-DE",
      value: 50
    }, {

      id: "US-FL",
      value: 50
    }, {

      id: "US-GA",
      value: 75
    }, {

      id: "US-HI",
      value: 25
    }, {

      id: "US-ID",
      value: 100
    }, {

      id: "US-IL",
      value: 25
    }, {

      id: "US-IN",
      value: 100
    }, {

      id: "US-IA",
      value: 25
    }, {

      id: "US-KS",
      value: 75
    }, {

      id: "US-KY",
      value: 75
    }, {

      id: "US-LA",
      value: 100
    }, {

      id: "US-ME",
      value: 25
    }, {

      id: "US-MD",
      value: 25
    }, {

      id: "US-MA",
      value: 25
    }, {

      id: "US-MI",
      value: 25
    }, {

      id: "US-MN",
      value: 25
    }, {

      id: "US-MS",
      value: 75
    }, {

      id: "US-MO",
      value: 75
    }, {

      id: "US-MT",
      value: 100
    }, {

      id: "US-NE",
      value: 50
    }, {

      id: "US-NV",
      value: 100
    }, {

      id: "US-NH",
      value: 75
    }, {

      id: "US-NJ",
      value: 25
    }, {

      id: "US-NM",
      value: 75
    }, {

      id: "US-NY",
      value: 25
    }, {

      id: "US-NC",
      value: 75
    }, {

      id: "US-ND",
      value: 25
    }, {

      id: "US-OH",
      value: 50
    }, {

      id: "US-OK",
      value: 50
    }, {

      id: "US-OR",
      value: 100
    }, {

      id: "US-PA",
      value: 50
    }, {

      id: "US-RI",
      value: 25
    }, {

      id: "US-SC",
      value: 100
    }, {

      id: "US-SD",
      value: 50
    }, {

      id: "US-TN",
      value: 100
    }, {

      id: "US-TX",
      value: 50
    }, {

      id: "US-UT",
      value: 100
    }, {

      id: "US-VT",
      value: 50
    }, {

      id: "US-VA",
      value: 75
    }, {

      id: "US-WA",
      value: 75
    }, {

      id: "US-WV",
      value: 50
    }, {

      id: "US-WI",
      value: 25
    }, {

      id: "US-WY",
      value: 100
    }]
  },

  areasSettings: {
    autoZoom: true
  },

  valueLegend: {
    right: 10,
    minValue: "Highest Ranked",
    maxValue: "Lowest Ranked"
  }

});
