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
     * @property string
     */
    private string $diff_message;
    
    /**
     * Groq API
     * @property Groq
     */
    private Groq $groq;

    /**
     * Api key untuk mengakses Groq API
     * @property string
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
    private function generateMessage(string $prompt)
    {
        try {
            $response = $this->groq->chat()->completions()->create([
                'model' => 'mixtral-8x7b-32768',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are as git commit message generator '
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            throw new \Exception('Groq API Error', 0, $e);
        }

        return $response['choices'][0]['message']['content'];
    }

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
            ```bash
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