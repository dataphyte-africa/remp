<?php

// Basic Beam settings
return [
    /*
    |--------------------------------------------------------------------------
    | Data retention/compression period
    |--------------------------------------------------------------------------
    |
    | Beam aggregates data from Segments API, here we set up how long these data are kept for.
    | Negative value means data are kept indefinitely.
    |
    */
    'aggregated_data_retention_period' => env('AGGREGATED_DATA_RETENTION_PERIOD', 90),

    /*
    |--------------------------------------------------------------------------
    | Pageview graph data source
    |--------------------------------------------------------------------------
    |
    | Valid values:
    | snapshots - load data stored in DB snapshots of Journal API (data represents recorded concurrents for points in time)
    | journal - (default for now) load data directly from Journal API (data represents total number of pageviews for specific intervals).
    |
    */
    'pageview_graph_data_source' => env('PAGEVIEW_GRAPH_DATA_SOURCE', 'journal'),

    /*
    |--------------------------------------------------------------------------
    | Article traffic graph data source
    |--------------------------------------------------------------------------
    |
    | Valid values:
    | snapshots - load data stored in DB snapshots of Journal API (data represents recorded concurrents for points in time)
    | journal - load data directly from Journal API (data represents total number of pageviews for specific intervals).
    | pageviews - (default for now) load data from article_pageviews DB table.
    |
    */
    'article_traffic_graph_data_source' => env('ARTICLE_TRAFFIC_GRAPH_DATA_SOURCE', 'pageviews'),

    // Temporarily disable property token filtering for debugging
    'disable_token_filtering' => env('DISABLE_TOKEN_FILTERING', false)
];
