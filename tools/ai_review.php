<?php
require __DIR__ . '/ai-review/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\Yaml\Yaml;

// -------------------------
// Parse CLI arguments
// -------------------------
$opts = getopt("", ["pr:", "repo:", "token:", "hf_token:"]);
if (!isset($opts['pr'], $opts['repo'], $opts['token'], $opts['hf_token'])) {
    echo "Missing required arguments. Usage: php ai_review.php --pr=NUMBER --repo=owner/repo --token=GITHUB_TOKEN --hf_token=HF_TOKEN\n";
    exit(1);
}

$pr = $opts['pr'];
$repo = $opts['repo'];
$githubToken = $opts['token'];
$hfToken = $opts['hf_token'];

if (empty($hfToken)) {
    echo "HF_TOKEN is empty. Set the Hugging Face token.\n";
    exit(1);
}

$client = new Client([
    'headers' => [
        'Authorization' => "token {$githubToken}",
        'Accept'        => 'application/vnd.github+json'
    ]
]);

// -------------------------
// Get PR files
// -------------------------
try {
    $response = $client->get("https://api.github.com/repos/{$repo}/pulls/{$pr}/files");
    $files = json_decode($response->getBody()->getContents(), true);
} catch (\Exception $e) {
    echo "Failed to fetch PR files: {$e->getMessage()}\n";
    exit(1);
}

// -------------------------
// Collect diffs for AI
// -------------------------
$diffs = [];
foreach ($files as $file) {
    $diffs[] = [
        'filename' => $file['filename'],
        'patch' => $file['patch'] ?? ''
    ];
}

// -------------------------
// Prepare prompt for AI
// -------------------------
$prompt = "You are a code reviewer. Review the following diffs and provide concise, clear comments for each code change. Do not write long paragraphs. Output must be a JSON array. Each object must have exactly 'file', 'line', and 'comment'. Each comment should be short and relevant.\n\n";
$prompt .= json_encode($diffs, JSON_PRETTY_PRINT);

// -------------------------
// Call Hugging Face API
// -------------------------
$hfClient = new Client(['base_uri' => 'https://router.huggingface.co/']);
try {
    $hfResponse = $hfClient->post("nebius/v1/completions", [
        'headers' => [
            'Authorization' => "Bearer {$hfToken}",
            'Content-Type'  => 'application/json'
        ],
        'json' => [
            'inputs' => $prompt,
            'model' => 'openai/gpt-oss-20b',
            'parameters' => [
                'max_new_tokens' => 500
            ],
            'options' => [
                'use_cache' => false
            ],
            'prompt' => $prompt
        ]
    ]);
} catch (\Exception $e) {
    echo "Hugging Face request failed: {$e->getMessage()}\n";
    exit(1);
}

$hfBody = json_decode($hfResponse->getBody()->getContents(), true);
if (!isset($hfBody['choices'][0]['text'])) {
    echo "Invalid Hugging Face response.\n";
    var_dump($hfBody);
    exit(1);
}

$aiText = trim($hfBody['choices'][0]['text'] ?? '');
$aiText = preg_replace('/[\x00-\x1F\x7F]/u', '', $aiText); // remove control chars

$comments = json_decode($aiText, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Failed to decode AI review JSON: " . json_last_error_msg() . "\n";
    var_dump($aiText);
    exit(1);
}

if (!is_array($comments)) {
    echo "AI response is not a JSON array.\n";
    var_dump($aiText);
    exit(1);
}

// -------------------------
// Post comments to GitHub PR
// -------------------------
foreach ($comments as $comment) {
    if (!isset($comment['file'], $comment['line'], $comment['comment'])) continue;

    $payload = [
        'body' => $comment['comment'],
        'path' => $comment['file'],
        'line' => $comment['line'],
        'side' => 'RIGHT'
    ];

    try {
        $client->post("https://api.github.com/repos/{$repo}/pulls/{$pr}/comments", ['json' => $payload]);
    } catch (\Exception $e) {
        echo "Failed to post comment on {$comment['file']} line {$comment['line']}: {$e->getMessage()}\n";
    }
}

echo "AI review completed successfully.\n";