<?php

namespace Ay4t\PCGG;

/**
 * Class Commit
 * Used to generate automatic commit messages with AI powered by LLM API
 */
class Commit
{
    /**
     * Pesan git diff
     * @property $diff_message string
     */
    private string $diff_message = '';

    /**
     * API Key untuk mengakses LLM API
     * @property $apiKey string
     */
    private string $apiKey = '';

    /**
     * API Endpoint untuk LLM
     * @property $apiEndpoint string
     */
    private string $apiEndpoint = 'https://api.groq.com/v1/chat/completions';

    /**
     * Model yang akan digunakan
     * @property $model string
     */
    private string $model = 'llama-3.3-70b-versatile';

    /**
     * System prompt untuk LLM
     * @property $systemPrompt string
     */
    private string $systemPrompt;

    /**
     * Constructor
     * @param string $apiKey
     * @param string $diff_message
     * @param array $config Konfigurasi tambahan (endpoint, model, systemPrompt)
     */
    public function __construct(string $apiKey = '', string $diff_message = '', array $config = [])
    {
        $this->apiKey = $apiKey;
        $this->diff_message = $diff_message;
        $this->systemPrompt = $this->defaultSystemPrompt();

        // Set konfigurasi tambahan jika ada
        if (isset($config['endpoint'])) $this->apiEndpoint = $config['endpoint'];
        if (isset($config['model'])) $this->model = $config['model'];
        if (isset($config['systemPrompt'])) $this->systemPrompt = $config['systemPrompt'];
    }

