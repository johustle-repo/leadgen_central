<?php

return [
    'csv_max_kilobytes' => (int) env('LEADGEN_CSV_MAX_KILOBYTES', 5120),
    'csv_max_files' => (int) env('LEADGEN_CSV_MAX_FILES', 50),
    'seed_password' => env('LEADGEN_SEED_PASSWORD', 'password'),

    /*
    | US and Canadian leads store their state/province in the City field.
    | A city entered without its state is placed using these overrides
    | first (city name => state, per country), then by how other leads
    | wrote the same city with a state. Add a city here when it is
    | ambiguous or never appears with a state.
    */
    'city_states' => [
        'US' => [
            'Louisville' => 'Colorado',
            'Boston' => 'Massachusetts',
            'Breese' => 'Illinois',
            'Broomfield' => 'Colorado',
            'Chandler' => 'Arizona',
            'Charlotte' => 'North Carolina',
            'Colorado Springs' => 'Colorado',
            'Findlay' => 'Ohio',
            'Fresno' => 'California',
            'Grand Forks' => 'North Dakota',
            'Lake Oswego' => 'Oregon',
            'Long Island City' => 'New York',
            'Lorton' => 'Virginia',
            'New Bern' => 'North Carolina',
            'Orem' => 'Utah',
            'Vernal' => 'Utah',
        ],
        'CA' => [],
    ],

    'seed_accounts' => [
        ['name' => 'LeadGen Super Administrator', 'email' => env('LEADGEN_SUPERADMIN_EMAIL', 'superadmin@leadgen.test'), 'role' => 'super_administrator'],
        ['name' => 'LeadGen Administrator', 'email' => env('LEADGEN_ADMIN_EMAIL', 'admin@leadgen.test'), 'role' => 'administrator'],
        ['name' => 'LeadGen Sub-Administrator', 'email' => env('LEADGEN_SUBADMIN_EMAIL', 'subadmin@leadgen.test'), 'role' => 'sub_administrator'],
        ['name' => 'Sample Agent', 'email' => env('LEADGEN_AGENT_EMAIL', 'agent@leadgen.test'), 'role' => 'agent'],
    ],
];
