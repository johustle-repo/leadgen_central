<?php

return [
    'csv_max_kilobytes' => (int) env('LEADGEN_CSV_MAX_KILOBYTES', 5120),
    'csv_max_files' => (int) env('LEADGEN_CSV_MAX_FILES', 50),
    'seed_password' => env('LEADGEN_SEED_PASSWORD', 'password'),
    'seed_accounts' => [
        ['name' => 'LeadGen Administrator', 'email' => env('LEADGEN_ADMIN_EMAIL', 'admin@leadgen.test'), 'role' => 'administrator'],
        ['name' => 'LeadGen Sub-Administrator', 'email' => env('LEADGEN_SUBADMIN_EMAIL', 'subadmin@leadgen.test'), 'role' => 'sub_administrator'],
        ['name' => 'Sample Agent', 'email' => env('LEADGEN_AGENT_EMAIL', 'agent@leadgen.test'), 'role' => 'agent'],
    ],
];
