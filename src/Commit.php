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

        $prompt[] = '### Berikut adalah perubahan kode dari git diff:';
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
                        
            // Inisialisasi Guzzle HTTP Client
            $client = new \GuzzleHttp\Client();
            
            // Melakukan request ke Gemini API menggunakan Guzzle
            $guzzleResponse = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $response = $guzzleResponse->getBody()->getContents();
            $httpCode = $guzzleResponse->getStatusCode();

            if ($httpCode !== 200) {
                throw new \Exception('API Error: HTTP ' . $httpCode . ' - ' . $response);
            }

            $responseData = json_decode($response, true);
            if (!$responseData) {
                throw new \Exception('Invalid JSON response');
            }

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
        // Format title line - hilangkan tanda kutip
        $title = isset($data['emoji']) ? str_replace('"', '', $data['emoji']) . ' ' : '';
        $title .= str_replace('"', '', $data['type']);
        if (!empty($data['scope'])) {
            $title .= '(' . str_replace('"', '', $data['scope']) . ')';
        }
        $title .= ': ' . str_replace('"', '', $data['title']);
        
        // Bersihkan deskripsi dari karakter yang bisa menyebabkan masalah
        $description = [];
        if (!empty($data['description'])) {
            foreach ($data['description'] as $point) {
                $cleanPoint = str_replace(
                    ['"', "'", '`', '[', ']', '(', ')', '{', '}', '*', '\\', '\n', '\r'], 
                    '', 
                    $point
                );
                $description[] = '- ' . trim($cleanPoint);
            }
        }
        
        // Format breaking change jika ada
        $breakingChange = '';
        if (!empty($data['breaking_change'])) {
            $breakingChange = "\n\nBREAKING CHANGE: " . str_replace('"', '', $data['breaking_change']);
        }
        
        // Gabungkan semua bagian dengan format yang benar
        $parts = [];
        $parts[] = $title;
        
        if (!empty($description)) {
            $parts[] = implode("\n", $description);
        }
        
        if (!empty($breakingChange)) {
            $parts[] = trim($breakingChange);
        }
        
        // Gabungkan dengan double newline
        $message = implode("\n\n", array_filter($parts));
        
        // Bersihkan karakter kontrol kecuali newline
        $cleaned = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);
        
        return $cleaned;
    }
    
    private function defaultSystemPrompt(): string
    {
        return 'Anda adalah generator pesan commit Git yang menganalisis perubahan kode dan menghasilkan pesan commit yang distandarisasi. 
        Anda harus menggunakan fungsi generate_commit untuk memformat respon Anda.
        Analisis perbedaan git dengan cermat dan identifikasi:
        1. Jenis perubahan (feat, fix, dll.)
        2. Ruang lingkup perubahan jika berlaku
        3. Judul yang singkat dan ringkas yang merangkum perubahan
        4. Poin-poin detail yang menjelaskan perubahan
        5. Perubahan yang merusak
        6. Emoji yang sesuai untuk jenis perubahan';
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
     * Prompt template
     * @return string
     */
    private function promptTemplate(): string
    {
        return '
            Generate a concise, standardized Git commit message based on the provided data above changes. Follow these guidelines:
            1. Gunakan Conventional Commits standard:
            * `feat`: untuk fitur baru
            * `fix`: untuk perbaikan bug
            * `docs`: untuk update dokumentasi
            * `style`: untuk update kode formatting atau style (perubahan non-fungsional)
            * `refactor`: untuk refactor kode (perubahan non-fungsional)
            * `test`: untuk menambahkan atau memperbarui test
            * `chore`: untuk tugas maintenance
            2. Ringkaskan commit dalam 50 karakter atau kurang untuk baris pertama.
            3. Secara opsional, tambahkan detail lebih lanjut dalam baris-baris berikut untuk menjelaskan perubahan jika diperlukan.
            4. Untuk perubahan yang merusak, include catatan `BREAKING CHANGE:` dengan deskripsi singkat tentang dampaknya.
            5. Berikan hanya pesan commit, diformat agar siap untuk Git.
            6. Respons dalam format markdown saja

            Output yang diharapkan:
            <emoji> Judul commit Anda yang singkat
            - [x] refactor(utils): deskripsi Anda
            - [x] Menulis ulang deskripsi Anda
            - [x] Menghapus deskripsi Anda
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
