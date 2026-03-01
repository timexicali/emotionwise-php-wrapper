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

## Detector Response

The detector returns:

| Field              | Description                          |
|--------------------|--------------------------------------|
| `detected_emotions`| List of detected emotion labels      |
| `confidence_scores`| Confidence score per emotion         |
| `sarcasm_detected` | Whether sarcasm was detected (beta)  |
| `sarcasm_score`    | Sarcasm confidence score (beta)      |
| `detected_language`| Detected language of the input       |
| `session_id`       | Session identifier for the request   |

> **Sarcasm fields are experimental.** Do not use this API as the sole basis for legal, medical, hiring, or safety-critical decisions.

## Feedback Endpoint

- `POST /api/v1/feedback/submit`
- Both `predictedEmotions` and `suggestedEmotions` are validated against the set of accepted labels.

### Valid Emotion Labels

`admiration`, `amusement`, `anger`, `annoyance`, `approval`, `caring`, `confusion`, `curiosity`, `desire`, `disappointment`, `disapproval`, `disgust`, `embarrassment`, `excitement`, `fear`, `gratitude`, `grief`, `joy`, `love`, `nervousness`, `optimism`, `pride`, `realization`, `relief`, `remorse`, `sadness`, `surprise`, `neutral`

These are also available as `EmotionwiseClient::VALID_EMOTIONS`.

### Example

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
