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

        $prompt[] = $this->promptTemplate();
        $prompt[] = '### Here is the `git diff` output:';
        $prompt[] = $this->diff_message;

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
            You are tasked with generating **perfect git commit messages** based on `git diff` output, using the following detailed standards and guidelines:

            ### Guidelines for Commit Message Structure
            1. **Commit Title**:
            - Summarize the changes concisely in a single line (50 characters or fewer).
            - If the diff involves multiple, similar changes, use a broad, high-level description that captures the overall purpose of the changes (e.g., "Refactor user auth module" instead of "Refactor user login and registration functions").
            - **Verb Format**: Start with an imperative verb (e.g., *Fix*, *Update*, *Add*, *Remove*, *Refactor*, *Optimize*, *Implement*, *Document*, *Improve*, etc.).
            - **Adjust for Length**: If the initial suggested title is too long, rewrite to capture the primary focus, keeping under 50 characters without losing clarity.

            2. **Commit Description**:
            - Provide context and details in the description below the title.
            - **Explain the Reasoning**: Clearly state why the changes were necessary and how they improve the codebase. Address the problem being solved, if applicable.
            - **Describe Changes by Category**:
                - **New Features**: Describe new functionalities, what they accomplish, and why they’re being added.
                - **Fixes**: Explain the issue or bug, how it was identified, and what the solution involved.
                - **Refactoring**: Describe which code areas were modified and how they improve maintainability, readability, or performance.
                - **Documentation**: Specify the documentation updated, added, or corrected, and why these changes improve understanding or accuracy.
                - **Optimization**: Explain the areas of improvement and the reason for optimization (e.g., performance gains, memory efficiency).
                - **Dependency Updates**: If any dependencies were added, removed, or updated, specify the dependency, version change, and why.
            - **Avoid Redundancy**: Do not repeat the same information from the title. Instead, expand on it with valuable details.

            3. **Commit Standards**:
            - **Style and Grammar**: Write with correct grammar and professional tone. Avoid colloquialisms and ensure clarity.
            - **Focus on Purpose**: Each commit message should focus on the purpose of the changes, clearly communicating the intent behind each modification.
            - **No Redundant Details**: Do not include unnecessary details, filenames, or specific line numbers unless essential for understanding the context.
            - **Standard Tags (optional)**: If appropriate, include conventional tags like `[Fix]`, `[Refactor]`, `[Feature]`, `[Docs]`, etc., but only if it improves clarity.

            4. **Formatting**:
            - **First Line** (Title): 50 characters or fewer.
            - **Second Line**: Blank line separating the title from the description.
            - **Following Lines** (Description): Use bullet points for multi-part explanations, with each point under 72 characters.

            **Your task** is to:
            - Generate a complete git commit message following these standards based strictly on the content and context from the provided `git diff` output.
            - Maintain adherence to message clarity, conciseness, and professionalism throughout.
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