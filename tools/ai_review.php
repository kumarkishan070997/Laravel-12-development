<?php
require __DIR__ . '/ai-review/vendor/autoload.php'; // path to your AI review composer autoload

use GuzzleHttp\Client;
use Symfony\Component\Yaml\Yaml;

// --- Get CLI args ---
$opts = getopt('', ['pr:', 'sha:', 'repo:']);
$pr   = $opts['pr'];
$sha  = $opts['sha'];
$repo = $opts['repo'];

// --- Tokens ---
$hfToken = getenv('HF_TOKEN');       // Hugging Face token
if (!$hfToken) {
    echo "HF_TOKEN is missing!\n";
    exit(1);
}
$ghToken = getenv('GITHUB_TOKEN');   // GitHub token

$client = new Client();

// --- 1) Get changed files from PR ---
$resp = $client->get("https://api.github.com/repos/{$repo}/pulls/{$pr}/files", [
    'headers' => [
        'Authorization' => "Bearer {$ghToken}",
        'Accept'        => 'application/vnd.github+json',
    ]
]);
$files = json_decode($resp->getBody(), true);

// --- 2) Load review rules ---
$rules = [];
if (file_exists('.ai-review.yml')) {
    $rules = Yaml::parseFile('.ai-review.yml');
}
$rulesText = $rules ? json_encode($rules, JSON_PRETTY_PRINT) : "No custom rules";

// --- 3) Build diffs string ---
$diffs = "";
foreach ($files as $f) {
    if (!isset($f['patch'])) continue;
    $diffs .= "File: {$f['filename']}\nPatch:\n{$f['patch']}\n\n";
}

if (empty($diffs)) {
    echo "No diffs found. Exiting.\n";
    exit(0);
}

// --- 4) Build prompt ---
$prompt = <<<PROMPT
You are an AI code reviewer.

Rules:
- Follow these repo-specific rules:
$rulesText

Task:
- Review the following code changes.
- Point out security issues, bugs, style problems, and missing tests.
- Suggest improvements.
- Respond in clear markdown text, not JSON.

Here are the code changes:

$diffs
PROMPT;

// --- 5) Call Hugging Face router API with openai/gpt-oss-20b ---
$hfResp = $client->post("https://router.huggingface.co/nebius/v1/completions", [
    'headers' => [
        'Authorization' => "Bearer {$hfToken}",
        'Content-Type'  => 'application/json',
    ],
    'json' => [
        'inputs'     => $prompt,
        'prompt'     => $prompt,
        'model'      => 'openai/gpt-oss-20b',
        'parameters' => [
            'max_new_tokens' => 500,
        ],
        'options' => [
            'use_cache' => false,
        ],
    ],
]);

$out = json_decode($hfResp->getBody(), true);

// --- 6) Extract AI review text ---
$reviewText = "";
if (isset($out['choices'][0]['text'])) {
    $reviewText = $out['choices'][0]['text'];
} else {
    $reviewText = json_encode($out, JSON_PRETTY_PRINT);
}

// --- 7) Post the review as a single PR comment ---
$client->post("https://api.github.com/repos/{$repo}/issues/{$pr}/comments", [
    'headers' => [
        'Authorization' => "Bearer {$ghToken}",
        'Accept'        => 'application/vnd.github+json',
    ],
    'json' => [
        'body' => "🤖 **AI Reviewer (GPT-OSS-20B)** says:\n\n" . $reviewText,
    ],
]);

echo "AI review posted to PR #{$pr}\n";
