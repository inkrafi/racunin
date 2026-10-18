<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Gemini API Configuration
    |--------------------------------------------------------------------------
    |
    | API Key and Model definition for Google Gemini generative AI service.
    | Obtain a free key from https://aistudio.google.com/app/apikey
    |
    */

    'api_key' => env('GEMINI_API_KEY'),

    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),

    'timeout' => (int) env('GEMINI_TIMEOUT', 20),

    /*
    | Embedding model for semantic search (pgvector). The dimension must match
    | the `links.embedding vector(768)` column.
    */
    'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),

    'embedding_dimensions' => (int) env('GEMINI_EMBEDDING_DIMENSIONS', 768),

    'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
];
