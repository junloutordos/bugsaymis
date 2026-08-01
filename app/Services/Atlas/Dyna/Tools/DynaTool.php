<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\User;

interface DynaTool
{
    /** Snake_case tool name, sent to Bedrock verbatim as the tool identifier. */
    public function name(): string;

    /** Plain-English description the model uses to decide when to call this tool. */
    public function description(): string;

    /** JSON Schema (draft-07-ish subset Bedrock accepts) describing the tool's input. */
    public function inputSchema(): array;

    /**
     * Runs the tool AS the requesting user — implementations MUST scope any query
     * to what $user is already permitted to see (mirror the equivalent web-UI query).
     *
     * @return array JSON-serializable result handed back to the model as a tool_result.
     */
    public function execute(User $user, array $input): array;
}
