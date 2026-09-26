<?php

return [
    /*
     * Staging gate compares this explicit value with the database name returned
     * by the live connection before it runs any test that writes temporary data.
     */
    'staging_database_guard' => env('STAGING_DATABASE_GUARD'),
];
