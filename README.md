# emotionwise-php

PHP wrapper for the `emotionwise.ai` API using `X-API-Key` authentication.

## Install

```bash
composer require emotionwise/emotionwise-php
```

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use Emotionwise\EmotionwiseClient;

$client = new EmotionwiseClient(apiKey: 'YOUR_API_KEY');

$result = $client->detectEmotion(
    message: 'I am happy but a bit nervous',
    context: 'daily journal'
);

var_dump($result);
```

## Detector Endpoint

- `POST /api/v1/tools/emotion-detector`
- Message length: min `1`, max `1000` characters.

## Feedback Endpoint

- `POST /api/v1/feedback/submit`

Example:

```php
<?php

$feedback = $client->submitFeedback(
    text: 'I am happy but a bit nervous',
    predictedEmotions: ['joy', 'nervousness'],
    suggestedEmotions: ['optimism'],
    predictedSarcasm: false,
    comment: 'Pretty accurate',
    languageCode: 'en',
);

var_dump($feedback);
```

## Error Handling

- Default base URL: `https://api.emotionwise.ai`
- Common handled statuses:
  - `401`: missing or invalid API key
  - `403`: API key inactive or not allowed
  - `429`: quota or rate limit exceeded
- Non-2xx responses throw `Emotionwise\\Exceptions\\EmotionwiseAPIError` with:
  - `getStatusCode()`
  - `getResponseBody()`

## Development

```bash
composer install
composer test
```
