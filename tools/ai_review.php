<?php
require __DIR__ . '/ai-review/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\Yaml\Yaml;

$opts = getopt('', ['pr:', 'sha:', 'repo:']);
$pr   = $opts['pr'] ?? null;
$sha  = $opts['sha'] ?? null;
$repo = $opts['repo'] ?? null;

if (!$pr || !$sha || !$repo) {
    echo "Missing required arguments: --pr, --sha, --repo\n";
    exit(1);
}

$hfToken = getenv('HF_TOKEN');       // Hugging Face token
$ghToken = getenv('GITHUB_TOKEN');   // GitHub token

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

// 3) Build diffs string
$diffs = "";
foreach ($files as $f) {
    if (!isset($f['patch'])) continue;
    $diffs .= "File: {$f['filename']}\nPatch:\n{$f['patch']}\n\n";
}
if (empty($diffs)) {
    echo "No diffs found. Exiting.\n";
    exit(0);
}

// 4) Build concise prompt
$rulesText = $rules ? json_encode($rules, JSON_PRETTY_PRINT) : "No custom rules";

$prompt = <<<PROMPT
You are a professional code reviewer.

Rules:
- Follow these repo-specific rules:
$rulesText

Task:
- Review the following code changes.
- Be concise and actionable, like a human reviewer.
- Focus on security, bugs, style, missing tests.
- Output JSON array: [{"file":"<file_path>","line":<line_number>,"comment":"<text>"}]
- Each comment should be 1-2 sentences max, clean and professional.

Code diffs:
$diffs
PROMPT;

// 5) Call Hugging Face Inference API
$hfResp = $client->post("https://router.huggingface.co/nebius/v1/completions", [
    'headers' => [
        'Authorization' => "Bearer {$hfToken}",
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
    ],
    'json' => [
        'inputs' => $prompt,
        'model' => 'openai/gpt-oss-20b',
        'prompt' => $prompt,
        'parameters' => [
            'max_new_tokens' => 500
        ],
        'options' => [
            'use_cache' => false
        ]
    ]
]);

$out = json_decode($hfResp->getBody(), true);

// 6) Extract review JSON from Hugging Face response
$reviewJson = "";
if (isset($out['choices'][0]['text'])) {
    $reviewJson = trim($out['choices'][0]['text']);
} else {
    $reviewJson = json_encode($out, JSON_PRETTY_PRINT);
}

// 7) Post each comment to GitHub PR
$comments = json_decode($reviewJson, true);
if (!is_array($comments)) {
    echo "Failed to decode AI review JSON:\n$reviewJson\n";
    exit(1);
}

foreach ($comments as $c) {
    if (!isset($c['file'], $c['line'], $c['comment'])) continue;
    $client->post("https://api.github.com/repos/{$repo}/pulls/{$pr}/comments", [
        'headers' => [
            'Authorization' => "Bearer {$ghToken}",
            'Accept'        => 'application/vnd.github+json',
        ],
        'json' => [
            'body' => $c['comment'],
            'commit_id' => $sha,
            'path' => $c['file'],
            'line' => $c['line'],
            'side' => 'RIGHT'
        ]
    ]);
}

echo "AI review comments posted to PR #{$pr}\n";
