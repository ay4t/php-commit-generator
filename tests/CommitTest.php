<?php

namespace Ay4t\PCGG\Tests;

use PHPUnit\Framework\TestCase;
use Ay4t\PCGG\Commit;

class CommitTest extends TestCase
{
    private const FAKE_API_KEY = 'test-api-key';
    private Commit $commit;

    protected function setUp(): void
    {
        $this->commit = new Commit(self::FAKE_API_KEY);
    }

    public function testGenerate(): void
    {
        $diff = $this->exampleDiff();
        $this->commit->gitDiff( $diff );
        $result = $this->commit->generate();

        var_dump($result);

        $this->assertIsString($result);
    }

    private function exampleDiff(): string
    {
        return '<<< DIFF
            diff --git a/src/Commit.php b/src/Commit.php
            index 1a4f5ec..b9e6c9f 100644
            --- a/src/Commit.php
            +++ b/src/Commit.php
            @@ -1,7 +1,7 @@
            <?php

            namespace Ay4t\PCGG;

            -use Ay4t\PCGG\Groq;
            +use Ay4t\PCGG\Groq\Groq;

            class Commit
            {
                private string $apiKey;
                }
        ';
    }
    
}