    /**
     * Set api key
     * @param string $apiKey
     */
    public function setApiKey(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Set API endpoint
     * @param string $endpoint
     */
    public function setEndpoint(string $endpoint)
    {
        $this->apiEndpoint = $endpoint;
    }

    /**
     * Set model
     * @param string $model
     */
    public function setModel(string $model)
    {
        $this->model = $model;
    }

    /**
     * Set system prompt
     * @param string $prompt
     */
    public function setSystemPrompt(string $prompt)
    {
        $this->systemPrompt = $prompt;
    }

    /**
     * Generate commit message
     * @return string
     */
    public function generate() 
    {
        /* validation */
        $this->validation();

        $prompt[] = '### Here is the `git diff` output:';
        $prompt[] = $this->diff_message;
        $prompt[] = $this->promptTemplate();

        $prompt_string = implode("\n", $prompt);
        return $this->generateMessage( $prompt_string );
    }

    private function generateMessage(string $prompt)
    {
        try {
            // Menyiapkan payload untuk Gemini API dengan format yang lebih sederhana
            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => "Anda adalah Git commit message generator yang menganalisis perubahan kode dan menghasilkan pesan commit yang terstandarisasi.\n\n" .
                                         "### Berikut adalah output `git diff`:\n" . 
                                         $this->diff_message . "\n\n" .
                                         "Berikan pesan commit dalam format JSON berikut:\n" .
                                         "{\n" .
                                         "  \"type\": \"feat/fix/docs/style/refactor/test/chore\",\n" .
                                         "  \"title\": \"judul singkat (max 50 karakter)\",\n" .
                                         "  \"description\": [\"poin perubahan 1\", \"poin perubahan 2\"],\n" .
                                         "  \"emoji\": \"emoji yang sesuai\"\n" .
                                         "}\n\n" .
                                         "Pastikan response dalam format JSON yang valid."
                            ]
                        ]
                    ]
                ]
            ];

            // Membangun URL endpoint
            $url = rtrim($this->apiEndpoint, '/') . '/models/' . $this->model . ':generateContent?key=' . $this->apiKey;
            
            // Debug: tampilkan URL dan payload
            /* echo "\nURL Request: " . $url . "\n";
            echo "\nPayload:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n"; */
            
            // Melakukan request ke Gemini API
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($payload)
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Debug: tampilkan response mentah
            /* echo "\nHTTP Code: " . $httpCode . "\n";
            echo "\nResponse Raw:\n" . $response . "\n"; */

            if ($httpCode !== 200) {
                throw new \Exception('API Error: HTTP ' . $httpCode . ' - ' . $response);
            }

            $responseData = json_decode($response, true);
            if (!$responseData) {
                throw new \Exception('Invalid JSON response');
            }

            // Debug: tampilkan response yang sudah di-decode
            /* echo "\nResponse Decoded:\n" . json_encode($responseData, JSON_PRETTY_PRINT) . "\n"; */

            // Parse response dari Gemini API
            $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (empty($text)) {
                throw new \Exception('Response kosong dari API');
            }

            // Ekstrak JSON dari response text
            preg_match('/\{.*\}/s', $text, $matches);
            if (empty($matches[0])) {
                throw new \Exception('Tidak ditemukan format JSON dalam response');
            }

            $commitData = json_decode($matches[0], true);
            if (!$commitData) {
                throw new \Exception('Format JSON tidak valid dalam response');
            }

            // Validasi data commit
            if (!isset($commitData['type']) || !isset($commitData['title']) || !isset($commitData['description'])) {
                throw new \Exception('Data commit tidak lengkap');
            }

            return $this->formatCommitMessage($commitData);

        } catch (\Exception $e) {
            throw new \Exception('LLM API Error: ' . $e->getMessage());
        }
    }
    
    private function formatCommitMessage(array $data): string
    {
        $message = [];
        
        // Format title line
        $title = isset($data['emoji']) ? "{$data['emoji']} " : '';
        $title .= $data['type'];
        if (!empty($data['scope'])) {
            $title .= "({$data['scope']})";
        }
        $title .= ": {$data['title']}";
        $message[] = $title;
        
        // Add description points
        if (!empty($data['description'])) {
            $message[] = "";  // Empty line after title
            foreach ($data['description'] as $point) {
                $message[] = "[x] {$point}";
            }
        }
        
        // Add breaking change if present
        if (!empty($data['breaking_change'])) {
            $message[] = "";  // Empty line before breaking change
            $message[] = "BREAKING CHANGE: {$data['breaking_change']}";
        }
        
        return implode("\n", $message);
    }
    
    private function defaultSystemPrompt(): string
    {
        return 'You are a Git commit message generator that analyzes code changes and generates standardized commit messages. 
        You must use the generate_commit function to format your response.
        Analyze the git diff carefully and identify:
        1. The type of change (feat, fix, etc.)
        2. The scope of the change if applicable
        3. A concise title that summarizes the change
        4. Detailed bullet points explaining the changes
        5. Any breaking changes
        6. An appropriate emoji for the type of change';
    }

    /**
     * Set git diff
     * @param string $diff_message
     */
    public function gitDiff( string $diff_message) 
    {
        $this->diff_message = $diff_message;
    }
    
    /**
     * Generate commit message
     * @param string $prompt
     * @return string
     */

    /**
     * Prompt template
     * @return string
     */
    private function promptTemplate(): string
    {
        return '
            Generate a concise, standardized Git commit message based on the provided data above changes. Follow these guidelines:
            1. Use the Conventional Commits standard:
            * `feat`: for a new feature
            * `fix`: for a bug fix
            * `docs`: for documentation updates
            * `style`: for code formatting or style updates (non-functional changes)
            * `refactor`: for code refactoring (non-functional)
            * `test`: for adding or updating tests
            * `chore`: for maintenance tasks
            2. Summarize the commit in 50 characters or less for the first line.
            3. Optionally, add further details in subsequent lines to clarify the changes if needed.
            4. For breaking changes, include a `BREAKING CHANGE:` note with a brief description of the impact.
            5. Provide only the commit message, formatted to be ready for Git.
            6. response in markdown format only

            Expected output:
            <emoji> Your short commit title
            - [x] refactor(utils): your description
            - [x] Refactored your description
            - [x] Removed your description
        ';
    }

    /**
     * Validation
     * @return bool
     */
    private function validation() 
    {
        if ( empty($this->apiKey) ) {
            throw new \Exception('API key is empty');
        }

        if ( empty($this->diff_message) ) {
            throw new \Exception('Diff message is empty');
        }

        return true;
    }
    

}
