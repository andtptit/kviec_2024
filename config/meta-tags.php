<?php

return array(
    
    /*
     |--------------------------------------------------------------------------
     | Site default title
     |--------------------------------------------------------------------------
     |
     */
    
    'title' => 'Job ads',
    
    /*
     |--------------------------------------------------------------------------
     | Limit title meta tag length
     |--------------------------------------------------------------------------
     |
     | To best SEO implementation, limit tags.
     |
     */
    
    'title_limit' => 70,
    
    /*
     |--------------------------------------------------------------------------
     | Limit description meta tag length
     |--------------------------------------------------------------------------
     |
     | To best SEO implementation, limit tags.
     |
     */
    
    'description_limit' => 200,
    
    /*
     |--------------------------------------------------------------------------
     | OpenGraph values
     |--------------------------------------------------------------------------
     |
     */
    
    'open_graph' => [
        'site_name' => 'Site Name',
        'type' => 'website'
    ],
    
    /*
     |--------------------------------------------------------------------------
     | Twitter Card values
     |--------------------------------------------------------------------------
     |
     */
    
    'twitter' => [
        'card' => 'summary',
        'creator' => '@BeDigit',
        'site' => '@BeDigit'
    ],
    
    /*
     |--------------------------------------------------------------------------
     | Supported languages
     |--------------------------------------------------------------------------
     |
     */
    
    'locale_url' => '[scheme]://[locale][host][uri]',
    
    /*
     |--------------------------------------------------------------------------
     | Supported languages
     |--------------------------------------------------------------------------
     |
     */
    
    'locales' => ['en', 'fr', 'es'],
);
