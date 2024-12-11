<?php

namespace Ay4t\PCGG;

use LucianoTonet\GroqPHP\Groq;

/**
 * Class Commit
 * Used to generate automatic commit messages with AI powered by Groq API
 */

class Commit
{

    /**
     * Pesan git diff
     * @property $diff_message string
     */
    private string $diff_message;
    
    /**
     * Groq API
     * @property $groq Groq
     */
    private Groq $groq;

    /**
     * Api key untuk mengakses Groq API
     * @property $apiKey string
     */
    private string $apiKey;
    
    /**
     * Constructor
     * @param string $apiKey
     * @param string $diff_message
     */
    public function __construct(string $apiKey = '', string $diff_message = '')
    {
        $this->apiKey           = $apiKey;
        $this->diff_message     = $diff_message;
        $this->groq             = new Groq( $this->apiKey );
    }

    /**
     * Set api key
     * @param string $apiKey
     */
    public function setApiKey( string $apiKey ) 
    {
        $this->apiKey = $apiKey;
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
            $response = $this->groq->chat()->completions()->create([
                'model' => 'llama3-groq-70b-8192-tool-use-preview',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'generate_commit',
                            'description' => 'Generate a standardized git commit message',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'enum' => ['feat', 'fix', 'docs', 'style', 'refactor', 'test', 'chore'],
                                        'description' => 'Type of commit following conventional commits standard'
                                    ],
                                    'scope' => [
                                        'type' => 'string',
                                        'description' => 'Scope of the commit (optional)'
                                    ],
                                    'title' => [
                                        'type' => 'string',
                                        'description' => 'Short commit title (50 chars or less)'
                                    ],
                                    'description' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'string'
                                        ],
                                        'description' => 'List of changes in bullet points'
                                    ],
                                    'breaking_change' => [
                                        'type' => 'string',
                                        'description' => 'Description of breaking changes if any'
                                    ],
                                    'emoji' => [
                                        'type' => 'string',
                                        'description' => 'Relevant emoji for the commit type'
                                    ]
                                ],
                                'required' => ['type', 'title', 'description']
                            ]
                        ]
                    ]
                ],
                'tool_choice' => [
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_commit'
                    ]
                ]
            ]);
    
            $toolCalls = $response['choices'][0]['message']['tool_calls'] ?? null;
            if (!$toolCalls) {
                throw new \Exception('No tool calls in response');
            }
    
            $commitData = json_decode($toolCalls[0]['function']['arguments'], true);
            return $this->formatCommitMessage($commitData);
    
        } catch (\Exception $e) {
            throw new \Exception('Groq API Error', 0, $e);
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
    
    private function systemPrompt(): string
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
            - Use the Conventional Commits standard:
            - `feat`: for a new feature
            - `fix`: for a bug fix
            - `docs`: for documentation updates
            - `style`: for code formatting or style updates (non-functional changes)
            - `refactor`: for code refactoring (non-functional)
            - `test`: for adding or updating tests
            - `chore`: for maintenance tasks
            - Summarize the commit in 50 characters or less for the first line.
            - Optionally, add further details in subsequent lines to clarify the changes if needed.
            - For breaking changes, include a `BREAKING CHANGE:` note with a brief description of the impact.
            - Provide only the commit message, formatted to be ready for Git.
            - response in markdown format only

            Expected output:
            <emoji> Your short commit title
            - refactor(utils): your description
            - Refactored your description
            - Removed your description
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
