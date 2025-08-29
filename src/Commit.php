<?php

namespace Ay4t\PCGG;

/**
 * Class Commit
 * Used to generate automatic commit messages with AI powered by LLM API
 */
class Commit
{

    /**
     * Konfigurasi tambahan
     * @property $config array
     */
    private $config = [];

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
    private string $model = 'meta-llama/llama-4-maverick-17b-128e-instruct';

    /**
     * System prompt untuk LLM
     * @property $systemPrompt string
     */
    private string $systemPrompt;

    /**
     * 
     * @var string
     */
    private string $prefix = '';
    
    /**
     * Flag untuk mengaktifkan/menonaktifkan fitur history commit
     * @var bool
     */
    private bool $enableCommitHistory = true;

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
        $this->config = $config;

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
        
        // Tambahkan commit history sebagai konteks jika tersedia
        $commitHistory = $this->getCommitHistory();
        if (!empty($commitHistory)) {
            $prompt[] = '### Berikut adalah beberapa commit sebelumnya:';
            $prompt[] = $commitHistory;
        }
        
        $prompt[] = $this->promptTemplate();

        $prompt_string = implode("\n", $prompt);
        return $this->generateMessage( $prompt_string );
    }

    private function generateMessage(string $prompt)
    {
        try {

            /* payload untuk OpenAI, Groq dan lainnya */
            $payload = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Anda adalah Git commit message generator yang menganalisis perubahan kode dan menghasilkan pesan commit yang terstandarisasi.\nPastikan response dalam format JSON yang valid. Output yang seharusnya keluar: { "emoji": "✨", "title": "your title", "type": "feat", "body": "- [x] your desc 1 \n-[x] your desc 2 \n- [x] other" }'
                    ], [
                        'role' => 'user',
                        'content' => "### Berikut adalah output `git diff`:\n" . $this->diff_message . "\n\n"                                             
                    ],
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'description' => 'Get and format commit message from git diff data',
                            'name' => 'formatCommitMessage',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'emoji' => [
                                        'type' => 'string',
                                        'description' => 'Emoji yang cocok untuk commit message',
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'description' => 'Jenis commit, feat/refactor/fix/chore dan lain sebagainya',
                                    ],
                                    'title' => [
                                        'type' => 'string',
                                        'description' => 'Judul commit message dengan singkat, padat, dan jelas',
                                    ],
                                    'body' => [
                                        'type' => 'string',
                                        'description' => "body content dalam bullet point. format:\n- [x] ini deskripsi perubahan pertama \n- [x] ini perubahan kedua \n-[x] perubahan lainnya",
                                    ],
                                ],
                                'required' => ['emoji', 'type', 'title'],
                            ]
                        ]
                    ]
                ], 
                'temperature' => 0.2,
                'tool_choice' => 'auto',
            ];

            /* jika config provider adalah gemini */
            if( $this->config['provider'] === 'gemini' ){
                $payload['response_format'] = [
                    'type' => 'json_object'
                ];
            }

            $url = rtrim($this->apiEndpoint, '/');

                        
            // Inisialisasi Guzzle HTTP Client
            $client = new \GuzzleHttp\Client();
            
            // Melakukan request ke Gemini API menggunakan Guzzle
            $guzzleResponse = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->apiKey}"
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

            // parse response dari OpenAI
            $text = $responseData['choices'][0]['message'];

            /* tool call */
            if( $this->config['provider'] === 'gemini' ){
                $tool_call = $text['content'] ?? [];
                $result_tool_call = $this->formatCommitMessage( json_decode($tool_call, true) );
            } else {
                $tool_call = $text['tool_calls'][0]['function'] ?? [];
                if(empty($tool_call)){
                    throw new \Exception("Failed tool calling. Please try again !", 500);                
                }

                $tool_data      = $tool_call['arguments'];
                $function_name  = $tool_call['name'];

                $result_tool_call = $this->$function_name( json_decode($tool_data, true) );
            }

            /* simpan raw output dari llm kedalam file log */
            $this->saveLog($text);

            return $result_tool_call;

        } catch (\Exception $e) {
            throw new \Exception('LLM API Error: ' . $e->getMessage());
        }
    }

    private function saveLog($data) {
        $log_path = __DIR__ . '/../logs/';
        $log_file = $log_path . date('Y-m-d') . '.log';
        /* jika folder belum tersedia, maka otomatis buat */
        if (!file_exists($log_path)) {
            mkdir($log_path, 0777, true);
        }

        /* jika $data adalah string */
        if(is_string($data)){
            file_put_contents($log_file, $data . PHP_EOL, FILE_APPEND);
        }

        /* jika $data adalah array */
        if(is_array($data)){
            file_put_contents($log_file, json_encode($data) . PHP_EOL, FILE_APPEND);
        }
            
    }

    public function formatCommitMessage(array $data) {
        $str = '';

        if(!empty($this->prefix)){
            $str .= $this->prefix . ' ';
        }

        $str .=  $data['emoji'].' ' .$data['type'].': '. $data['title'];

        if(isset($data['body']) && !empty($data['body'])){
            $str .= "\n" . $data['body'];
        }
        
        return $str;
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
            Hasilkan pesan commit Git yang ringkas dan standar berdasarkan data yang diberikan di atas perubahan. Ikuti pedoman berikut:
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

    public function setPrefix(string $prefix){
        $this->prefix = $prefix;
        return $this;
    }
    
    /**
     * Mengaktifkan/menonaktifkan fitur history commit
     * @param bool $enable
     * @return $this
     */
    public function enableCommitHistory(bool $enable = true){
        $this->enableCommitHistory = $enable;
        return $this;
    }

    /**
     * Alias untuk mengaktifkan/menonaktifkan fitur history commit (kompatibilitas API)
     * @param bool $enable
     * @return $this
     */
    public function enableGitHistory(bool $enable = true){
        return $this->enableCommitHistory($enable);
    }
    
    /**
     * Mendapatkan commit history dari file
     * @param int $limit Jumlah commit history yang akan diambil
     * @return string
     */
    private function getCommitHistory(int $limit = 10) {
        // Jika fitur dinonaktifkan, tidak perlu mengambil history
        if (!$this->enableCommitHistory) {
            return '';
        }

        // Tentukan direktori proyek (fallback ke cwd)
        $dir = isset($this->config['project_dir']) ? $this->config['project_dir'] : getcwd();

        // Pastikan ini repository git
        $gitDir = rtrim($dir, '/');
        if (!is_dir($gitDir.'/.git')) {
            return '';
        }

        // Jalankan git log
        $cmd = "cd " . escapeshellarg($gitDir) . " && git log -n " . intval($limit) . " --pretty=format:'%h | %ad | %s' --date=short";
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            return '';
        }

        return implode("\n", $output);
    }
}
