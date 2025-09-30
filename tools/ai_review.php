<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\Yaml\Yaml;

// CLI options
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

// 3) Build diffs
$diffs = "";
foreach ($files as $f) {
    if (!isset($f['patch'])) continue;
    $diffs .= "File: {$f['filename']}\nPatch:\n{$f['patch']}\n\n";
}

if (empty($diffs)) {
    echo "No diffs found. Exiting.\n";
    exit(0);
}

// 4) Build AI prompt for concise inline comments
$prompt = <<<PROMPT
You are a professional code reviewer.

Rules:
- Follow these repo-specific rules:
{$rules ? json_encode($rules, JSON_PRETTY_PRINT) : "No custom rules"}

Task:
- Review the following code changes.
- Be concise and actionable, like a human reviewer.
- Focus on security, bugs, style, missing tests.
- Output JSON array: [{"file":"<file_path>","line":<line_number>,"comment":"<text>"}]
- Each comment should be 1-2 sentences max, clean and professional.

Code diffs:
{$diffs}
PROMPT;

// 5) Call Hugging Face Inference API (openai/gpt-oss-20b via router)
$hfResp = $client->post("https://router.huggingface.co/nebius/v1/completions", [
    'headers' => [
        'Authorization' => "Bearer {$hfToken}",
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
    ],
    'json' => [
        'inputs' => $prompt,
        'prompt' => $prompt,
        'model'  => "openai/gpt-oss-20b",
        'parameters' => [
            'max_new_tokens' => 500,
        ],
        'options' => [
            'use_cache' => false,
        ],
    ],
]);

$out = json_decode($hfResp->getBody(), true);

// 6) Extract AI comments
$aiComments = [];
if (isset($out['choices'][0]['text'])) {
    $aiComments = json_decode($out['choices'][0]['text'], true);
}

if (!$aiComments || !is_array($aiComments)) {
    echo "No AI comments generated.\n";
    exit(0);
}

// 7) Post inline review comments
foreach ($aiComments as $c) {
    if (!isset($c['file'], $c['line'], $c['comment'])) continue;

    try {
        $client->post("https://api.github.com/repos/{$repo}/pulls/{$pr}/comments", [
            'headers' => [
                'Authorization' => "Bearer {$ghToken}",
                'Accept'        => 'application/vnd.github+json',
            ],
            'json' => [
                'body' => $c['comment'],
                'path' => $c['file'],
                'line' => $c['line'],
                'side' => 'RIGHT',  // comment on new code
            ],
        ]);
        echo "Comment added to {$c['file']} on line {$c['line']}\n";
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        echo "Failed to post comment: " . $e->getMessage() . "\n";
    }
}

echo "AI review completed for PR #{$pr}\n";
