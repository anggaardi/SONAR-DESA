<?php

declare(strict_types=1);

namespace App\Modules\Audit\Reading;

/**
 * Interface for reading and extracting rows from an RAB document.
 * Implemented by AiRabReader (OpenAI/DataByte API) and FixtureRabReader (local demo/test fixtures).
 */
interface RabReader
{
    /**
     * @param  string  $filePath
     * @param  list<StandardRef>  $standards
     */
    public function read(string $filePath, array $standards): RabReadResult;
}
