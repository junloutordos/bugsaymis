<?php

namespace App\Services\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\Atlas\DynaMessage;
use App\Models\User;

class DynaOrchestratorService
{
    private const SYSTEM_PROMPT = <<<'TEXT'
        You are Dyna, an analytics and insights assistant for Atlas, the campus management
        system for Philippine Science High School - Caraga Region Campus. You answer questions
        for the Campus Director and Division Chiefs (MANCOM) using the tools available to you.
        Always call a tool to get real data before stating any number — never estimate or
        invent statistics. If no tool can answer the question, say so plainly.
        TEXT;

    private const MAX_TOOL_TURNS = 5;

    public function __construct(
        private readonly DynaToolRegistry $tools,
        private readonly DynaBedrockClientFactory $clientFactory,
    ) {}

    public function reply(User $user, DynaConversation $conversation, string $userMessage): string
    {
        DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => now(),
        ]);

        $client = $this->clientFactory->make();
        $messages = $this->historyAsBedrockMessages($conversation);
        $messages[] = ['role' => 'user', 'content' => [['text' => $userMessage]]];

        $toolCallLog = [];

        for ($turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++) {
            $result = $client->converse([
                'modelId' => config('services.bedrock.inference_profile_id'),
                'system' => [['text' => self::SYSTEM_PROMPT]],
                'messages' => $messages,
                'toolConfig' => $this->tools->toBedrockToolConfig(),
                'inferenceConfig' => ['maxTokens' => 2048],
            ]);

            $assistantContent = $result['output']['message']['content'];
            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

            $toolUseBlocks = array_values(array_filter($assistantContent, fn ($b) => isset($b['toolUse'])));

            if (empty($toolUseBlocks)) {
                $finalText = collect($assistantContent)->pluck('text')->filter()->implode('');
                $finalText = $this->stripThinkingTags($finalText);

                DynaMessage::create([
                    'dyna_conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $finalText,
                    'tool_calls' => $toolCallLog ?: null,
                    'created_at' => now(),
                ]);

                return $finalText;
            }

            $toolResultBlocks = [];
            foreach ($toolUseBlocks as $block) {
                $toolUse = $block['toolUse'];
                $output = $this->tools->execute($toolUse['name'], $toolUse['input'] ?? [], $user);

                $toolCallLog[] = ['name' => $toolUse['name'], 'input' => $toolUse['input'] ?? [], 'result' => $output];

                // Some tools legitimately return a bare list (e.g. get_attention_items with
                // nothing flagged returns []). array_is_list() is true for both a populated
                // list AND an empty array, so this also covers the empty case — Nova's
                // Converse validator requires toolResult.content[].json to be a JSON object,
                // not an array, so any bare list gets wrapped rather than passed through raw.
                $json = is_array($output) && array_is_list($output) ? ['items' => $output] : $output;

                $toolResultBlocks[] = ['toolResult' => [
                    'toolUseId' => $toolUse['toolUseId'],
                    'content' => [['json' => $json]],
                ]];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResultBlocks];
        }

        $fallback = "I wasn't able to complete that within the allowed number of tool calls — try narrowing the question.";

        DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $fallback,
            'tool_calls' => $toolCallLog ?: null,
            'created_at' => now(),
        ]);

        return $fallback;
    }

    /**
     * Amazon Nova inlines its chain-of-thought directly in the text output as
     * <thinking>...</thinking>, rather than a separate content block the way gpt-oss's
     * reasoningContent does — strip it so users only see the actual answer. A response
     * truncated by maxTokens can leave an unclosed <thinking> tag with no real answer after
     * it; that's stripped too (falling back to a friendly message) rather than shown raw.
     */
    private function stripThinkingTags(string $text): string
    {
        $text = preg_replace('/<thinking>.*?<\/thinking>/is', '', $text);
        $text = preg_replace('/<thinking>.*/is', '', $text);
        $text = trim($text);

        return $text !== ''
            ? $text
            : "Dyna's response got cut off — try asking again, or narrow the question.";
    }

    private function historyAsBedrockMessages(DynaConversation $conversation): array
    {
        return $conversation->messages->map(fn (DynaMessage $m) => [
            'role' => $m->role,
            'content' => [['text' => $m->content]],
        ])->all();
    }
}
