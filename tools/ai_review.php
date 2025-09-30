<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\Yaml\Yaml;

$opts = getopt('', ['pr:', 'sha:', 'repo:']);
$pr   = $opts['pr'];
$sha  = $opts['sha'];
$repo = $opts['repo'];

$hfToken = getenv('HF_TOKEN');       // Hugging Face token (from secrets)
$ghToken = getenv('GITHUB_TOKEN');   // GitHub token (auto from Actions)

$client = new Client();

// 1) Get changed files from PR
$resp = $client->get("https://api.github.com/repos/{$repo}/pulls/{$pr}/files", [
    'headers' => [
        'Authorization' => "Bearer {$ghToken}",
        'Accept'        => 'application/vnd.github+json',
    ]
]);
$files = json_decode($resp->getBody(), true);

// 2) Load review rules if config exists
$rules = [];
if (file_exists('.ai-review.yml')) {
    $rules = Yaml::parseFile('.ai-review.yml');
}

// Convert rules to string outside heredoc
$rulesText = $rules ? json_encode($rules, JSON_PRETTY_PRINT) : "No custom rules";

// 3) Build a prompt with rules + diffs
$diffs = "";
foreach ($files as $f) {
    if (!isset($f['patch'])) {
        continue;
    }
    $diffs .= "File: {$f['filename']}\nPatch:\n{$f['patch']}\n\n";
}

// If no diffs, skip
if (empty($diffs)) {
    echo "No diffs found. Exiting.\n";
    exit(0);
}

// Heredoc with variables only, no expressions
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

// 4) Call Hugging Face Inference API with SmolLM3-3B
$hfResp = $client->post("https://api-inference.huggingface.co/models/HuggingFaceTB/SmolLM3-3B", [
    'headers' => [
        'Authorization' => "Bearer {$hfToken}",
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
    ],
    'json' => [
        'inputs' => $prompt,
        'parameters' => [
            'max_new_tokens' => 500,
        ],
        'options' => [
            'use_cache' => false,
        ],
    ],
]);

$out = json_decode($hfResp->getBody(), true);

// Hugging Face response could be array or object
$reviewText = "";
if (isset($out[0]['generated_text'])) {
    $reviewText = $out[0]['generated_text'];
} elseif (isset($out['generated_text'])) {
    $reviewText = $out['generated_text'];
} else {
    $reviewText = json_encode($out, JSON_PRETTY_PRINT);
}

// 5) Post the review as a single PR comment
$client->post("https://api.github.com/repos/{$repo}/issues/{$pr}/comments", [
    'headers' => [
        'Authorization' => "Bearer {$ghToken}",
        'Accept'        => 'application/vnd.github+json',
    ],
    'json' => [
        'body' => "🤖 **AI Reviewer (SmolLM3-3B)** says:\n\n" . $reviewText,
    ],
]);

echo "AI review posted to PR #{$pr}\n";
