<?php

return [
    'pdf' => [
        'timeout' => (int) env('SLIDES_PDF_TIMEOUT', 60),
        'no_sandbox' => filter_var(env('SLIDES_PDF_NO_SANDBOX', false), FILTER_VALIDATE_BOOL),
        'node_binary' => env('SLIDES_PDF_NODE_BINARY'),
        'npm_binary' => env('SLIDES_PDF_NPM_BINARY'),
        'chrome_path' => env('SLIDES_PDF_CHROME_PATH'),
    ],
];
